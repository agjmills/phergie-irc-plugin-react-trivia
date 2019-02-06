<?php

namespace Asdfx\Phergie\Plugin\Trivia\Providers;

use Illuminate\Database\Capsule\Manager as Capsule;

class Database {

    public function __construct($driver = '', $host = 'localhost', $database = '', $username = 'root', $password = 'password')
    {
        $capsule = new Capsule;
        $capsule->addConnection([
            'driver' => $driver,
            'host' => $host,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

}
