<?php

declare(strict_types=1);

namespace Zjwshisb\ProcessManager\Tests;

use PHPUnit\Framework\TestCase;
use Zjwshisb\ProcessManager\Manager;
use Zjwshisb\ProcessManager\Process\PcntlProcess;

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
            ->onSuccess(function (PcntlProcess $process) use (&$count) {
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
            ->onSuccess(function (PcntlProcess $process) use (&$count) {
                $count += $process->getOutput();
            });
        $manager->start();
        $this->assertEquals(10, $count);
    }

    public function testMultipleProcessCount(): void
    {
        $manager = new Manager;
        $count = 0;
        $manager->spawnPhp(function () {
            return 1;
        })->setRunTimes(10)->setProcessCount(10)
            ->onSuccess(function (PcntlProcess $process) use (&$count) {
                $count += $process->getOutput();
            });
        $manager->start();
        $this->assertEquals(100, $count);
    }

    public function testError(): void
    {
        $manager = new Manager;
        $manager->spawnPhp(function () {
            throw new \RuntimeException('test');
        })->onError(function (PcntlProcess $process) {
            $this->assertNotEmpty($process->getErrorOutput());
        })->onSuccess(function () {
            $this->fail();
        });
        $manager->start();
    }

    public function testSuccess(): void
    {
        $manager = new Manager;
        $manager->spawnPhp(function () {
            return 'test';
        })->onSuccess(function (PcntlProcess $process) {
            $this->assertEquals('test', $process->getOutput());
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
        })->setTimeout(6)->onSuccess(function () {
            $this->fail();
        })->onError(function () {
            $this->fail();
        })->onTimeout(function () {
            $this->assertTrue(true);
        });
        $manager->start();
    }
}
