Laravel5 Queue (Yii 2)
======================

Provides Illuminate queues implementation for Yii 2 (using mongodb as main storage).

Config:
-------
```php
'bootstrap' => [
    'jobqueue'
],
'components' => [
    'jobqueue' => [
        'class' => 'yiicod\jobqueue\JobQueue'
    ]
]
```

And console command:
```php
    'controllerMap' => [
        'job-queue' => [
            'class' => 'yiicod\jobqueue\commands\WorkerCommand',
        ]
    ],
```

also: component requires "mongodb" component to connect to mongo database


Adding jobs to queue:
---------------------

Create your own handler which implements yiicod\jobqueue\base\JobQueueInterface 
OR extends yiicod\jobqueue\handlers\JobQueue 
and run parent::fire($job, $data) to restart db connection before job process

```php
JobQueue::push(<--YOUR JOB QUEUE CLASS NAME->>, $data);
```

Note: $data - additional data to your handler

Start sync worker:
------------------

Run worker daemon with console command: 
```php
$ php yiic job-queue/start
```

Stop worker daemon:
```php
$ php yiic job-queue/stop
```

Async worker:
-------------

Add jobqueue component with connections parameters, specially with "MongoThreadQueue" driver and connection name ("default" in example)
```php
'laravel5queue' => [
    'class' => 'yiicod\jobqueue\JobQueue',
    'connections' => [
        'default' => [
            'driver' => 'mongo-thread',
            'table' => 'yii-jobs',
            'queue' => 'default',
            'expire' => 60,
            'limit' => 1,
            'connectionName' => 'default',
            'yiiAlias' => '@app/..'
        ],
    ]
]
```

Now you can run thread queues like usual:
```php
$ php yiic job-queue/start
```
And worker will take jobs from mongo database and run them by thread with defined driver using "mongo-thread" command in background

Available events:
_________________

In Worker::class:
```php
EVENT_RAISE_BEFORE_JOB = 'raiseBeforeJobEvent';
EVENT_RAISE_AFTER_JOB = 'raiseAfterJobEvent';
EVENT_RAISE_EXCEPTION_OCCURED_JOB = 'raiseExceptionOccurredJobEvent';
EVENT_RAISE_FAILED_JOB = 'raiseFailedJobEvent';
EVENT_STOP = 'stop';
```