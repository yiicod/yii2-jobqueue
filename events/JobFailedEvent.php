<?php

namespace yiicod\jobqueue\events;

use yii\base\Event;

/**
 * Class JobFailedEvent
 * Event on jobs failed
 *
 * @author Virchenko Maksim <muslim1992@gmail.com>
 *
 * @package yiicod\jobqueue\events
 */
class JobFailedEvent extends Event
{
    /**
     * The connection name.
     *
     * @var string
     */
    public $connectionName;

    /**
     * The job instance.
     *
     * @var \Illuminate\Contracts\Queue\Job
     */
    public $job;

    /**
     * The exception that caused the job to fail.
     *
     * @var \Exception
     */
    public $exception;

    /**
     * Create a new event instance.
     *
     * @param string $connectionName
     * @param \Illuminate\Contracts\Queue\Job $job
     * @param \Exception $exception
     * @param array $config
     */
    public function __construct($connectionName, $job, $exception, array $config = [])
    {
        parent::__construct($config);

        $this->job = $job;
        $this->exception = $exception;
        $this->connectionName = $connectionName;
    }
}
