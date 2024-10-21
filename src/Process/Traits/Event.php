<?php

namespace Zjwshisb\ProcessManager\Process\Traits;

use Zjwshisb\ProcessManager\Process\ProcessInterface;

/**
 * @mixin ProcessInterface
 */
trait Event
{
    /**
     * @var array<string, array<callable|callable-string>>
     */
    protected array $events = [];

    public function triggerSuccessEvent(): static
    {
        $callbacks = $this->getEventCallbacks('success');
        if (count($callbacks) > 0) {
            foreach ($callbacks as $event) {
                call_user_func($event, $this, $this->getOutput());
            }
        }

        return $this;
    }

    public function triggerErrorEvent(): static
    {
        $callbacks = $this->getEventCallbacks('error');
        if (count($callbacks) > 0) {
            foreach ($callbacks as $event) {
                call_user_func($event, $this, $this->getOutput());
            }
        }

        return $this;
    }

    public function triggerTimeoutEvent(): static
    {
        $callbacks = $this->getEventCallbacks('timeout');
        if (count($callbacks) > 0) {
            foreach ($callbacks as $event) {
                call_user_func($event, $this);
            }
        }

        return $this;
    }

    public function onSuccess(callable $callback): static
    {
        return $this->addEvent('success', $callback);
    }

    public function onTimeout(callable $callback): static
    {
        return $this->addEvent('timeout', $callback);
    }

    public function onError(callable $callback): static
    {
        return $this->addEvent('error', $callback);
    }

    /**
     * @return array<callable|callable-string>
     */
    private function getEventCallbacks(string $event): array
    {
        return $this->events[$event] ?? [];
    }

    private function addEvent(string $event, callable $callback): static
    {
        if (! isset($this->events[$event])) {
            $this->events[$event] = [];
        }
        $this->events[$event][] = $callback;

        return $this;
    }
}
