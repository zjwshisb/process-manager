<?php
namespace Zjwshisb\ProcessManager\Group;

use ArrayIterator;
use Traversable;
use Zjwshisb\ProcessManager\Process\ProcessInterface;

/**
 * @method self setTimeout(float $float)
 * @method self setRepeatable(int|bool $repeatable)
 */
class ProcessGroup implements ProcessGroupInterface
{
    /**
     * @var array<ProcessInterface>
     */
    protected array $processes = [];

    public function getIterator(): Traversable
    {
       return new ArrayIterator($this->processes);
    }

    public function add(ProcessInterface $process): static
    {
        $this->processes[] = $process;
        return $this;
    }

    public function __call(string $name, array $arguments)
    {
        foreach ($this->processes as $process) {
            call_user_func_array([$process, $name], $arguments);
        }
        return $this;
    }
}