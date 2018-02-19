<?php

namespace yiicod\jobqueue\handlers;

use Exception;
use Yii;

/**
 * DaemonExceptionHandler
 *
 * @author Virchenko Maksim <muslim1992@gmail.com>
 */
class ExceptionHandler implements \Illuminate\Contracts\Debug\ExceptionHandler
{
    /**
     * DaemonExceptionHandler constructor.
     */
    public function __construct()
    {
        // automatically send every new message to available log routes
        Yii::getLogger()->flushInterval = 1;
    }

    /**
     * Report or log an exception.
     *
     * @param  \Exception $e
     */
    public function report(Exception $e)
    {
        Yii::error(sprintf("%s (%s : %s)\nStack trace:\n%s", $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()), 'jobqueue');
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \HttpRequest $request
     * @param  \Exception $e
     */
    public function render($request, Exception $e)
    {
        return;
    }

    /**
     * Render an exception to the console.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     * @param  \Exception $e
     */
    public function renderForConsole($output, Exception $e)
    {
        return;
    }
}
