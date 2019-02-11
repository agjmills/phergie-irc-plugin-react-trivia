<?php

namespace Asdfx\Phergie\Plugin\Trivia;

use Asdfx\Phergie\Plugin\Trivia\Models\Message;
use Asdfx\Phergie\Plugin\Trivia\Models\User;
use Asdfx\Phergie\Plugin\Trivia\Models\UsersPoints;
use Asdfx\Phergie\Plugin\Trivia\Models\Question;
use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Client\React\LoopAwareInterface;
use Phergie\Irc\ConnectionInterface;
use Phergie\Irc\Event\UserEventInterface;
use Phergie\Irc\Bot\React\EventQueueInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

class Plugin extends AbstractPlugin implements LoopAwareInterface
{
    const MODE_OFF = 0;
    const MODE_ON = 1;
    const MODE_ASKING = 2;
    const MODE_WAITING = 3;

    /**
     * @var ?int
     */
    private $firstHintTime = null;

    /**
     * @var ?int
     */
    private $secondHintTime = null;

    /**
     * @var ?int
     */
    private $doneTime = null;

    /**
     * @var ?int
     */
    private $nextTime = null;

    /**
     * @var ?string
     */
    private $hint = null;

    /**
     * @var array
     */
    private $question = [];

    /**
     * @var int
     */
    private $points = 3;

    /**
     * @var array
     */
    private $config = [];

    /**
     * @var Providers\Database
     */
    private $database;

    /**
     * @var int
     */
    private $mode = 0;

    /**
     * @var \SplObjectStorage
     */
    protected $connections;

    public function __construct(array $configuration = [])
    {
        $this->config = $configuration;
        $this->database = new Providers\Database('sqlite', '', 'trivia.db');
        $this->question = ['answer' => null, 'question' => null];
    }

    /**
     * Bind plugin methods to ones emitted by phergie
     *
     * @return array
     */
    public function getSubscribedEvents(): array
    {
        return [
            'irc.received.privmsg' => 'onPrivmsg',
            'connect.after.each' => 'addConnection',
        ];
    }

    /**
     * Set a timer to execute Plugin::triviaLoop() every 1 second
     *
     * @param LoopInterface $loop
     */
    public function setLoop(LoopInterface $loop)
    {
        $loop->addPeriodicTimer(1, [$this, 'triviaLoop']);
    }

    /**
     * @param ConnectionInterface $connection
     */
    public function addConnection(ConnectionInterface $connection)
    {
        $this->getConnections()->attach($connection);
    }

    /**
     * @return \SplObjectStorage
     */
    public function getConnections()
    {
        if (!$this->connections) {
            $this->connections = new \SplObjectStorage;
        }
        return $this->connections;
    }

    /**
     * For each connection the bot is connected to, grab the event queue and pass it into the handleTrivia method
     *
     * @param TimerInterface $timer
     */
    public function triviaLoop(TimerInterface $timer)
    {
        $factory = $this->getEventQueueFactory();
        foreach ($this->getConnections() as $connection) {
            $queue = $factory->getEventQueue($connection);
            $this->handleTrivia($queue);
        }
    }

    /**
     * If we are waiting for an answer, then determine whether it is time to output a hint, or end the question,
     * or move on to the next question.
     *
     * @param $queue
     */
    private function handleTrivia($queue)
    {
        $time = time();

        if ($this->mode === self::MODE_WAITING && !is_null($this->firstHintTime) && $time >= $this->firstHintTime) {
            $this->hint = $this->getHint($this->question['answer']);
            $queue->ircPrivmsg($this->config['channel'], $this->hint);
            $this->firstHintTime = null;
            $this->points = ceil($this->points / 2);
        } else if ($this->mode === self::MODE_WAITING && !is_null($this->secondHintTime) && $time >= $this->secondHintTime) {
            $queue->ircPrivmsg($this->config['channel'], $this->getHint($this->question['answer'], $this->hint));
            $this->secondHintTime = null;
            $this->points = ceil($this->points / 2);
        } else if ($this->mode === self::MODE_WAITING && !is_null($this->doneTime) && $time >= $this->doneTime) {
            $queue->ircPrivmsg($this->config['channel'], 'Times up!');
            $this->doneTime = null;
            $this->missed($queue);
        } else if ($this->mode === self::MODE_WAITING && !is_null($this->nextTime) && $time >= $this->nextTime) {
            $this->nextTime = null;
            $this->next(null, $queue);
        }
    }

    /**
     * Takes a string, and replaces the characters with asterisks to be used as a hint
     * If a 'last' parameter is provided, then use that as a starting point
     *
     * @param $answer
     * @param string $last
     * @return string
     */
    private function getHint(string $answer, string $last = ''): string
    {
        $parts = str_split($answer);
        $lastParts = str_split($last);
        $response = "";

        foreach ($parts as $index => $letter) {
            if ($last == "") {
                if ($letter == " ") {
                    $response .= " ";
                } else if (rand(1, 4) == 2) {
                    $response .= $letter;
                } else {
                    $response .= "*";
                }
            } else {
                if ($lastParts[$index] != "*") {
                    $response .= $lastParts[$index];
                } else if ($letter == " ") {
                    $response .= " ";
                } else if ($lastParts[$index] == "*" && rand(1, 2) == 2) {
                    $response .= $letter;
                } else {
                    $response .= "*";
                }
            }
        }

        return $response;
    }

