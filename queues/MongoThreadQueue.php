<?php

namespace yiicod\jobqueue\queues;

use Illuminate\Queue\Jobs\Job;
use Symfony\Component\Process\Process;
use Yii;
use yii\mongodb\Connection;
use yiicod\jobqueue\jobs\MongoJob;

/**
 * Class AsyncMongoQueue
 *
 * @package yiicod\jobqueue\queues
 */
class MongoThreadQueue extends MongoQueue
{
    /**
     * @var string
     */
    protected $binary;

    /**
     * @var string
     */
    protected $binaryArgs;

    /**
     * @var int
     */
    protected $limit = 15;

    /**
     * @var string
     */
    protected $yiiAlias = '@app/..';

    /**
     * @var string
     */
    protected $connectionName;

    /**
     * Create a new database queue instance.
     *
     * @param Connection $database
     * @param string $table
     * @param string $default
     * @param int $expire
     * @param int $limit
     * @param $yiiAlias
     * @param string $binary
     * @param string|array $binaryArgs
     * @param string $connectionName
     */
    public function __construct(Connection $database, $table, $default = 'default', $expire = 60, $limit = 15, $yiiAlias, $binary = 'php', $binaryArgs = '', $connectionName = '')
    {
        parent::__construct($database, $table, $default, $expire);
        $this->limit = $limit;
        $this->binary = $binary;
        $this->binaryArgs = $binaryArgs;
        $this->connectionName = $connectionName;
        $this->yiiAlias = $yiiAlias;
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string $queue
     *
     * @return null|bool
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);

        if ($job = $this->getNextAvailableJob($queue)) {
            $this->startProcess($job);

            return true;
        }

        return null;
    }

    /**
     * Check if can run process depend on limits
     *
     * @param MongoJob $job
     *
     * @return bool
     */
    protected function canRunJob(MongoJob $job)
    {
        return $this->getCollection()->count(['reserved' => 1]) < $this->limit || $job->reserved();
    }

    /**
     * Get the next available job for the queue.
     *
     * @param $id
     *
     * @return null|MongoJob
     */
    public function getJobById($id)
    {
        $job = $this->getCollection()->findOne(['_id' => new \MongoDB\BSON\ObjectID($id)]);

        if (is_null($job)) {
            return $job;
        } else {
            $job = (object)$job;

            return new MongoJob($this->container, $this, $job, $job->queue);
        }
    }

    /**
     * Make a Process for the Artisan command for the job id.
     *
     * @param MongoJob $job
     */
    public function startProcess(MongoJob $job)
    {
        if ($this->canRunJob($job)) {
            $this->markJobAsReserved($job);

            $command = $this->getCommand($job);
            $cwd = $this->getYiiPath();

            $process = new Process($command, $cwd);
            $process->run();
        } else {
            sleep(1);
        }
    }

    /**
     * Get the Artisan command as a string for the job id.
     *
     * @param MongoJob $job
     *
     * @return string
     */
    protected function getCommand(MongoJob $job): string
    {
        $connection = $this->connectionName;
        $cmd = '%s yii job-queue/process --id=%s --connection=%s --queue=%s';
        $cmd = $this->getBackgroundCommand($cmd);

        $binary = $this->getPhpBinary();

        return sprintf($cmd, $binary, (string)$job->getJobId(), $connection, $job->getQueue());
    }

    /**
     * Get the escaped PHP Binary from the configuration
     *
     * @return string
     */
    protected function getPhpBinary()
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

    /**
     * @return mixed
     */
    protected function getYiiPath()
    {
        return Yii::getAlias($this->yiiAlias);
    }

    /**
     * @param $cmd
     *
     * @return string
     */
    protected function getBackgroundCommand($cmd)
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            return 'start /B ' . $cmd . ' > NUL';
        } else {
            return $cmd . ' > /dev/null 2>&1 &';
        }
    }
}
