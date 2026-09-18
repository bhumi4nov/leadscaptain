<?php

declare(strict_types=1);

namespace App\Application\Lead;

use App\Notifications\LeadImportFailedNotification;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class HandleLeadImportFailure
{
    public function execute(
        Batch $batch,
        Throwable $exception
    ): void {
        Log::channel('leadscaptain')->error(
            'Leadscaptain import batch failed',
            [
                'batch_id' => $batch->id,
                'failed_jobs' => $batch->failedJobs,
                'error' => $exception->getMessage(),
            ]
        );

        $email = config(
            'leadscaptain.failure_notification_email'
        );

        if (is_string($email) && $email !== '') {
            Notification::route('mail', $email)
                ->notify(
                    new LeadImportFailedNotification(
                        batchId: (string) $batch->id,
                        message: $exception->getMessage(),
                    )
                );
        }
    }
}