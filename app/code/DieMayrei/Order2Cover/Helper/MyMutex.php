<?php

namespace DieMayrei\Order2Cover\Helper;

class MyMutex
{
    private int $timeout = 16;

    /**
     * @var resource
     */
    private $fileHandle;

    public function __construct(string $fileName)
    {
        $this->fileHandle = fopen($fileName, 'r');
    }

    private function lockBusy(): void
    {
        $deadline = microtime(true) + $this->timeout;

        while (microtime(true) < $deadline) {
            if ($this->acquireNonBlockingLock()) {
                return;
            }

            usleep(65000);
        }

        throw new \Exception('Timeout');
    }

    private function acquireNonBlockingLock(): bool
    {
        if (!flock($this->fileHandle, LOCK_EX | LOCK_NB, $wouldBlock)) {
            if ($wouldBlock) {
                return false;
            }

            throw new \Exception('Failed to lock the file.');
        }

        return true;
    }

    protected function lock(): void
    {
        $this->lockBusy();
    }

    protected function unlock(): void
    {
        if (!flock($this->fileHandle, LOCK_UN)) {
            throw new \Exception('Failed to unlock the file.');
        }
    }

    public function synchronized(callable $code)
    {
        try {
            $this->lock();
        } catch (\Exception $err) {
            return;
        }

        try {
            $result = $code();
            $this->unlock();

            return $result;
        } catch (\Throwable $exception) {
            $this->unlock();
            throw $exception;
        }
    }
}
