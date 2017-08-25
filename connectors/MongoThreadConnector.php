<?php

namespace yiicod\jobqueue\connectors;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use yiicod\jobqueue\queues\MongoThreadQueue;

/**
 * Connector for laravel queue to mongodb
 *
 * @author Virchenko Maksim <muslim1992@gmail.com>
 */
class MongoThreadConnector implements ConnectorInterface
{
    /**
     * Database connections.
     */
    protected $connection;

    /**
     * Create a new connector instance.
     *
     * @param $connection
     */
    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    /**
     * Establish a queue connection.
     *
     * @param  array $config
     *
     * @return Queue
     */
    public function connect(array $config)
    {
        $config = array_merge([
            'limit' => 15,
            'yiiAlias' => '@app/..',
            'binary' => 'php',
            'binaryArgs' => [],
            'connectionName' => 'async',
        ], $config);

        return new MongoThreadQueue($this->connection, $config['table'], $config['queue'], $config['expire'], $config['limit'], $config['yiiAlias'], $config['binary'], $config['binaryArgs'], $config['connectionName']);
    }
}
