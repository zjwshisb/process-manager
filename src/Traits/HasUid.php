<?php
namespace Zjwshisb\ProcessManager\Traits;

trait HasUid {
    protected ?string $uid = null;

    public function getUid(): string
    {
        return $this->uid;
    }

    public function setUuid(): void
    {
        if (is_null($this->uid)) {
            $this->uid = uniqid();
        }
    }
}