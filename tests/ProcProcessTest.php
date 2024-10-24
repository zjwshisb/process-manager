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
        $manager->spawnCmd(['echo', '1'])->setRunTimes(10)
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
        $manager->spawnCmd(['echo', '1'])->setRunTimes(10)->setProcessCount(10)
            ->onSuccess(function (ProcessInterface $process) use (&$count) {
                $count += intval($process->getOutput());
            });
        $manager->start();
        $this->assertEquals(100, $count);
    }

    public function testError(): void
    {
        $manager = new Manager;
        $manager->spawnCmd(['ls', 'error'])
            ->onError(function (ProcessInterface $process) {
                $this->assertTrue(! $process->isSuccessful());
            })->onSuccess(function () {
                $this->fail();
            });
        $manager->start();
    }

    public function testGetErrorOutput()
    {
        $manager = new Manager;
        $manager->spawnCmd(['ls', 'error'])->onSuccess(function () {
            $this->fail();
        })->onError(function (ProcessInterface $process) {
            $this->assertTrue((bool) $process->getErrorOutput());
        });
        $manager->start();
    }

    public function testGetSuccessOutput()
    {
        $manager = new Manager;
        $manager->spawnCmd(['echo', 'hello world'])->onSuccess(function (ProcessInterface $process) {
            $this->assertEquals('hello world', trim($process->getOutput()));
        })->onError(function () {
            $this->fail();
        });
        $manager->start();
    }

    public function testSuccess(): void
    {
        $manager = new Manager;
        $manager->spawnCmd(['echo', 'hello world'])->onSuccess(function (ProcessInterface $process) {
            $this->assertTrue($process->isSuccessful());
        })->onError(function () {
            $this->fail();
        });
        $manager->start();
    }

    public function testTimeout(): void
    {
        $manager = new Manager;
        $manager->spawnCmd(['sleep', '7'])
            ->setTimeout(6)->onSuccess(function (ProcessInterface $process) {
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
        $manager->spawnCmd(['pwd'])->onSuccess(function (ProcessInterface $process) {
            $this->assertTrue($process->getPid() > 0);
        });
        $manager->start();
    }

    public function testGetDurationTime()
    {
        $manager = new Manager;
        $manager->spawnCmd(['sleep', '5'])->onSuccess(function (ProcessInterface $process) {
            $this->assertTrue($process->getDurationTime() >= 5);
        });
        $manager->start();
    }

    public function testGetInfo(): void
    {
        $manager = new Manager;
        $manager->spawnCmd(['pwd'])->onSuccess(function (ProcessInterface $process) {
            $this->assertEquals('proc', $process->getInfo()['type']);
        });
        $manager->start();
    }
}
