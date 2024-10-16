<?php
namespace Zjwshisb\ProcessManager\Process\Traits;

use Zjwshisb\ProcessManager\Process\ProcessInterface;

/**
 * @mixin ProcessInterface
 */
trait Event {
    protected array $events = [];

    public function triggerSuccessEvent(): void
    {
        $callbacks = $this->getEventCallbacks("success");
        if (sizeof($callbacks) > 0) {
            foreach ($callbacks as $event) {
                call_user_func($event, $this, $this->getOutput());
            }
        }
    }

    public function triggerErrorEvent(): void
    {
        $callbacks = $this->getEventCallbacks("error");
        if (sizeof($callbacks) > 0) {
            foreach ($callbacks as $event) {
                call_user_func($event, $this, $this->getOutput());
            }
        }
    }

    public function triggerTimeoutEvent(): void
    {
        $callbacks = $this->getEventCallbacks("timeout");
        if (sizeof($callbacks) > 0) {
            foreach ($callbacks as $event) {
                call_user_func($event, $this);
            }
        }
    }

    public function onSuccess(callable $callback): static
    {
        return $this->addEvent("success", $callback);
    }

    public function onTimeout(callable $callback): static
    {
        return $this->addEvent("timeout", $callback);
    }

    public function onError(callable $callback): static
    {
        return $this->addEvent("error", $callback);
    }


    private function getEventCallbacks(string $event)
    {
        return $this->events[$event] ?? [];
    }

    private function addEvent($event, $callback) : static
    {
        if (!isset($this->events[$event])) {
            $this->events[$event] = [];
        }
        $this->events[$event][] = $callback;
        return $this;
    }
}