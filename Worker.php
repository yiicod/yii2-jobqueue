<?php

namespace yiicod\jobqueue;

use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\QueueManager;
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
use yiicod\jobqueue\queues\MongoThreadQueue;

/**
 * Worker for laravel queues
 *
 * @author Orlov Alexey <aaorlov88@gmail.com>
 * @author Virchenko Maksim <muslim1992@gmail.com>
 */
class Worker
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
     * The queue manager instance.
     *
     * @var \Illuminate\Queue\QueueManager
     */
    protected $manager;

    /**
     * Failer instance
     *
     * @var FailedJobProviderInterface
     */
    protected $failer;

    /**
     * The exception handler instance.
     *
     * @var \Illuminate\Foundation\Exceptions\Handler
     */
    protected $exceptions;

    protected $shouldQuit = false;

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
            if ($this->shouldQuit) {
                $this->kill();
            }

            if (false === $this->runNextJob($connectionName, $queue, $options)) {
                $this->sleep($options->sleep);
            }

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
        /** @var MongoThreadQueue|Queue $connection */
        $connection = $this->manager->connection($connectionName);
        $job = $this->getNextJob($connection, $queue);

        // If we're able to pull a job off of the stack, we will process it and then return
        // from this method. If there is no job on the queue, we will "sleep" the worker
        // for the specified number of seconds, then keep processing jobs after sleep.
        if ($job instanceof Job && $connection->canRunJob($job)) {
            // If job can be run, then markJobAsReserved and run process
            $connection->markJobAsReserved($job);

            $this->runInBackground($job, $connectionName);

            return true;
        }

        return false;
    }

    /**
     * Make a Process for the Artisan command for the job id.
     *
     * @param Job $job
     * @param string $connectionName
     */
    protected function runInBackground(Job $job, string $connectionName)
    {
        $process = Yii::$container->get(JobProcess::class)->getProcess($job, $connectionName);

        $process->run();
    }

    /**
     * Process the given job from the queue.
     *
     * @param  string $connectionName
     * @param  \Illuminate\Contracts\Queue\Job $job
     * @param  \Illuminate\Queue\WorkerOptions $options
     *
     * @throws \Throwable
     */
    public function process($connectionName, $job, WorkerOptions $options)
    {
        try {
            // First we will raise the before job event and determine if the job has already ran
            // over the its maximum attempt limit, which could primarily happen if the job is
            // continually timing out and not actually throwing any exceptions from itself.
            $this->raiseBeforeJobEvent($connectionName, $job);

            $this->markJobAsFailedIfAlreadyExceedsMaxAttempts(
                $connectionName, $job, (int)$options->maxTries
            );

            // Here we will fire off the job and let it process. We will catch any exceptions so
            // they can be reported to the developers logs, etc. Once the job is finished the
            // proper events will be fired to let any listeners know this job has finished.
            $job->fire();

            $this->raiseAfterJobEvent($connectionName, $job);
        } catch (Exception $e) {
            $this->handleJobException($connectionName, $job, $options, $e);
        } catch (Throwable $e) {
            $this->handleJobException(
                $connectionName, $job, $options, new FatalThrowableError($e)
            );
        }
    }

    /**
     * Handle an exception that occurred while the job was running.
     *
     * @param string $connectionName
     * @param \Illuminate\Contracts\Queue\Job $job
     * @param WorkerOptions $options
     * @param \Exception $e
     *
     * @throws \Exception
     */
    protected function handleJobException($connectionName, $job, WorkerOptions $options, $e)
    {
        try {
            // First, we will go ahead and mark the job as failed if it will exceed the maximum
            // attempts it is allowed to run the next time we process it. If so we will just
            // go ahead and mark it as failed now so we do not have to release this again.
            if (!$job->hasFailed()) {
                $this->markJobAsFailedIfWillExceedMaxAttempts(
                    $connectionName, $job, (int)$options->maxTries, $e
                );
            }

            $this->raiseExceptionOccurredJobEvent(
                $connectionName, $job, $e
            );
        } finally {
            // If we catch an exception, we will attempt to release the job back onto the queue
            // so it is not lost entirely. This'll let the job be retried at a later time by
            // another listener (or this same one). We will re-throw this exception after.
            if (!$job->isDeleted() && !$job->isReleased() && !$job->hasFailed()) {
                $job->release($options->delay);
            }
        }

        throw $e;
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * This will likely be because the job previously exceeded a timeout.
     *
     * @param  string $connectionName
     * @param  \Illuminate\Contracts\Queue\Job $job
     * @param  int $maxTries
     */
    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts($connectionName, $job, $maxTries)
    {
        $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

        if (0 === $maxTries || $job->attempts() <= $maxTries) {
            return;
        }

        $this->failJob($connectionName, $job, $e = new MaxAttemptsExceededException(
            'A queued job has been attempted too many times. The job may have previously timed out.'
        ));

        throw $e;
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * @param  string $connectionName
     * @param  \Illuminate\Contracts\Queue\Job $job
     * @param  int $maxTries
     * @param  \Exception $e
     */
    protected function markJobAsFailedIfWillExceedMaxAttempts($connectionName, $job, $maxTries, $e)
    {
        $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

        if ($maxTries > 0 && $job->attempts() >= $maxTries) {
            $this->failJob($connectionName, $job, $e);
        }
    }

    /**
     * Get the next job from the queue connection.
     *
     * @param  \Illuminate\Contracts\Queue\Queue $connection
     * @param  string $queue
     *
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    protected function getNextJob($connection, $queue)
    {
        try {
            foreach (explode(',', $queue) as $queue) {
                if (!is_null($job = $connection->pop($queue))) {
                    return $job;
                }
            }
        } catch (Exception $e) {
            $this->exceptions->report($e);
        } catch (Throwable $e) {
            $this->exceptions->report($e = new FatalThrowableError($e));
        }
    }

    /**
     * Kill the process.
     *
     * @param  int $status
     */
    public function kill($status = 0)
    {
        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }

        exit($status);
    }

    /**
     * Sleep the script for a given number of seconds.
     *
     * @param int $seconds
     */
    public function sleep($seconds)
    {
        sleep($seconds);
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
     * Determine if the memory limit has been exceeded.
     *
     * @param  int $memoryLimit
     *
     * @return bool
     */
    public function memoryExceeded($memoryLimit)
    {
        return (memory_get_usage() / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Stop the worker if we have lost connection to a database.
     *
     * @param  \Exception $e
     */
    protected function stopWorkerIfLostConnection($e)
    {
        $message = $e->getMessage();
        $contains = [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'Transaction() on null',
        ];

        foreach ($contains as $contain) {
            if (strpos($message, $contain)) {
                $this->shouldQuit = true;
            }
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
