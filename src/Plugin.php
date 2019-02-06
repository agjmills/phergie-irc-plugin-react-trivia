<?php

namespace Asdfx\Phergie\Plugin\Trivia;

use Asdfx\Phergie\Plugin\Trivia\Models\User;
use Asdfx\Phergie\Plugin\Trivia\Models\Question;
use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Event\UserEventInterface;
use Phergie\Irc\Bot\React\EventQueueInterface;

class Plugin extends AbstractPlugin
{

    const MODE_OFF = 0;
    const MODE_ON = 1;
    const MODE_ASKING = 2;
    const MODE_WAITING = 3;

    private $firstHintTime;
    private $secondHintTime;
    private $doneTime;
    private $nextTime;
    private $hint;
    private $question;
    private $points;
    private $config;
    private $database;
    private $mode = 0;

    public function __construct(array $configuration = [])
    {
        $this->config = $configuration;
        $this->database = new Providers\Database('sqlite', '', 'trivia.db');
    }

    public function getSubscribedEvents()
    {
        return [
            'irc.tick' => 'onTick',
            'irc.received.privmsg' => 'onPrivmsg',
        ];
    }

    private function start(UserEventInterface $event, EventQueueInterface $queue)
    {
        $this->mode = self::MODE_ON;
        $queue->ircPrivmsg($this->config['channel'], 'I want to play some trivia, don\'t you?!');
        $this->ask($event, $queue);
    }

    public function onPrivmsg(UserEventInterface $event, EventQueueInterface $queue)
    {
        $nick = $event->getNick();
        $eventParams = $event->getParams();
        switch ($eventParams[0]) {
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
                if (strtolower($event->getParams()[0]) == strtolower($this->question['answer'])) {
                    $this->correct($event, $queue);
                } else {
                }
                break;
        }
    }


    public function onTick(UserEventInterface $event, EventQueueInterface $queue)
    {
        $time = time();
        if ($this->mode == self::MODE_WAITING && !is_null($this->firstHintTime) && $time >= $this->firstHintTime) {
            $this->hint = $this->getHint($this->question['answer']);
            $queue->ircPrivmsg($this->config['channel'], $this->hint);
            $this->firstHintTime = null;
            $this->points = ceil($this->points / 2);
        } else if ($this->mode == self::MODE_WAITING && !is_null($this->secondHintTime) && $time >= $this->secondHintTime) {
            $queue->ircPrivmsg($this->config['channel'], $this->getHint($this->question['answer'], $this->hint));
            $this->secondHintTime = null;
            $this->points = ceil($this->points / 2);
        } else if ($this->mode == self::MODE_WAITING && !is_null($this->doneTime) && $time >= $this->doneTime) {
            $queue->ircPrivmsg($this->config['channel'], 'Times up!');
            $this->doneTime = null;
            $this->missed($event, $queue);
        } else if ($this->mode == self::MODE_WAITING && !is_null($this->nextTime) && $time >= $this->nextTime) {
            $this->nextTime = null;
            $this->next($event, $queue);
        }
    }

    private function correct(UserEventInterface $event, EventQueueInterface $queue)
    {
        $nick = $event->getNick();
        $queue->ircPrivmsg($this->config['channel'], 'Correct! ' . $nick . ' gets ' . $this->points . ' points');

        $user = User::where('nick', $nick)->first();
        if ($user === null) {
            $user = User::create(['nick' => $nick, 'points' => 0]);
        }

        $user->points = $user->points + $this->points;

        $user->save();
        $this->next();
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

        $this->firstHintTime = time() + $this->config['time_limit_first_hint'];
        $this->secondHintTime = time() + $this->config['time_limit_first_hint'] + $this->config['time_limit_second_hint'];
        $this->doneTime = time() + $this->config['time_limit_first_hint'] + $this->config['time_limit_second_hint'] + $this->config['time_limit_done'];

        $this->points = 3;
    }
}
