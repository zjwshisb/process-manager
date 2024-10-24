<?php

declare(strict_types=1);

namespace Zjwshisb\ProcessManager\Tests;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Zjwshisb\ProcessManager\Manager;

class ManagerTest extends TestCase
{
    public function testDefaultLogger(): void
    {
        $manager = new Manager;
        $manager->setLogger();
        $this->assertTrue($manager->getLogger() instanceof Logger);
    }
}
