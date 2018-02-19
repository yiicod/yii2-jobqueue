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
class JobQueueCommand extends DaemonController
{
    use WorkerTrait;

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
}
