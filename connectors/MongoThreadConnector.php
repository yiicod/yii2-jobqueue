<?php

namespace yiicod\jobqueue\connectors;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Yii;
use yiicod\jobqueue\queues\MongoThreadQueue;

/**
 * Connector for laravel queue to mongodb
 *
 * @author Virchenko Maksim <muslim1992@gmail.com>
 */
class MongoThreadConnector implements ConnectorInterface
{
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
            'connection' => 'mongodb',
        ], $config);

        $connection = Yii::$app->get($config['connection']);

        return new MongoThreadQueue($connection, $config['table'], $config['queue'], $config['expire'], $config['limit']);
    }
}
