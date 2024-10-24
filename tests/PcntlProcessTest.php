<?php

declare(strict_types=1);

namespace Zjwshisb\ProcessManager\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zjwshisb\ProcessManager\Manager;
use Zjwshisb\ProcessManager\Process\ProcessInterface;

class PcntlProcessTest extends TestCase
{
    public function testMultipleProcess(): void
    {
        $manager = new Manager;
        $count = 0;
        $manager->spawnPhp(function () use (&$resp) {
            return 1;
        })
            ->setProcessCount(10)
            ->onSuccess(function (ProcessInterface $process) use (&$count) {
                $count += $process->getOutput();
            });
        $manager->start();
        $this->assertEquals(10, $count);
    }

    public function testMultipleCount(): void
    {
        $manager = new Manager;
        $count = 0;
        $manager->spawnPhp(function () use (&$resp) {
            return 1;
        })->setRunTimes(10)
            ->onSuccess(function (ProcessInterface $process) use (&$count) {
                $count += $process->getOutput();
            });
        $manager->start();
        $this->assertEquals(10, $count);
    }

    public function testMultipleProcessCount(): void
    {
        $manager = new Manager;
        $manager->setLogger();
        $count = 0;
        $manager->spawnPhp(function () {
            return 1;
        })->setRunTimes(10)->setProcessCount(10)
            ->onSuccess(function (ProcessInterface $process) use (&$count) {
                $count += $process->getOutput();
            });
        $manager->start();
        $this->assertEquals(100, $count);
    }

    public function testError(): void
    {
        $manager = new Manager;
        $manager->spawnPhp(function () {
            throw new RuntimeException('test');
        })->onError(function (ProcessInterface $process) {
            $this->assertTrue(! $process->isSuccessful());
        })->onSuccess(function () {
            $this->fail();
        });
        $manager->start();
    }

    public function testGetErrorOutput()
    {
        $manager = new Manager;
        $manager->spawnPhp(function () {
            throw new LogicException('hello world');
        })->onSuccess(function () {
            $this->fail();
        })->onError(function (ProcessInterface $process) {
            $this->assertEquals('hello world', $process->getErrorOutput());
        });
        $manager->start();
    }

    public function testGetSuccessOutput()
    {
        $manager = new Manager;
        $manager->spawnPhp(function () {
            return 'hello world';
        })->onSuccess(function (ProcessInterface $process) {
            $this->assertEquals('hello world', $process->getOutput());
        })->onError(function () {
            $this->fail();
        });
        $manager->start();
    }

    public function testSuccess(): void
    {
        $manager = new Manager;
        $manager->spawnPhp(function () {
            return 'hello world';
        })->onSuccess(function (ProcessInterface $process) {
            $this->assertTrue($process->isSuccessful());
        })->onError(function () {
            $this->fail();
        });
        $manager->start();
    }

    public function testTimeout(): void
    {
        $manager = new Manager;
        $manager->spawnPhp(function () use (&$resp) {
            sleep(7);
        })->setTimeout(6)->onSuccess(function (ProcessInterface $process) {
            $this->assertEquals(143, $process->getExitCode());
        })->onError(function () {
            $this->fail();
        })->onTimeout(function () {
            $this->assertTrue(true);
        });
        $manager->start();
    }

    public function tetGetPid(): void
    {
        $manager = new Manager;
        $manager->spawnPhp(function () use (&$resp) {
            return 1;
        })->onSuccess(function (ProcessInterface $process) {
            $this->assertTrue($process->getPid() > 0);
        });
        $manager->start();
    }

    public function testGetDurationTime()
    {
        $manager = new Manager;
        $manager->spawnPhp(function () use (&$resp) {
            sleep(5);
        })->onSuccess(function (ProcessInterface $process) {
            $this->assertTrue($process->getDurationTime() >= 5);
        });
        $manager->start();
    }

    public function testGetInfo(): void
    {
        $manager = new Manager;
        $manager->spawnPhp(function () use (&$resp) {
            return 1;
        })->onSuccess(function (ProcessInterface $process) {
            $this->assertEquals('pcntl', $process->getInfo()['type']);
        });
        $manager->start();
    }
}
