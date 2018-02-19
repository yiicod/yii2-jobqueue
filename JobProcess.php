<?php

namespace yiicod\jobqueue;

use Illuminate\Queue\Jobs\Job;
use SfCod\QueueBundle\Job\MongoJob;
use Symfony\Component\Process\Process;
use Yii;

/**
 * Class JobProcess
 *
 * @author Orlov Alexey <aaorlov88@gmail.com@gmail.com>
 *
 * @package SfCod\QueueBundle
 */
class JobProcess
{
    /**
     * @var string
     */
    public $binPath;

    /**
     * @var string
     */
    public $scriptName;

    /**
     * @var string
     */
    public $binary;

    /**
     * @var string
     */
    public $binaryArgs;

    public function __construct(
        string $scriptName = 'yii',
        string $binPath = null,
        string $binary = 'php',
        string $binaryArgs = '')
    {
        $this->scriptName = $scriptName;
        $this->binPath = $binPath;
        $this->binary = $binary;
        $this->binaryArgs = $binaryArgs;
    }

    /**
     * Get the Artisan process for the job id.
     *
     * @param Job|MongoJob $job
     * @param string $connectionName
     *
     * @return Process
     */
    public function getProcess(Job $job, string $connectionName): Process
    {
        $cmd = '%s %s job-queue/process %s --connection=%s --queue=%s';
        $cmd = $this->getBackgroundCommand($cmd);
        $cmd = sprintf($cmd, $this->getPhpBinary(), $this->scriptName, (string)$job->getJobId(), $connectionName, $job->getQueue());

        $alias = '';
        if (is_null($this->binPath)) {
            if (file_exists(Yii::getAlias('@app/yii'))) {
                $alias = '@app';
            } elseif (file_exists(Yii::getAlias('@app/../yii'))) {
                $alias = '@app/../';
            }
        }

        return new Process($cmd, Yii::getAlias($this->binPath ?? $alias));
    }

    /**
     * @param $cmd
     *
     * @return string
     */
    protected function getBackgroundCommand(string $cmd): string
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            return 'start /B ' . $cmd . ' > NUL';
        } else {
            return $cmd . ' > /dev/null 2>&1 &';
        }
    }

    /**
     * Get the escaped PHP Binary from the configuration
     *
     * @return string
     */
    protected function getPhpBinary(): string
    {
        $path = $this->binary;
        if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
            $path = escapeshellarg($path);
        }

        $args = $this->binaryArgs;
        if (is_array($args)) {
            $args = implode(' ', $args);
        }

        return trim($path . ' ' . $args);
    }
}
