<?php

declare(strict_types=1);

namespace Zjwshisb\ProcessManager\Process;

use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Zjwshisb\ProcessManager\Exception\ProcessTimedOutException;
use Zjwshisb\ProcessManager\Process\Traits\Event;
use Zjwshisb\ProcessManager\Process\Traits\HasUid;
use Zjwshisb\ProcessManager\Process\Traits\Repeatable;
use Zjwshisb\ProcessManager\Process\Traits\WithEndTime;

class ProcProcess extends SymfonyProcess implements ProcessInterface
{
    use WithEndTime;
    use Repeatable;
    use HasUid;
    use Event;

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

    /**
     * @param callable|null $callback
     * @param array<string> $env
     * @return void
     */
    public function start(?callable $callback = null, array $env = []): void
    {
        parent::start();
        $this->addRunTimes();
    }

    protected function updateStatus(bool $blocking): void
    {
        parent::updateStatus($blocking);
        if (! $this->isRunning()) {
            $this->updateEndTime();
        }
    }

    public function checkTimeout(): void
    {
        try {
            parent::checkTimeout();
        } catch (SymfonyProcessTimedOutException $exception) {
            throw new ProcessTimedOutException(
                $this,
                $exception->isGeneralTimeout() ?
                    SymfonyProcessTimedOutException::TYPE_GENERAL :
                    SymfonyProcessTimedOutException::TYPE_IDLE
            );
        }
    }
}
