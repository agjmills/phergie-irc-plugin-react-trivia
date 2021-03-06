# Trivia plugin for [Phergie](http://github.com/phergie/phergie-irc-bot-react/)

[Phergie](http://github.com/phergie/phergie-irc-bot-react/) plugin for playing a trivia game.

## About
This plugin provides a really simple Trivia game, the bot asks a question, if you answer it correctly, you get points!

## Usage

### Start the game
`
.start
`

### Stop the game
`
.stop
`

### Get your current score
`
.score
`

## Install

The recommended method of installation is [through composer](http://getcomposer.org).

```JSON
composer require asdfx/phergie-irc-plugin-react-trivia
```

See Phergie documentation for more information on
[installing and enabling plugins](https://github.com/phergie/phergie-irc-bot-react/wiki/Usage#plugins).

## Configuration

This plugin requires the [Command plugin](https://github.com/phergie/phergie-irc-plugin-react-command) to recognise commands.

If you're new to Phergie or Phergie plugins, see the [Phergie setup instructions](https://github.com/phergie/phergie-irc-bot-react/wiki/Usage#configuration)
for more information.  Otherwise, add the following references to your config file:

```php
return [
    // ...
    'plugins' => [
        new \Phergie\Irc\Plugin\React\Command\Plugin,   // dependency
	new \Asdfx\Phergie\Plugin\Trivia\Plugin(['channel' => '#yourChannel']),
    ]
]
```

## License

Released under the MIT License. See `LICENSE`.
