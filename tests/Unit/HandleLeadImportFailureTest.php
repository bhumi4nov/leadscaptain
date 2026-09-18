<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Lead\HandleLeadImportFailure;
use App\Notifications\LeadImportFailedNotification;
use Illuminate\Bus\Batch;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class HandleLeadImportFailureTest extends TestCase
{
    public function test_it_logs_import_failure(): void
    {
        $batch = Mockery::mock(Batch::class);

        $batch->id = 'batch-123';
        $batch->failedJobs = 1;

        Log::shouldReceive('channel')
            ->once()
            ->with('leadscaptain')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->with(
                'Leadscaptain import batch failed',
                Mockery::on(function (array $context): bool {
                    return $context['batch_id'] === 'batch-123'
                        && $context['failed_jobs'] === 1
                        && $context['error'] === 'Test failure';
                })
            );

        $handler = app(HandleLeadImportFailure::class);

        $handler->execute(
            $batch,
            new RuntimeException('Test failure')
        );

        $this->assertTrue(true);
    }

    public function test_it_sends_notification_when_email_is_configured(): void
    {
        Notification::fake();

        config([
            'leadscaptain.failure_notification_email'
                => 'test@example.com',
        ]);

        $batch = Mockery::mock(Batch::class);

        $batch->id = 'batch-456';
        $batch->failedJobs = 1;

        Log::shouldReceive('channel')
            ->once()
            ->with('leadscaptain')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once();

        $handler = app(HandleLeadImportFailure::class);

        $handler->execute(
            $batch,
            new RuntimeException('Import failed')
        );

        Notification::assertSentOnDemand(
            LeadImportFailedNotification::class,
            function (
                LeadImportFailedNotification $notification,
                array $channels,
                AnonymousNotifiable $notifiable
            ): bool {
                return in_array('mail', $channels, true);
            }
        );
    }
}