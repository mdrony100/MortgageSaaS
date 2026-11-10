<?php

namespace App\Jobs\Concerns;

/**
 * Horizon listens to the dedicated "bank" queue. A database (or other non-Redis)
 * worker started as `php artisan queue:work` only listens to that connection's
 * default queue, which is what shared hosts without Redis actually run.
 */
trait UsesAnalysisQueue
{
    protected function assignAnalysisQueue(): void
    {
        $connection = (string) config('queue.default');
        if ($connection === 'redis') {
            $this->onQueue('bank');

            return;
        }
        $name = config('queue.connections.'.$connection.'.queue');
        $this->onQueue(is_string($name) && $name !== '' ? $name : 'default');
    }
}
