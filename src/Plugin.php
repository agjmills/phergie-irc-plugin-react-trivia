<?php

namespace Asdfx\Phergie\Plugin\Trivia;

use Asdfx\Phergie\Plugin\Trivia\Models\User;
use Asdfx\Phergie\Plugin\Trivia\Models\Question;
use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Client\React\LoopAwareInterface;
use Phergie\Irc\Client\React\WriteStream;
use Phergie\Irc\Connection;
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
    private $firstHintTime = null;
    private $secondHintTime = null;
    private $doneTime = null;
    private $nextTime = null;
    private $hint = null;
    private $question = [];
    private $points = 3;
    private $config = [];
    private $database;
    private $mode = 0;
    protected $connections;

    public function __construct(array $configuration = [])
    {
        $this->config = $configuration;
        $this->database = new Providers\Database('sqlite', '', 'trivia.db');
        $this->question = ['answer' => null, 'question' => null];
    }

    public function getSubscribedEvents()
    {
        return [
            'irc.tick' => 'onTick',
            'irc.received.privmsg' => 'onPrivmsg',
            'connect.after.each' => 'addConnection',
        ];
    }

    public function setLoop(LoopInterface $loop)
    {
        $loop->addPeriodicTimer(2, [$this, 'triviaLoop']);
    }

    public function addConnection(ConnectionInterface $connection)
    {
        $this->getConnections()->attach($connection);
    }

    public function getConnections()
    {
        if (!$this->connections) {
            $this->connections = new \SplObjectStorage;
        }
        return $this->connections;
    }

    public function triviaLoop(TimerInterface $timer)
    {
        $factory = $this->getEventQueueFactory();
        foreach ($this->getConnections() as $connection) {
            $queue = $factory->getEventQueue($connection);
            $this->handleTrivia($queue);
        }
    }

    private function handleTrivia($queue)
    {
        $time = time();

        echo 'Tick';
        echo 'Mode: ' . $this->mode;
        echo 'First Hint Time: ' . $this->firstHintTime;
        echo 'Current Time: ' . $time;
        echo '###########################';

        if ($this->mode === self::MODE_WAITING && !is_null($this->firstHintTime) && $time >= $this->firstHintTime) {
            echo 'First Hint';
            $this->hint = $this->getHint($this->question['answer']);
            $queue->ircPrivmsg($this->config['channel'], $this->hint);
            $this->firstHintTime = null;
            $this->points = ceil($this->points / 2);
        } else if ($this->mode === self::MODE_WAITING && !is_null($this->secondHintTime) && $time >= $this->secondHintTime) {
            echo 'Second Hint';
            $queue->ircPrivmsg($this->config['channel'], $this->getHint($this->question['answer'], $this->hint));
            $this->secondHintTime = null;
            $this->points = ceil($this->points / 2);
        } else if ($this->mode === self::MODE_WAITING && !is_null($this->doneTime) && $time >= $this->doneTime) {
            echo 'TIMES UP';
            $queue->ircPrivmsg($this->config['channel'], 'Times up!');
            $this->doneTime = null;
            $this->missed($event, $queue);
        } else if ($this->mode === self::MODE_WAITING && !is_null($this->nextTime) && $time >= $this->nextTime) {
            echo 'Next Question';
            $this->nextTime = null;
            $this->next($event, $queue);
        }
    }

    private function getHint($answer, $last = '')
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

    private function start(UserEventInterface $event, EventQueueInterface $queue)
    {
        $this->mode = self::MODE_ON;
        $queue->ircPrivmsg($this->config['channel'], 'I want to play some trivia, don\'t you?!');
        $this->ask($event, $queue);
    }

    public function onPrivmsg(UserEventInterface $event, EventQueueInterface $queue)
    {
        $eventParams = $event->getParams();
        $messageParts = explode(' ', $eventParams['text']);
        switch ($messageParts[0]) {
            case ".start":
                if ($this->mode == self::MODE_OFF) {
                    $this->start($event, $queue);
                } else {
                    $queue->ircPrivmsg($this->config['channel'], 'I\'m sorry Dave, I can\'t do that right now.');
                }
                break;
            case '.stop':
                if ($this->mode != self::MODE_OFF) {
                    $this->stop($event, $queue);
                } else {
                    $queue->ircPrivmsg($this->config['channel'], 'I\'m sorry Dave, I can\'t do that right now.');
                }
                break;
            default:
                if (strtolower($eventParams['text']) === strtolower($this->question['answer'])) {
                    $this->correct($event, $queue);
                } else {
                }
                break;
        }
    }

    public function onTick(WriteStream $stream, Connection $connection)
    {
        $stream->emit('trivia');
    }

    private function correct(UserEventInterface $event, EventQueueInterface $queue)
    {
        $nick = $event->getNick();
        $queue->ircPrivmsg($this->config['channel'], 'Correct! ' . $nick . ' gets ' . $this->points . ' points');

        $user = User::where('nick', $nick)->first();
        if ($user === null) {
            $user = User::create(['nick' => $nick]);
        }

        $user->points = $user->points + $this->points;

        $user->save();
        $this->next($event, $queue);
    }

    private function missed(UserEventInterface $event, EventQueueInterface $queue)
    {
        $queue->ircPrivmsg($this->config['channel'], 'Were you sleeping? Sit here and study the correct answer.');
        $queue->ircPrivmsg($this->config['channel'], 'Answer: ' . $this->question['answer']);
        $this->nextTime = time() + 5;
    }

    private function next(UserEventInterface $event, EventQueueInterface $queue)
    {
        $this->mode = self::MODE_ON;
        $this->firstHintTime = null;
        $this->secondHintTime = null;
        $this->doneTime = null;
        $this->nextTime = null;
        $this->hint = '';
        $this->ask($event, $queue);
    }

    private function stop(UserEventInterface $event, EventQueueInterface $queue)
    {
        $this->mode = self::MODE_OFF;
        $queue->ircPrivmsg($this->config['channel'], 'Stopping Trivia');
    }

    private function ask(UserEventInterface $event, EventQueueInterface $queue)
    {
        $this->mode = self::MODE_ASKING;
        $this->question = Question::inRandomOrder()->first();
        $queue->ircPrivmsg($this->config['channel'], 'Here comes another question');
        $queue->ircPrivmsg($this->config['channel'], $this->question['question']);

        $this->mode = self::MODE_WAITING;
        echo 'WE ARE NOW WAITING';
        $this->firstHintTime = time() + 30;
        $this->secondHintTime = time() + 60;
        $this->doneTime = time() + 90;
        $this->points = 3;
    }
}
