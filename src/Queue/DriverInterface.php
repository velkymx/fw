<?php

declare(strict_types=1);

namespace Fw\Queue;

interface DriverInterface
{
    /**
     * Push a job onto the queue.
     */
    public function push(JobInterface $job): string;

    /**
     * Push a job onto the queue with a delay.
     */
    public function later(int $delay, JobInterface $job): string;

    /**
     * Pop the next job from the queue.
     */
    public function pop(string $queue = 'default'): ?array;

    /**
     * Delete a job from the queue.
     */
    public function delete(string $jobId): bool;

    /**
     * Release a job back onto the queue.
     */
    public function release(string $jobId, int $delay = 0): bool;

    /**
     * Get the number of jobs in the queue.
     */
    public function size(string $queue = 'default'): int;

    /**
     * Clear all jobs from the queue.
     */
    public function clear(string $queue = 'default'): int;
}
