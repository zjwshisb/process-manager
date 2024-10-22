<?php

declare(strict_types=1);

namespace Zjwshisb\ProcessManager\Tests;

use PHPUnit\Framework\TestCase;
use Zjwshisb\ProcessManager\Manager;
use Zjwshisb\ProcessManager\Process\ProcessInterface;

class ProcProcessTest extends TestCase
{
    public function testMultipleProcess(): void
    {
        $manager = new Manager;
        $count = 0;
        $manager->spawnCmd(['echo', '1'])
            ->setProcessCount(10)
            ->onSuccess(function (ProcessInterface $process) use (&$count) {
                $count += intval($process->getOutput());
            });
        $manager->start();
        $this->assertEquals(10, $count);
    }

    public function testMultipleCount(): void
    {
        $manager = new Manager;
        $count = 0;
        $manager->spawnCmd(['echo', '1'])
            ->setRunTimes(10)
            ->onSuccess(function (ProcessInterface $process) use (&$count) {
                $count += intval($process->getOutput());
            });
        $manager->start();
        $this->assertEquals(10, $count);
    }

    public function testMultipleProcessCount(): void
    {
        $manager = new Manager;
        $count = 0;
        $manager->spawnCmd(['echo', '1'])
            ->setRunTimes(10)
            ->setProcessCount(10)
            ->onSuccess(function (ProcessInterface $process) use (&$count) {
                $count += intval($process->getOutput());
            });
        $manager->start();
        $this->assertEquals(100, $count);
    }

    public function testSuccess(): void
    {
        $manager = new Manager;
        $manager->spawnCmd(['echo', 'test'])->onSuccess(function (ProcessInterface $process) {
            $this->assertEquals('test', trim($process->getOutput()));
        });
        $manager->start();
    }

    public function testError(): void
    {
        $manager = new Manager;
        $manager->spawnCmd(['ls', 'lldwa'])
            ->onError(function (ProcessInterface $process) {
                $output = $process->getErrorOutput();
                $this->assertTrue((bool) $output);
            });
        $manager->start();
    }

    public function testTimeout(): void
    {
        $manager = new Manager;
        $manager->spawnCmd(['sleep', '10'])->setTimeout(6)->onSuccess(function () {
            $this->fail();
        })->onError(function () {
            $this->fail();
        })->onTimeout(function () {
            $this->assertTrue(true);
        });
        $manager->start();
    }
}
