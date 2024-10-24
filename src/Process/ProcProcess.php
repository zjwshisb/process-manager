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
    use Event;
    use HasUid;
    use Repeatable;
    use WithEndTime;

    /**
     * @return array{
     *     cmd: string,
     *     type: string,
     *     pid: int|null,
     *     exitCode: int|null,
     *     exitText: string|null
     * }| array{
     *     cmd: string,
     *     type: string,
     *     pid: int|null,
     * }
     */
    public function getInfo(bool $withExit = false): array
    {
        $info = [
            'cmd' => $this->getCommandLine(),
            'type' => 'proc',
            'pid' => $this->getPid(),
        ];
        if ($withExit) {
            $info['exitCode'] = $this->getExitCode();
            $info['exitText'] = $this->getExitCodeText();
        }

        return $info;
    }

    /**
     * @param  array<string>  $env
     */
    public function start(?callable $callback = null, array $env = []): void
    {
        parent::start($callback, $env);
        $this->addRunTimes();
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
