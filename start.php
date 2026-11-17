<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use PickaxeMP\Server;

$server = new Server(__DIR__);
$server->start();
