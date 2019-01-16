<?php

namespace Comparon\MegacronBundle\Helper;

use Comparon\MegacronBundle\Model\TaskConfiguration;
use Cron\CronExpression;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;

class TaskProcessorHelper
{
    /** @var string */
    private $binDirPath;

    /** @var Command */
    private $command;

    /** @var TaskConfiguration */
    private $taskConfig;

    /**
     * @param string            $binDirPath
     * @param Command           $command
     * @param TaskConfiguration $taskConfig
     */
    public function __construct($binDirPath, Command $command, TaskConfiguration $taskConfig)
    {
        $this->binDirPath = $binDirPath;
        $this->command = $command;
        $this->taskConfig = $taskConfig;
    }

    /**
     * @throws \Exception
     */
    public function process(): void
    {
        $pidFileDir = $this->getPidFileDir();
        $this->createDir($pidFileDir);

        $processHash = sha1($this->command->getName() . $this->taskConfig->getCronExpression());
        $pidFilePath = $pidFileDir . $processHash . '.pid';

        if ($this->isDue()) {
            $processCmd = $this->binDirPath . 'console ' . $this->command->getName();
            $processCmdSuffix = ' > /dev/null 2>/dev/null &';

            if (count($this->taskConfig->getParameters()) > 0) {
                $processCmd .= ' ' . implode(' ', $this->taskConfig->getParameters());
            }

            if (file_exists($pidFilePath)) {
                if ($this->taskConfig->isWithOverlapping()) {
                    unlink($pidFilePath);
                } else {
                    $pid = intval(file_get_contents($pidFilePath));
                    $isRunning = posix_kill($pid, 0);
                    if ($isRunning) {
                        return;
                    }
                }
            }

            if (!$this->taskConfig->isWithOverlapping()) {
                file_put_contents($pidFilePath, '');
                $processCmdSuffix .= ' echo $! >> ' . $pidFilePath;
            }

            shell_exec($processCmd . $processCmdSuffix);
        }
    }

    private function getPidFileDir(): string
    {
        return $this->binDirPath . '..'
            . DIRECTORY_SEPARATOR . 'var'
            . DIRECTORY_SEPARATOR . 'megacron'
            . DIRECTORY_SEPARATOR;
    }

    /**
     * @param string $dirPath
     */
    private function createDir(string $dirPath): void
    {
        (new Filesystem())->mkdir($dirPath);
    }

    private function isDue(): bool
    {
        $expression = $this->taskConfig->getCronExpression();
        if (CronExpression::isValidExpression($expression)) {
            $cron = CronExpression::factory($expression);
            return $cron->isDue(new \DateTime('now'));
        }
        // TODO: implement logging
        return false;
    }
}

