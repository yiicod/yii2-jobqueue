Yii Job Queue based on Illuminate Queue
=======================================

[![Latest Stable Version](https://poser.pugx.org/yiicod/yii2-jobqueue/v/stable)](https://packagist.org/packages/yiicod/yii2-jobqueue) [![Total Downloads](https://poser.pugx.org/yiicod/yii2-jobqueue/downloads)](https://packagist.org/packages/yiicod/yii2-jobqueue) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiicod/yii2-jobqueue/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiicod/yii2-jobqueue/?branch=master)[![Code Climate](https://codeclimate.com/github/yiicod/yii2-jobqueue/badges/gpa.svg)](https://codeclimate.com/github/yiicod/yii2-jobqueue)

Provides Illuminate queues implementation for Yii 2 using mongodb as main storage.

#### Base config:

```php
    'bootstrap' => [
        'jobqueue'
    ],
    'components' => [
        'jobqueue' => [
            'class' => \yiicod\jobqueue\JobQueue::class
        ],
        'mongodb' => [
            'class' => '\yii\mongodb\Connection',
            'dsn' => 'mongodb://@localhost:27017/mydatabase',
        ],        
    ]
```
#### Console config
```php
    'bootstrap' => [
        'jobqueue'
    ],
    'controllerMap' => [
        'job-queue' => [
            'class' => \yiicod\jobqueue\commands\JobQueueCommand::class,
        ]
    ],
    'components' => [
        'jobqueue' => [
            'class' => \yiicod\jobqueue\JobQueue::class
        ],
        'mongodb' => [
            'class' => '\yii\mongodb\Connection',
            'dsn' => 'mongodb://@localhost:27017/mydatabase',
        ],        
    ]    
```
###### Start worker:

Run worker daemon with console command: 
```php
$ php yii job-queue/start
```

Stop worker daemon:
```php
$ php yii job-queue/stop
```
##### OR use pm2(http://pm2.keymetrics.io/). This variant more preferable.
```php
    'controllerMap' => [
        'job-queue' => [
            'class' => \yiicod\jobqueue\commands\WorkerCommand::class,
        ]
    ],
```
###### pm2 config:
```json
    {
      "apps": [
        {
          "name": "job-queue",
          "script": "yii",
          "args": [
            "job-queue/work"
          ],
          "exec_interpreter": "php",
          "exec_mode": "fork_mode",
          "max_memory_restart": "1G",
          "watch": false,
          "merge_logs": true,
          "out_file": "runtime/logs/job_queue.log",
          "error_file": "runtime/logs/job_queue.log"
        }
      ]
    }
```
###### Run PM2 daemons
```bash
pm2 start daemons-app.json
```

Note: Don't forget configure mongodb


#### Adding jobs to queue:

Create your own handler which implements yiicod\jobqueue\base\JobQueueInterface 
OR extends yiicod\jobqueue\handlers\JobQueue 
and run parent::fire($job, $data) to restart db connection before job process

```php
JobQueue::push(<--YOUR JOB QUEUE CLASS NAME->>, $data, $queue, $connection);
// Optional: $queue, $connection
```

Note: $data - additional data to your handler

#### Queue configuration:

Add jobqueue component with connections parameters, specially with "MongoThreadQueue" driver and connection name ("default" in example)
```php
'jobqueue' => [
    'class' => \yiicod\jobqueue\JobQueue::class,
    'connections' => [
        'default' => [
            'driver' => 'mongo-thread',
            'table' => 'yii-jobs',
            'queue' => 'default',
            'connection' => 'mongodb', // Default mongodb connection 
            'expire' => 60,
            'limit' => 1, // How many parallel process should run at the same time            
        ],
    ]
]
```
Worker will take jobs from mongo database and run them by thread with defined driver using "mongo-thread" command in the background

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