<?php

namespace Asdfx\Phergie\Plugin\Trivia\Models;

class Message
{
    /**
     * @return string
     */
    public static function getStart()
    {
        $messages = array(
            "What is up homies!?",
            "I'M ALIVE!",
            "Let's pley shall we?",
            "This week on \"Are You Smarter Than A PHP IRC Bot?\", we have... YOU!",
            "I want to play some trivia, don't you?!",
        );

        return "\x039" . $messages[array_rand($messages)] . "\x15";
    }

    /**
     * @return string
     */
    public static function getNoCanDo()
    {
        $messages = array(
            "I'm sorry Dave, I can't do that right now.",
            "Did you read the manual?",
            "Ya, ummm, no.  Not gonna happen.",
        );

        return "\x034" . $messages[array_rand($messages)] . "\x15";
    }

    /**
     * @return string
     */
    public static function getWrong()
    {
        $messages = array(
            "Wow you suck at this. Sit here and study the correct answer.",
            "Were you sleeping? Sit here and study the correct answer.",
            "How did you not know that?",
            "I thought you were supposed to be smart!",
            "Well, at least I knew the answer to that",
        );

        return "\x034" . $messages[array_rand($messages)] . "\x15";
    }

    /**
     * @return string
     */
    public static function getStop()
    {
        $messages = array(
            "Fine give up, see if I care!",
            "What a sore loser.",
            "Oh, I see the only way for you to win is to kill me?",
            "OK, fine I get you're point, I'm stopping!",
        );

        return "\x034" . $messages[array_rand($messages)] . "\x15";
    }

    /**
     * @return string
     */
    public static function getAsk()
    {
        $messages = array(
            "Here Comes Another Question",
        );

        return "\x0311" . $messages[array_rand($messages)] . "\x15";
    }

    /**
     * @param $n Nick
     * @param $p Points
     * @return string
     */
    public static function getCorrect($n, $p)
    {
        $messages = array(
            "Will someone get $n a prize?  They just got a question right! How about $p points?",
            "About time $n. You get a measly $p points.",
            "Took you long enough $n! Here's $p pity points.",
        );

        return "\x033" . $messages[array_rand($messages)] . "\x15";
    }

    /**
     * @param $x Time in seconds until the next question
     * @return string
     */
    public static function getTimeLimitAnswer($x)
    {
        $messages = array(
            "You have $x seconds before you must answer.",
        );

        return "\x0311" . $messages[array_rand($messages)] . "\x15";
    }

    /**
     * @param $n nick
     * @param $points the number of points
     * @return mixed
     */
    public static function getScore($n, $points) {
        $messages = array(
            "$n has $points points!",
        );

        return $messages[array_rand($messages)];
    }
}