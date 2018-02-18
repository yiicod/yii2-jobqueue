<?php

namespace yiicod\jobqueue\failed;

use Carbon\Carbon;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use yiicod\jobqueue\queues\MongoThreadQueue;

/**
 * Mongo provider for failed jobs
 *
 * @author Virchenko Maksim <muslim1992@gmail.com>
 */
class MongoFailedJobProvider implements FailedJobProviderInterface
{
    /**
     * The database connection name.
     *
     * @var MongoThreadQueue
     */
    protected $database;

    /**
     * The database table.
     *
     * @var string
     */
    protected $table;

    /**
     * Create a new database failed job provider.
     *
     * @param  string $database
     * @param  string $table
     */
    public function __construct($database, $table)
    {
        $this->database = $database;
        $this->table = $table;
    }

    /**
     * Log a failed job into storage.
     *
     * @param  string $connection
     * @param  string $queue
     * @param  string $payload
     * @param \Exception $exception
     *
     * @return int|null|void
     */
    public function log($connection, $queue, $payload, $exception)
    {
        $this->getCollection()->insert([
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'exception' => $exception->getMessage(),
            'failed_at' => Carbon::now(),
        ]);
    }

    /**
     * Get a list of all of the failed jobs.
     *
     * @return array
     */
    public function all()
    {
        $result = [];
        $data = $this->getCollection()->find([])->sort(['_id' => -1]);
        foreach ($data as $item) {
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Get a single failed job.
     *
     * @param  mixed $id
     *
     * @return array
     */
    public function find($id)
    {
        return $this->getCollection()->find(['_id' => new \MongoDB\BSON\ObjectID($id)]);
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param  mixed $id
     *
     * @return bool
     */
    public function forget($id)
    {
        return $this->getCollection()->remove(['_id' => new \MongoDB\BSON\ObjectID($id)]);
    }

    /**
     * Flush all of the failed jobs from storage.
     */
    public function flush()
    {
        $this->getCollection()->remove();
    }

    /**
     * Get a new query builder instance for the table.
     *
     * @return object mongo collection
     */
    protected function getCollection()
    {
        return $this->database->getDatabase()->getCollection($this->table);
    }
}
