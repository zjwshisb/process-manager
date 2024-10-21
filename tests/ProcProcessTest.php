<?php

declare(strict_types=1);

namespace Zjwshisb\ProcessManager\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Zjwshisb\ProcessManager\Manager;

class ProcProcessTest extends TestCase
{
    public function testGetOutput(): void
    {
        $manager = new Manager;
        $manager->spawnCmd(['ls'])->onSuccess(function (Process $process) {
            $output = $process->getOutput();
            $this->assertTrue((bool) $output);
        });
        $manager->start();
    }

    public function testMultipleProcess(): void
    {
        $manager = new Manager;
        $resp = [];
        $manager->spawnCmd(['ls'])
            ->setProcessCount(10)
            ->onSuccess(function (Process $process) use (&$resp) {
                $resp[] = $process->getOutput();
            });
        $manager->start();
        $this->assertTrue(count($resp) == 10);
    }

    public function testMultipleCount(): void
    {
        $manager = new Manager;
        $resp = [];
        $manager->spawnCmd(['ls'])
            ->setRunTimes(10)
            ->onSuccess(function (Process $process) use (&$resp) {
                $resp[] = $process->getOutput();
            });
        $manager->start();
        $this->assertTrue(count($resp) == 10);
    }

    public function testGetErrOutput(): void
    {
        $manager = new Manager;
        $manager->spawnCmd(['a'])
            ->onError(function (Process $process) {
                $output = $process->getOutput();
                var_dump($output);
                $this->assertTrue((bool) $output);
            });
        $manager->start();
    }
}
