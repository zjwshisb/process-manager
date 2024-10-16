<?php
declare(strict_types=1);
namespace Zjwshisb\ProcessManager;

use Traversable;
use Zjwshisb\ProcessManager\Process\ProcessInterface;

/**
 * @method $this setTimeout(float $float)
 * @method $this setRunTimes(int $runTimes)
 * @method $this onSuccess(callable $callback)
 * @method $this onTimeout(callable $callback)
 * @method $this onError(callable $callback)
 */
class Job implements \IteratorAggregate {

    protected array|null $processes = null;

    protected int $processCount= 1;

    public function __construct(protected ProcessInterface $process)
    {

    }

    public function setProcessCount(int $count): static
    {
        $this->processCount = $count;
        return $this;
    }

    public function __call(string $name, array $arguments)
    {
        if (in_array($name, $this->allowMethods())) {
            call_user_func_array([$this->process, $name], $arguments);
            return $this;
        }
        throw new \BadMethodCallException("Call to undefined method " . __CLASS__ . "::" . $name . "()");
    }

    protected function allowMethods() : array
    {
        return [
            "setTimeout",
            "setRunTimes",
            "onSuccess",
            "onError",
            "onTimeout"
        ];
    }

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
        return new \ArrayIterator($this->processes);
    }
}