<?php

namespace DieMayrei\ProductStock\Console;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

trait ConsoleLoggerTrait
{
    /** @var OutputInterface */
    private $loggerOutput;

    protected function initOutput(?OutputInterface $output): void
    {
        if ($output !== null) {
            $this->loggerOutput = $output;
            return;
        }

        if (!$this->loggerOutput instanceof OutputInterface) {
            $this->loggerOutput = new NullOutput();
        }
    }

    protected function logInfo(string $message): void
    {
        $this->getOutput()->writeln(sprintf('<info>%s</info> %s', $this->getLogPrefix(), $message));
    }

    protected function logWarning(string $message): void
    {
        $this->getOutput()->writeln(sprintf('<comment>%s</comment> %s', $this->getLogPrefix(), $message));
    }

    protected function logError(string $message): void
    {
        $this->getOutput()->writeln(sprintf('<error>%s</error> %s', $this->getLogPrefix(), $message));
    }

    protected function getOutput(): OutputInterface
    {
        if (!$this->loggerOutput instanceof OutputInterface) {
            $this->loggerOutput = new NullOutput();
        }

        return $this->loggerOutput;
    }

    protected function getLogPrefix(): string
    {
        return '[ProductStock]';
    }
}
