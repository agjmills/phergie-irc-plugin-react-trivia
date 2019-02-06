<?php

namespace Asdfx\Phergie\Plugin\Trivia;

use Asdfx\Phergie\Plugin\Trivia\Models\User;
use Asdfx\Phergie\Plugin\Trivia\Models\Question;
use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Event\UserEventInterface;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;
use Phergie\Irc\Event\UserEvent;

class Plugin extends AbstractPlugin {

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

    public function __construct(array $configuration = []) {
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

    private function start()
    {
        $this->mode = self::MODE_ON;
        $this->doPrivMsg($this->config['channel'], 'I want to play some trivia, don\'t you?!');
        $this->ask();
    }

    public function onPrivmsg(UserEvent $event, Queue $queue)
    {
        $nick = $event->getNick();
        $eventParams = $event->getParams();
        switch($eventParams[0]) {
            case ".start":
                if ($this->mode == self::MODE_OFF) {
                    $this->start();
                } else {
                    $this->doPrivMsg($this->config['channel'], 'I\'m sorry Dave, I can\'t do that right now.');
                }
                break;
            case '.stop':
                if ($this->mode != self::MODE_OFF) {
                    $this->stop();
                } else {
                    $this->doPrivMsg($this->config['channel'], 'I\'m sorry Dave, I can\'t do that right now.');
                }
                break;
            default:
                if (strtolower($event->getParams()[0]) == strtolower($this->question['answer'])) {
                    $this->correct($event);
                } else {
                }
                break;
        } 
    }


    public function onTick()
    {
        $time = time();
        if ($this->mode == self::MODE_WAITING && !is_null($this->firstHintTime) && $time >= $this->firstHintTime) {
            $this->hint = $this->getHint($this->question['answer']);
            $this->doPrivMsg($this->config['channel'], $this->hint);
            $this->firstHintTime = null;
            $this->points = ceil($this->points / 2);
        } else if ($this->mode == self::MODE_WAITING && !is_null($this->secondHintTime) && $time >= $this->secondHintTime) {
            $this->doPrivMsg($this->config['channel'], $this->getHint($this->question['answer'], $this->hint));
            $this->secondHintTime = null;
            $this->points = ceil($this->points / 2);
        } else if ($this->mode == self::MODE_WAITING && !is_null($this->doneTime) && $time >= $this->doneTime) {
            $this->doPrivMsg($this->config['channel'], 'Times up!');
            $this->doneTime = null;
            $this->missed();
        } else if ($this->mode == self::MODE_WAITING && !is_null($this->nextTime) && $time >= $this->nextTime) {
            $this->nextTime = null;
            $this->next();
        }
    }

    private function correct(UserEventInterface $event) {
        $nick = $event->getNick();
        $this->doPrivMsg($this->config['channel'], 'Correct! ' . $nick . ' gets ' . $this->points . ' points');

        $user = User::where('nick', $nick)->first();
        if ($user === null) {
            $user = User::create(['nick' => $nick, 'points' => 0]);
        }

        $user->points = $user->points + $this->points;

        $user->save();
        $this->next();
    }
    
    private function missed() {
        $this->doPrivMsg($this->config['channel'], 'Were you sleeping? Sit here and study the correct answer.');
        $this->doPrivMsg($this->config['channel'], 'Answer: ' . $this->question['answer']);
        $this->nextTime = time() + 5;
    }

    private function next() {
        $this->mode = self::MODE_ON;
        $this->firstHintTime = null;
	$this->secondHintTime = null;
        $this->doneTime = null;
        $this->nextTime = null;
        $this->hint = '';
        $this->ask();  
    }

    private function stop() {
        $this->mode = self::MODE_OFF;
        $this->doPrivMsg($this->config['channel'], 'Stopping Trivia');
    }

    private function ask() {
        $this->mode = self::MODE_ASKING;
        $this->question = Question::inRandomOrder()->first();
        $this->doPrivMsg($this->config['channel'], 'Here comes another question');
        $this->doPrivMsg($this->config['channel'], $this->question['question']);
        
        $this->mode = self::MODE_WAITING;

        $this->firstHintTime = time() + $this->config['time_limit_first_hint'];
        $this->secondHintTime = time() + $this->config['time_limit_first_hint'] + $this->config['time_limit_second_hint'];
        $this->doneTime = time() + $this->config['time_limit_first_hint'] + $this->config['time_limit_second_hint'] + $this->config['time_limit_done'];

        $this->doPrivMsg($this->config['channel']);
        $this->points = 3;
    }

    private function doPrivMsg(EventQueueInterface $queue, $target, $message)
    {
        $queue->ircPrivmsg($target, $message);
    }
}

