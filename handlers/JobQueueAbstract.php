<?php

namespace yiicod\jobqueue\handlers;

use Illuminate\Queue\Jobs\Job;
use Yii;
use yiicod\jobqueue\base\JobQueueInterface;

/**
 * Handler for queue jobs
 *
 * @author Virchenko Maksim <muslim1992@gmail.com>
 */
abstract class JobQueueAbstract implements JobQueueInterface
{
    /**
     * Run job with restarting connection
     *
     * @param Job $job
     * @param array $data
     */
    public function fire(Job $job, array $data)
    {
        Yii::$app->db->close();
        Yii::$app->db->open();
    }
}