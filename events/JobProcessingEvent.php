<?php

namespace yiicod\jobqueue\events;

use yii\base\Event;

/**
 * Class JobProcessingEvent
 * Event before job starts
 *
 * @author Virchenko Maksim <muslim1992@gmail.com>
 *
 * @package yiicod\jobqueue\events
 */
class JobProcessingEvent extends Event
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
     * Create a new event instance.
     *
     * @param string $connectionName
     * @param \Illuminate\Contracts\Queue\Job $job
     * @param array $config
     */
    public function __construct($connectionName, $job, array $config = [])
    {
        parent::__construct($config);

        $this->job = $job;
        $this->connectionName = $connectionName;
    }
}
