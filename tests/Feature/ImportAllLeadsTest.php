<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Lead\ImportAllLeads;
use App\Jobs\FetchLeadPageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ImportAllLeadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fetches_first_page_and_dispatches_remaining_pages(): void
    {
        Http::fake([
            'https://api.leadscaptain.com/leads*' => Http::response([
                'data' => [],
                'page' => 1,
                'limit' => 100,
                'total' => 300,
                'total_pages' => 3,
            ], 200),
        ]);

        Bus::fake();

        $importAllLeads = app(ImportAllLeads::class);

        $batch = $importAllLeads->execute();

        $this->assertSame(
            'Leadscaptain Lead Import',
            $batch->name
        );

        Bus::assertBatched(function ($pendingBatch): bool {
            return $pendingBatch->name === 'Leadscaptain Lead Import'
                && count($pendingBatch->jobs) === 2
                && $pendingBatch->jobs[0] instanceof FetchLeadPageJob
                && $pendingBatch->jobs[1] instanceof FetchLeadPageJob;
        });

        Http::assertSent(function ($request): bool {
            return $request->url()
                === 'https://api.leadscaptain.com/leads?page=1&limit=100';
        });
    }

    public function test_it_dispatches_only_remaining_pages_after_first_page(): void
    {
        Http::fake([
            'https://api.leadscaptain.com/leads*' => Http::response([
                'data' => [],
                'page' => 1,
                'limit' => 100,
                'total' => 200,
                'total_pages' => 2,
            ], 200),
        ]);

        Bus::fake();

        $importAllLeads = app(ImportAllLeads::class);

        $importAllLeads->execute();

        Bus::assertBatched(function ($pendingBatch): bool {
            if (count($pendingBatch->jobs) !== 1) {
                return false;
            }

            /** @var FetchLeadPageJob $job */
            $job = $pendingBatch->jobs[0];

            return $job instanceof FetchLeadPageJob
                && $job->page === 2
                && $job->limit === 100;
        });
    }
}