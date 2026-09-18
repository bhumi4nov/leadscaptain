<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class QueueTestJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        \Illuminate\Support\Facades\Log::info(
            'QUEUE TEST JOB EXECUTED'
        );
    }
}