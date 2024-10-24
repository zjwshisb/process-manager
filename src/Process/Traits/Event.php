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
    protected array $listeners = [];

    public function triggerSuccessListeners(): static
    {
        return $this->trigger('success');
    }

    public function triggerErrorListeners(): static
    {
        return $this->trigger('error');
    }

    public function triggerTimeoutListeners(): static
    {
        return $this->trigger('timeout');
    }

    public function onSuccess(callable $callback): static
    {
        return $this->attachListener('success', $callback);
    }

    public function onTimeout(callable $callback): static
    {
        return $this->attachListener('timeout', $callback);
    }

    public function onError(callable $callback): static
    {
        return $this->attachListener('error', $callback);
    }

    /**
     * @return array<callable|callable-string>
     */
    private function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }

    private function trigger(string $event): static
    {
        $listeners = $this->getListeners($event);
        if (! empty($listeners) > 0) {
            foreach ($listeners as $listener) {
                call_user_func($listener, $this);
            }
        }

        return $this;
    }

    private function attachListener(string $event, callable $callback): static
    {
        if (! isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = $callback;

        return $this;
    }
}
