<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Lead\ImportAllLeads;
use App\Jobs\FetchLeadPageJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ImportAllLeadsTest extends TestCase
{
    public function test_it_fetches_first_page_and_dispatches_remaining_pages(): void
    {
        Bus::fake();

        Http::fake([
            'https://api.leadscaptain.com/leads*' => Http::response([
                'data' => [],
                'page' => 1,
                'limit' => 100,
                'total' => 500,
                'total_pages' => 5,
            ], 200),
        ]);

        $useCase = app(ImportAllLeads::class);

        $useCase->execute();

        Bus::assertBatched(function ($batch): bool {
            return $batch->name === 'Leadscaptain Lead Import'
                && count($batch->jobs) === 4;
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.leadscaptain.com/leads?page=1&limit=100';
        });
    }
}