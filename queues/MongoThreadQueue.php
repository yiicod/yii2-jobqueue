<?php

namespace yiicod\jobqueue\queues;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use yii\mongodb\Connection;
use yiicod\jobqueue\jobs\MongoJob;

/**
 * Class MongoThreadQueue
 *
 * @package yiicod\jobqueue\queues
 */
class MongoThreadQueue extends Queue implements QueueContract
{
    /**
     * @var int
     */
    protected $limit = 15;

    /**
     * Create a new database queue instance.
     *
     * @param Connection $database
     * @param string $table
     * @param string $default
     * @param int $expire
     * @param int $limit
     */
    public function __construct(Connection $database, $table, $default = 'default', $expire = 60, $limit = 15)
    {
        $this->table = $table;
        $this->expire = $expire;
        $this->default = $default;
        $this->database = $database;
        $this->limit = $limit;
    }

    /**
     * Check if can run process depend on limits
     *
     * @param MongoJob $job
     *
     * @return bool
     */
    public function canRunJob(MongoJob $job)
    {
        if ($job->getQueue()) {
            return $this->getCollection()->count([
                    'reserved' => 1,
                    'queue' => $job->getQueue(),
                ]) < $this->limit || $job->reserved();
        }

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
     * Push a new job onto the queue.
     *
     * @param  string $job
     * @param  mixed $data
     * @param  string $queue
     *
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushToDatabase(0, $queue, $this->createPayload($job, $data));
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string $job
     * @param  mixed $data
     * @param  string $queue
     *
     * @return mixed
     */
    public function exists($job, $data = '', $queue = null)
    {
        return null !== $this->getCollection()->findOne([
                'queue' => $queue,
                'payload' => $this->createPayload($job, $data),
            ]);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string $payload
     * @param  string $queue
     * @param  array $options
     *
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->pushToDatabase(0, $queue, $payload);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  DateTime|int $delay
     * @param  string $job
     * @param  mixed $data
     * @param  string $queue
     *
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushToDatabase($delay, $queue, $this->createPayload($job, $data));
    }

    /**
     * Push an array of jobs onto the queue.
     *
     * @param  array $jobs
     * @param  mixed $data
     * @param  string $queue
     *
     * @return mixed
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        $queue = $this->getQueue($queue);

        $availableAt = $this->getAvailableAt(0);

        $records = array_map(function ($job) use ($queue, $data, $availableAt) {
            return $this->buildDatabaseRecord($queue, $this->createPayload($job, $data), $availableAt);
        }, (array)$jobs);

        return $this->getCollection()->insert($records);
    }

    /**
     * Release a reserved job back onto the queue.
     *
     * @param  string $queue
     * @param  \StdClass $job
     * @param  int $delay
     *
     * @return mixed
     */
    public function release($queue, $job, $delay)
    {
        return $this->pushToDatabase($delay, $queue, $job->payload, $job->attempts);
    }

    /**
     * Push a raw payload to the database with a given delay.
     *
     * @param  DateTime|int $delay
     * @param  string|null $queue
     * @param  string $payload
     * @param  int $attempts
     *
     * @return mixed
     */
    protected function pushToDatabase($delay, $queue, $payload, $attempts = 0)
    {
        $attributes = $this->buildDatabaseRecord($this->getQueue($queue), $payload, $this->getAvailableAt($delay), $attempts);

        return $this->getCollection()->insert($attributes);
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string $queue
     *
     * @return Job|null
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);

        if ($job = $this->getNextAvailableJob($queue)) {
            // Worker does it
            //$this->markJobAsReserved($job);

            return $job; //new MongoJob($this->container, $this, $job->getJob(), $queue);
        }
    }

    /**
     * Get the next available job for the queue.
     *
     * @param  string|null $queue
     *
     * @return null|MongoJob
     */
    protected function getNextAvailableJob($queue)
    {
        $job = $this->getCollection()
            ->findOne([
                'queue' => $this->getQueue($queue),
                '$or' => [
                    $this->isAvailable(),
                    $this->isReservedButExpired(),
                ],
            ], [], [
                'sort' => ['_id' => 1],
            ]);

        return $job ? new MongoJob($this->container, $this, (object)$job, ((object)$job)->queue) : null;
    }

    /**
     * Get available jobs
     *
     * @return array
     */
    protected function isAvailable()
    {
        return [
            'reserved_at' => null,
            'available_at' => ['$lte' => $this->currentTime()],
        ];
    }

    /**
     * Get reserved but expired by time jobs
     *
     * @return array
     */
    protected function isReservedButExpired()
    {
        return [
            'reserved_at' => ['$lte' => Carbon::now()->subSeconds($this->expire)->getTimestamp()],
        ];
    }

    /**
     * Mark the given job ID as reserved.
     *
     * @param MongoJob $job
     */
    public function markJobAsReserved($job)
    {
        $attempts = $job->attempts() + 1;
        $reserved_at = $this->currentTime();

        $this->getCollection()->update(['_id' => new \MongoDB\BSON\ObjectID($job->getJobId())], [
            '$set' => [
                'attempts' => $attempts,
                'reserved' => 1,
                'reserved_at' => $reserved_at,
            ],
        ]);
    }

    /**
     * Delete a reserved job from the queue.
     *
     * @param  string $queue
     * @param  string $id
     */
    public function deleteReserved($queue, $id)
    {
        return $this->getCollection()->remove(['_id' => new \MongoDB\BSON\ObjectID($id)]);
    }

    /**
     * Get the "available at" UNIX timestamp.
     *
     * @param  DateTime|int $delay
     *
     * @return int
     */
    protected function getAvailableAt($delay)
    {
        $availableAt = $delay instanceof DateTime ? $delay : Carbon::now()->addSeconds($delay);

        return $availableAt->getTimestamp();
    }

    /**
     * Create an array to insert for the given job.
     *
     * @param  string|null $queue
     * @param  string $payload
     * @param  int $availableAt
     * @param  int $attempts
     *
     * @return array
     */
    protected function buildDatabaseRecord($queue, $payload, $availableAt, $attempts = 0)
    {
        return [
            'queue' => $queue,
            'payload' => $payload,
            'attempts' => $attempts,
            'reserved' => 0,
            'reserved_at' => null,
            'available_at' => $availableAt,
            'created_at' => $this->currentTime(),
        ];
    }

    /**
     * Get the queue or return the default.
     *
     * @param  string|null $queue
     *
     * @return string
     */
    protected function getQueue($queue)
    {
        return $queue ?: $this->default;
    }

    /**
     * Get the expiration time in seconds.
     *
     * @return int|null
     */
    public function getExpire()
    {
        return $this->expire;
    }

    /**
     * Set the expiration time in seconds.
     *
     * @param  int|null $seconds
     */
    public function setExpire($seconds)
    {
        $this->expire = $seconds;
    }

    /**
     * Get the size of the queue.
     *
     * @param  string $queue
     *
     * @return int
     */
    public function size($queue = null)
    {
        $this->getCollection()->count();
    }

    /**
     * Get queue table
     *
     * @return Collection Mongo collection instance
     */
    protected function getCollection()
    {
        return $this->database->getDatabase()->getCollection($this->table);
    }
}
