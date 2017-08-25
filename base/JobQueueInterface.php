<?php

namespace yiicod\jobqueue\base;

use Illuminate\Queue\Jobs\Job;

/**
 * Base interface for handlers
 *
 * @author Virchenko Maksim <muslim1992@gmail.com>
 */
interface JobQueueInterface
{
    /**
     * Run command from queue
     *
     * @param Job $job
     * @param array $data
     */
    public function fire(Job $job, array $data);
}
