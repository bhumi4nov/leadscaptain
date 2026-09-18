<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ImportAllLeadsJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ImportAllLeadsJobTest extends TestCase
{
    public function test_it_runs_the_lead_import_orchestrator(): void
    {
        Bus::fake();

        Http::fake([
            'https://api.leadscaptain.com/leads*' => Http::response([
                'data' => [],
                'page' => 1,
                'limit' => 100,
                'total' => 300,
                'total_pages' => 3,
            ], 200),
        ]);

        $job = new ImportAllLeadsJob();

        $job->handle(
            app(\App\Application\Lead\ImportAllLeads::class)
        );

        Bus::assertBatched(function ($batch): bool {
            return $batch->name === 'Leadscaptain Lead Import'
                && count($batch->jobs) === 2;
        });

        Http::assertSent(function ($request): bool {
            return $request->url()
                === 'https://api.leadscaptain.com/leads?page=1&limit=100';
        });
    }

    public function test_it_has_three_retries(): void
    {
        $job = new ImportAllLeadsJob();

        $this->assertSame(3, $job->tries);
    }

    public function test_it_uses_the_expected_backoff_delays(): void
    {
        $job = new ImportAllLeadsJob();

        $this->assertSame(
            [60, 300, 1800],
            $job->backoff()
        );
    }
}