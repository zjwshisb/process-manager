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
        $resp = [];
        $manager->spawnPhp(function () use (&$resp) {
            $resp[] = '1';
        })
            ->setProcessCount(10)
            ->onSuccess(function (PcntlProcess $process) use (&$resp) {
                $resp[] = $process->getOutput();
            });
        $manager->start();
        $this->assertCount(10, $resp);
    }

    public function testMultipleCount(): void
    {
        $manager = new Manager;
        $resp = [];
        $manager->spawnPhp(function () use (&$resp) {
            $resp[] = '1';
        })->setRunTimes(10)
            ->onSuccess(function (PcntlProcess $process) use (&$resp) {
                $resp[] = $process->getOutput();
            });
        $manager->start();
        $this->assertCount(10, $resp);
    }

    public function testMultipleProcessCount(): void
    {
        $manager = new Manager;
        $resp = [];
        $manager->spawnPhp(function () use (&$resp) {
            $resp[] = '1';
        })->setRunTimes(10)->setProcessCount(10)
            ->onSuccess(function (PcntlProcess $process) use (&$resp) {
                $resp[] = $process->getOutput();
            });
        $manager->start();
        $this->assertCount(100, $resp);
    }

    public function testError(): void
    {
        $manager = new Manager;
        $manager->spawnPhp(function () use (&$resp) {
            $arr = [];

            return $arr[1];
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
