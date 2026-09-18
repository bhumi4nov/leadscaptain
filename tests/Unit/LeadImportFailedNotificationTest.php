<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Notifications\LeadImportFailedNotification;
use PHPUnit\Framework\TestCase;

final class LeadImportFailedNotificationTest extends TestCase
{
    public function test_it_uses_mail_channel(): void
    {
        $notification = new LeadImportFailedNotification(
            batchId: 'batch-123',
            message: 'Import failed because page 3 returned HTTP 500.',
        );

        $channels = $notification->via(new \stdClass());

        $this->assertSame(['mail'], $channels);
    }

    public function test_it_builds_the_failure_email(): void
    {
        $notification = new LeadImportFailedNotification(
            batchId: 'batch-123',
            message: 'Import failed because page 3 returned HTTP 500.',
        );

        $mail = $notification->toMail(new \stdClass());

        $this->assertSame(
            'Leadscaptain Lead Import Failed',
            $mail->subject
        );

        $this->assertContains(
            'The Leadscaptain lead import batch has failed.',
            $mail->introLines
        );

        $this->assertContains(
            'Batch ID: batch-123',
            $mail->introLines
        );

        $this->assertContains(
            'Error: Import failed because page 3 returned HTTP 500.',
            $mail->introLines
        );
    }
}