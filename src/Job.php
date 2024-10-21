<?php

declare(strict_types=1);

namespace Zjwshisb\ProcessManager;

use ArrayIterator;
use BadMethodCallException;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;
use Zjwshisb\ProcessManager\Process\ProcessInterface;

/**
 * @method $this setTimeout(float $float)
 * @method $this setRunTimes(int $runTimes)
 * @method $this onSuccess(callable $callback)
 * @method $this onTimeout(callable $callback)
 * @method $this onError(callable $callback)
 * @template-implements  IteratorAggregate<int, ProcessInterface>
 * /
 */
class Job implements IteratorAggregate
{
    /**
     * @var array<int, ProcessInterface>|null
     */
    protected array|null $processes = null;

    protected int $processCount = 1;

    public function __construct(protected ProcessInterface $process)
    {

    }

    /**
     * @param int<0, 99999> $count
     * @return $this
     */
    public function setProcessCount(int $count): static
    {
        if ($count <= 0) {
            throw new InvalidArgumentException('Process Count must be greater than 0');
        }
        $this->processCount = $count;
        return $this;
    }


    /**
     * @return string[]
     */
    protected function allowMethods(): array
    {
        return [
            "setTimeout",
            "setRunTimes",
            "onSuccess",
            "onError",
            "onTimeout"
        ];
    }

    /**
     * @return Traversable<int, ProcessInterface>
     */
    public function getIterator(): Traversable
    {
        if ($this->processes === null || sizeof($this->processes) !== $this->processCount) {
            $processes = [];
            for ($i = 1; $i <= $this->processCount; $i++) {
                $p = clone $this->process;
                $p->setUid();
                $processes[] = $p;
            }
            $this->processes = $processes;
        }
        return new ArrayIterator($this->processes);
    }

    /**
     * @param string $name
     * @param array<string> $arguments
     * @return $this
     */
    public function __call(string $name, array $arguments): static
    {
        if (in_array($name, $this->allowMethods())) {
            $this->process->$name(...$arguments);
            return $this;
        }
        throw new BadMethodCallException("Call to undefined method " . __CLASS__ . "::" . $name . "()");
    }
}
