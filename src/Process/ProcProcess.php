<?php

namespace Zjwshisb\ProcessManager\Process;

use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Zjwshisb\ProcessManager\Exception\ProcessTimedOutException;
use Zjwshisb\ProcessManager\Traits\HasUid;
use Zjwshisb\ProcessManager\Traits\WithEndTime;
use Zjwshisb\ProcessManager\Traits\Repeatable;

class ProcProcess extends SymfonyProcess implements ProcessInterface
{

    use WithEndTime, Repeatable, HasUid, WithEndTime;

    public function __construct(array $command, ?string $cwd = null, ?array $env = null, mixed $input = null, ?float $timeout = 60)
    {
        parent::__construct($command, $cwd, $env, $input, $timeout);
        $this->setUuid();
    }


    public function getInfo($withExit = false): array
    {
        $info = [
            "cmd" => $this->getCommandLine(),
            "type" => "proc",
            "pid" => $this->getPid(),
        ];
        if ($withExit) {
            $info["exit code"] = $this->getExitCode();
            $info['exit text'] = $this->getExitCodeText();
        }
        return $info;
    }


    public function start(?callable $callback = null, array $env = []): void
    {
        parent::start();
        $this->addRunCount();
    }

    protected function updateStatus(bool $blocking): void
    {
        parent::updateStatus($blocking);
        if ($this->isRunning()) {
            $this->updateEndTime();
        }
    }

    public function checkTimeout(): void
    {
        try {
            parent::checkTimeout();
        } catch (SymfonyProcessTimedOutException $exception) {
            throw new ProcessTimedOutException($exception->getProcess(),
                $exception->isGeneralTimeout() ?
                    SymfonyProcessTimedOutException::TYPE_GENERAL :
                    SymfonyProcessTimedOutException::TYPE_IDLE
            );
        }

    }

}