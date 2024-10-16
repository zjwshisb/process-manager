<?php
namespace Zjwshisb\ProcessManager\Process\Traits;

use Zjwshisb\ProcessManager\Util\Str;

trait HasUid {
    protected ?string $uid = null;

    public function getUid(): string
    {
        return $this->uid;
    }

    public function setUid(): void
    {
        if (is_null($this->uid)) {
            $this->uid =  Str::random();
        }
    }


}