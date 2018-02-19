<?php

namespace yiicod\jobqueue\commands;

use Illuminate\Queue\WorkerOptions;
use Yii;
use yii\console\Controller;
use yiicod\jobqueue\failed\MongoFailedJobProvider;
use yiicod\jobqueue\handlers\ExceptionHandler;
use yiicod\jobqueue\Worker;

class WorkerCommand extends Controller
{
    use CommandTrait;

    /**
     * @var string
     */
    public $defaultAction = 'work';

    /**
     * @throws \Exception
     */
    public function actionWork()
    {
        $queueManager = Yii::$app->jobqueue->getQueueManager();

        $worker = new Worker($queueManager, new MongoFailedJobProvider(Yii::$app->mongodb, 'yii_jobs_failed'), new ExceptionHandler());
        $worker->daemon($this->connection, $this->queue, new WorkerOptions($this->delay, $this->memory, $this->timeout, $this->sleep, $this->maxTries));
    }

    protected function output($text)
    {
        $this->stdout($text);
    }
}
