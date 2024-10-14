<?php
namespace Zjwshisb\ProcessManager\Traits;

trait HasUuid {
    protected ?string $uuid = null;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(): void
    {
        if (is_null($this->uuid)) {
            $this->uuid = uniqid();
        }
    }
}