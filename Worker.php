<?php

namespace yiicod\jobqueue;

use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker as BaseWorker;
use Illuminate\Queue\WorkerOptions;
use Throwable;
use Yii;
use yii\base\Event;
use yiicod\jobqueue\base\FatalThrowableError;
use yiicod\jobqueue\events\JobExceptionOccurredEvent;
use yiicod\jobqueue\events\JobFailedEvent;
use yiicod\jobqueue\events\JobProcessedEvent;
use yiicod\jobqueue\events\JobProcessingEvent;
use yiicod\jobqueue\events\WorkerStoppingEvent;

/**
 * Worker for laravel queues
 *
 * @author Virchenko Maksim <muslim1992@gmail.com>
 */
class Worker extends BaseWorker
{
    /**
     * Events
     */
    const EVENT_RAISE_BEFORE_JOB = 'raiseBeforeJobEvent';
    const EVENT_RAISE_AFTER_JOB = 'raiseAfterJobEvent';
    const EVENT_RAISE_EXCEPTION_OCCURED_JOB = 'raiseExceptionOccurredJobEvent';
    const EVENT_RAISE_FAILED_JOB = 'raiseFailedJobEvent';
    const EVENT_STOP = 'stop';

    /**
     * Failer instance
     *
     * @var FailedJobProviderInterface
     */
    protected $failer;

    /**
     * Create a new queue worker.
     *
     * @param QueueManager $manager
     * @param FailedJobProviderInterface $failer
     * @param ExceptionHandler $exceptions
     */
    public function __construct(QueueManager $manager,
                                FailedJobProviderInterface $failer,
                                ExceptionHandler $exceptions)
    {
        $this->manager = $manager;
        $this->failer = $failer;
        $this->exceptions = $exceptions;
    }

    /**
     * Listen to the given queue in a loop.
     *
     * @param  string $connectionName
     * @param  string $queue
     * @param WorkerOptions $options
     *
     * @return array
     */
    public function daemon($connectionName, $queue, WorkerOptions $options)
    {
        while (true) {
            $this->runNextJob(
                $connectionName, $queue, $options
            );

            if ($this->memoryExceeded($options->memory)) {
                $this->stop();
            }
        }
    }

    /**
     * Process the next job on the queue.
     *
     * @param  string $connectionName
     * @param  string $queue
     * @param  \Illuminate\Queue\WorkerOptions $options
     */
    public function runNextJob($connectionName, $queue, WorkerOptions $options)
    {
        $job = $this->getNextJob(
            $this->manager->connection($connectionName), $queue
        );

        // If we're able to pull a job off of the stack, we will process it and then return
        // from this method. If there is no job on the queue, we will "sleep" the worker
        // for the specified number of seconds, then keep processing jobs after sleep.
        if ($job instanceof Job) {
            return $this->runJob($job, $connectionName, $options);
        }

        $this->sleep($options->sleep);
    }

    /**
     * Process the next job on the queue.
     *
     * @param  string $connectionName
     * @param $id
     * @param  \Illuminate\Queue\WorkerOptions $options
     */
    public function runJobById($connectionName, $id, WorkerOptions $options)
    {
        try {
            $job = $this->manager->connection($connectionName)->getJobById($id);

            // If we're able to pull a job off of the stack, we will process it and then return
            // from this method. If there is no job on the queue, we will "sleep" the worker
            // for the specified number of seconds, then keep processing jobs after sleep.
            if ($job) {
                return $this->process($connectionName, $job, $options);
            }
        } catch (Exception $e) {
            $this->exceptions->report($e);
        } catch (Throwable $e) {
            $this->exceptions->report(new FatalThrowableError($e));
        }

        $this->sleep($options->sleep);
    }

    /**
     * Mark the given job as failed and raise the relevant event.
     *
     * @param  string $connectionName
     * @param  \Illuminate\Contracts\Queue\Job $job
     * @param  \Exception $e
     */
    protected function failJob($connectionName, $job, $e)
    {
        if ($job->isDeleted()) {
            return;
        }

        try {
            // If the job has failed, we will delete it, call the "failed" method and then call
            // an event indicating the job has failed so it can be logged if needed. This is
            // to allow every developer to better keep monitor of their failed queue jobs.
            $job->delete();

            $job->failed($e);
        } finally {
            $this->failer->log($connectionName, $job->getQueue(), $job->getRawBody(), $e);
            $this->raiseFailedJobEvent($connectionName, $job, $e);
        }
    }

    /**
     * Raise the before queue job event.
     *
     * @param string $connectionName
     * @param \Illuminate\Contracts\Queue\Job $job
     */
    protected function raiseBeforeJobEvent($connectionName, $job)
    {
        Event::trigger(self::class, self::EVENT_RAISE_BEFORE_JOB, new JobProcessingEvent($connectionName, $job));
    }

    /**
     * Raise the after queue job event.
     *
     * @param string $connectionName
     * @param \Illuminate\Contracts\Queue\Job $job
     */
    protected function raiseAfterJobEvent($connectionName, $job)
    {
        Event::trigger(self::class, self::EVENT_RAISE_AFTER_JOB, new JobProcessedEvent($connectionName, $job));
    }

    /**
     * Raise the exception occurred queue job event.
     *
     * @param string $connectionName
     * @param \Illuminate\Contracts\Queue\Job $job
     * @param \Exception $e
     */
    protected function raiseExceptionOccurredJobEvent($connectionName, $job, $e)
    {
        Event::trigger(self::class, self::EVENT_RAISE_EXCEPTION_OCCURED_JOB, new JobExceptionOccurredEvent(
            $connectionName, $job, $e
        ));
    }

    /**
     * Raise the failed queue job event.
     *
     * @param  string $connectionName
     * @param  \Illuminate\Contracts\Queue\Job $job
     * @param  \Exception $e
     */
    protected function raiseFailedJobEvent($connectionName, $job, $e)
    {
        Event::trigger(self::class, self::EVENT_RAISE_FAILED_JOB, new JobFailedEvent(
            $connectionName, $job, $e
        ));
    }

    /**
     * Stop listening and bail out of the script.
     */
    public function stop($status = 0)
    {
        Event::trigger(self::class, self::EVENT_STOP, new WorkerStoppingEvent());

        Yii::$app->end();
    }
}
