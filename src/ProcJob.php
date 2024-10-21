<?php

declare(strict_types=1);

namespace Zjwshisb\ProcessManager;

/**
 * @method $this setEnv(array $env)
 * @method $this setInput($input)
 * @method $this setWorkingDirectory(string $cwd)
 */
class ProcJob extends Job
{
    protected function allowMethods(): array
    {
        return array_merge(parent::allowMethods(), [
            "setEnv", "setInput", "setWorkingDirectory"
        ]);
    }

}
