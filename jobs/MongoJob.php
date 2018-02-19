<?php

namespace yiicod\jobqueue\jobs;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use stdClass;
use yiicod\jobqueue\queues\MongoThreadQueue;

/**
 * MongoJob for laravel queue
 *
 * @author Virchenko Maksim <muslim1992@gmail.com>
 */
class MongoJob extends Job implements JobContract
{
    /**
     * The database queue instance.
     *
     * @var MongoThreadQueue
     */
    protected $database;

    /**
     * The database job payload.
     *
     * @var StdClass
     */
    protected $job;

    /**
     * Create a new job instance.
     *
     * @param  Container $container
     * @param  MongoThreadQueue $database
     * @param  StdClass $job
     * @param  string $queue
     */
    public function __construct(Container $container, MongoThreadQueue $database, $job, $queue)
    {
        $this->job = $job;
        $this->queue = $queue;
        $this->database = $database;
        $this->container = $container;
    }

    /**
     * Delete the job from the queue.
     */
    public function delete()
    {
        parent::delete();

        if ($this->database->deleteReserved($this->queue, (string)$this->getJobId())) {
            $this->deleted = true;
        }
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return (int)$this->job->attempts;
    }

    /**
     * Check if job reserved
     *
     * @return bool
     */
    public function reserved(): bool
    {
        return (bool)$this->job->reserved;
    }

    /**
     * Get reserved at time
     *
     * @return int
     */
    public function reservedAt(): int
    {
        return (int)$this->job->reserved_at;
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId(): string
    {
        return (string)$this->job->_id;
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job->payload;
    }
}
