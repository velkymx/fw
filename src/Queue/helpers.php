<?php

declare(strict_types=1);

namespace Fw\Queue;

/**
 * Dispatch a job to the queue.
 */
function dispatch(JobInterface $job): string
{
    return Queue::getInstance()->dispatch($job);
}

/**
 * Dispatch a job to the queue with a delay.
 */
function dispatch_after(int $seconds, JobInterface $job): string
{
    return Queue::getInstance()->dispatchAfter($seconds, $job);
}
