<?php

namespace Zjwshisb\ProcessManager\Process\Traits;

use Closure;
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

    /**
     * @return static
     */
    public function triggerSuccessEvent(): static
    {
        $callbacks = $this->getEventCallbacks("success");
        if (sizeof($callbacks) > 0) {
            foreach ($callbacks as $event) {
                call_user_func($event, $this, $this->getOutput());
            }
        }
        return $this;
    }

    /**
     * @return static
     */
    public function triggerErrorEvent(): static
    {
        $callbacks = $this->getEventCallbacks("error");
        if (sizeof($callbacks) > 0) {
            foreach ($callbacks as $event) {
                call_user_func($event, $this, $this->getOutput());
            }
        }
        return $this;
    }

    /**
     * @return static
     */
    public function triggerTimeoutEvent(): static
    {
        $callbacks = $this->getEventCallbacks("timeout");
        if (sizeof($callbacks) > 0) {
            foreach ($callbacks as $event) {
                call_user_func($event, $this);
            }
        }
        return $this;
    }

    /**
     * @param callable $callback
     * @return static
     */
    public function onSuccess(callable $callback): static
    {
        return $this->addEvent("success", $callback);
    }

    /**
     * @param callable $callback
     * @return static
     */
    public function onTimeout(callable $callback): static
    {
        return $this->addEvent("timeout", $callback);
    }

    /**
     * @param callable $callback
     * @return static
     */
    public function onError(callable $callback): static
    {
        return $this->addEvent("error", $callback);
    }


    /**
     * @param string $event
     * @return array<callable|callable-string>
     */
    private function getEventCallbacks(string $event): array
    {
        return $this->events[$event] ?? [];
    }

    /**
     * @param string $event
     * @param callable $callback
     * @return static
     */
    private function addEvent(string $event, callable $callback): static
    {
        if (!isset($this->events[$event])) {
            $this->events[$event] = [];
        }
        $this->events[$event][] = $callback;
        return $this;
    }
}
