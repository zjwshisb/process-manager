<?php
require_once "vendor/autoload.php";


$m = new \Zjwshisb\ProcessManager\Manager();

$m->spawnPhp(function () {
  echo "haha". PHP_EOL;
}, 5,  10, 5)
    ->spawnCmd(["ls"], ["timeout" => 5],  10, 5);
$m->start();