<?php

namespace yiicod\jobqueue;

use Illuminate\Queue\Capsule\Manager;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yiicod\jobqueue\connectors\MongoThreadConnector;

/**
 * Yii component for laravel 5 queues to work with mongodb
 *
 * @author Orlov Alexey <aaorlov88@gmail.com>
 * @author Virchenko Maksim <muslim1992@gmail.com>
 */
class JobQueue extends Component implements BootstrapInterface
{
    /**
     * Available connections
     *
     * @var array
     */
    public $connections = [
        'thread' => [
            'driver' => 'mongo-thread',
            'table' => 'yii_jobs_thread',
            'queue' => 'default',
            'expire' => 60,
            'limit' => 15, // Or 1
            'connectionName' => 'thread',
        ],
    ];

    /**
     * Encryption key
     *
     * @var string
     */
    public $privateKey = 'rc5lgpue80sr17nx';

    /**
     * Manager instance
     *
     * @var
     */
    private $manager = null;

    /**
     * Initialize
     *
     * @param \yii\base\Application $app
     */
    public function bootstrap($app)
    {
        $this->connect();
    }

    /**
     * Connect queue manager for mongo database
     *
     * @return Manager
     */
    protected function connect()
    {
        $this->manager = new Manager();

        $this->manager->addConnector('mongo-thread', function () {
            return new MongoThreadConnector(Yii::$app->mongodb);
        });

        foreach ($this->connections as $name => $params) {
            $this->manager->addConnection($params, $name);
        }

        //Set as global to access
        $this->manager->setAsGlobal();
    }

    /**
     * Get queue manager instance
     *
     * @return mixed
     */
    public function getQueueManager()
    {
        return $this->manager->getQueueManager();
    }

    /**
     * Push new job to queue
     *
     * @param mixed $job
     * @param array $data
     * @param string $queue
     * @param string $connection
     *
     * @return mixed
     */
    public static function push($job, $data = [], $queue = 'default', $connection = 'default')
    {
        return Manager::push($job, $data, $queue, $connection);
    }

    /**
     * Push new job to queue if this job is not exist
     *
     * @param mixed $job
     * @param array $data
     * @param string $queue
     * @param string $connection
     *
     * @return mixed
     */
    public static function pushUnique($job, $data = [], $queue = 'default', $connection = 'default')
    {
        if (false === Manager::connection($connection)->exists($job, $data, $queue)) {
            return Manager::push($job, $data, $queue, $connection);
        }

        return null;
    }

    /**
     * Push a new an array of jobs onto the queue.
     *
     * @param  array $jobs
     * @param  mixed $data
     * @param  string $queue
     * @param  string $connection
     *
     * @return mixed
     */
    public static function bulk($jobs, $data = '', $queue = null, $connection = null)
    {
        return Manager::bulk($jobs, $data, $queue, $connection);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTime|int $delay
     * @param  string $job
     * @param  mixed $data
     * @param  string $queue
     * @param  string $connection
     *
     * @return mixed
     */
    public static function later($delay, $job, $data = '', $queue = null, $connection = null)
    {
        return Manager::later($delay, $job, $data, $queue, $connection);
    }

    /**
     * Push a new job into the queue after a delay if job does not exist.
     *
     * @param  \DateTime|int $delay
     * @param  string $job
     * @param  mixed $data
     * @param  string $queue
     * @param  string $connection
     *
     * @return mixed
     */
    public static function laterUnique($delay, $job, $data = '', $queue = null, $connection = null)
    {
        if (false === Manager::connection($connection)->exists($job, $data, $queue)) {
            return Manager::later($delay, $job, $data, $queue, $connection);
        }

        return null;
    }
}