    /**
     * Start trivia by asking a question
     *
     * @param UserEventInterface $event
     * @param EventQueueInterface $queue
     */
    private function start(UserEventInterface $event, EventQueueInterface $queue)
    {
        $this->mode = self::MODE_ON;
        $queue->ircPrivmsg($this->config['channel'], Message::getStart());
        $this->ask($queue);
    }

    /**
     * If a private message comes to the bot, determine what to do if it is a command.
     *
     * @param UserEventInterface $event
     * @param EventQueueInterface $queue
     */
    public function onPrivmsg(UserEventInterface $event, EventQueueInterface $queue)
    {
        if ($event->getSource() !== $this->config['channel']) {
            return;
        }
 
        $eventParams = $event->getParams();
        $messageParts = explode(' ', $eventParams['text']);
        switch ($messageParts[0]) {
            case ".start":
                if ($this->mode == self::MODE_OFF) {
                    $this->start($event, $queue);
                } else {
                    $queue->ircPrivmsg($this->config['channel'], Message::getNoCanDo());
                }
                break;
            case '.stop':
                if ($this->mode != self::MODE_OFF) {
                    $this->stop($event, $queue);
                } else {
                    $queue->ircPrivmsg($this->config['channel'], Message::getNoCanDo());
                }
                break;
            case '.score':
                $nick = $event->getNick();
                $points = $this->points($nick);
                $queue->ircPrivmsg($this->config['channel'], Message::getScore($nick, $points));
                break;
            default:
                if (strtolower($eventParams['text']) === strtolower($this->question['answer']) && $this->mode === self::MODE_WAITING) {
                    $this->correct($event, $queue);
                }
                break;
        }
    }

    /**
     * When a correct answer is given, tell the user its correct, increment their points, and move on to the next
     * question
     *
     * @param UserEventInterface $event
     * @param EventQueueInterface $queue
     */
    private function correct(UserEventInterface $event, EventQueueInterface $queue)
    {
        $nick = $event->getNick();
        $queue->ircPrivmsg($this->config['channel'], Message::getCorrect($nick, $this->points));

        $user = User::where('nick', $nick)->first();
        if ($user === null) {
            $user = User::create(['nick' => $nick, 'points' => 0]);
        }

        UsersPoints::create(['user_id' => $user->id, 'points' => $this->points]); 

        $this->nextTime = time() + 5;
        $this->next($event, $queue);
    }

    /**
     * If nobody answers the question, output the answer and move onto the next question in 5 seconds.
     *
     * @param EventQueueInterface $queue
     */
    private function missed(EventQueueInterface $queue)
    {
        $queue->ircPrivmsg($this->config['channel'], Message::getWrong());
        $queue->ircPrivmsg($this->config['channel'], 'Answer: ' . $this->question['answer']);
        $this->nextTime = time() + 5;
    }

    /**
     * Move on to the next question by resetting all of our timers.
     *
     * @param EventQueueInterface $queue
     */
    private function next(?UserEventInterface $event = null, EventQueueInterface $queue)
    {
        $this->mode = self::MODE_ON;
        $this->firstHintTime = null;
        $this->secondHintTime = null;
        $this->doneTime = null;
        $this->nextTime = null;
        $this->hint = '';
        $this->ask($queue);
    }

    /**
     * Disable trivia
     *
     * @param UserEventInterface $event
     * @param EventQueueInterface $queue
     */
    private function stop(UserEventInterface $event, EventQueueInterface $queue)
    {
        $this->mode = self::MODE_OFF;
        $queue->ircPrivmsg($this->config['channel'], Message::getStop());
    }

    /**
     * Grab a random question from the database, and setup out timers.
     *
     * @param EventQueueInterface $queue
     */
    private function ask(EventQueueInterface $queue)
    {
        $this->mode = self::MODE_ASKING;
        $this->question = Question::inRandomOrder()->first();
        $queue->ircPrivmsg($this->config['channel'], Message::getAsk());
        $queue->ircPrivmsg($this->config['channel'], $this->question['question']);

        $this->mode = self::MODE_WAITING;
        $this->firstHintTime = time() + 30;
        $this->secondHintTime = time() + 60;
        $this->doneTime = time() + 90;

        $queue->ircPrivmsg($this->config['channel'], Message::getTimeLimitAnswer($this->doneTime - time()));

        $this->points = 3;
    }

    /**
     * @param string $nick
     * @return int
     */
    private function points(string $nick): int {
        $user = User::where('nick', $nick)->first();

        if ($user === null) {
            return 0;
        }

        return UsersPoints::where('user_id', $user->id)->sum('points');
    }
}
