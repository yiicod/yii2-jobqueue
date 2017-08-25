<?php

namespace yiicod\jobqueue\commands;

use Illuminate\Queue\WorkerOptions;
use Yii;
use yiicod\cron\commands\DaemonController;
use yiicod\jobqueue\failed\MongoFailedJobProvider;
use yiicod\jobqueue\handlers\ExceptionHandler;
use yiicod\jobqueue\Worker;

/**
 * Command to start worker
 *
 * @author Virchenko Maksim <muslim1992@gmail.com>
 */
class WorkerCommand extends DaemonController
{
    /**
     * Delay before getting jobs
     *
     * @var int
     */
    public $delay = 0;

    /**
     * Maximum memory usage
     *
     * @var int
     */
    public $memory = 128;

    /**
     * Sleep before getting new jobs
     *
     * @var int
     */
    public $sleep = 3;

    /**
     * Max tries to run job
     *
     * @var int
     */
    public $maxTries = 1;

    /**
     * Daemon timeout
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Queue name
     *
     * @var string
     */
    public $queue = 'default';

    /**
     * Connection name
     *
     * @var string
     */
    public $connection = 'default';

    /**
     * Available options
     *
     * @param string $actionId
     *
     * @return array
     */
    public function options($actionId)
    {
        return ['id', 'queue', 'connection'];
    }

    /**
     * Process job by id and connection
     */
    public function actionProcess()
    {
        $this->processJob(
            $this->connection, $this->id
        );
    }

    /**
     * Get daemon name
     *
     * @return string
     */
    protected function daemonName(): string
    {
        return 'jobqueue-' . $this->queue . '-' . $this->connection;
    }

    /**
     * Run queue worker
     *
     * @author Virchenko Maksim <muslim1992@gmail.com>
     */
    protected function worker()
    {
        $queueManager = Yii::$app->jobqueue->getQueueManager();

        $worker = new Worker($queueManager, new MongoFailedJobProvider(Yii::$app->mongodb, 'yii_jobs_failed'), new ExceptionHandler());
        $worker->daemon($this->connection, $this->queue, new WorkerOptions($this->delay, $this->memory, $this->timeout, $this->sleep, $this->maxTries));
    }

    /**
     * Process the job
     *
     * @param $connectionName
     * @param $id
     *
     * @return array|null
     */
    protected function processJob($connectionName, $id)
    {
        // automatically send every new message to available log routes
        Yii::getLogger()->flushInterval = 1;
        //manager
        $queueManager = Yii::$app->jobqueue->getQueueManager();
        //worker
        $worker = new Worker($queueManager, new MongoFailedJobProvider(Yii::$app->mongodb, 'yii_jobs_failed'), new ExceptionHandler());

        $worker->runJobById($connectionName, $id, new WorkerOptions(
            $this->delay,
            $this->memory,
            $this->timeout,
            $this->sleep,
            $this->maxTries
        ));
    }
}
