<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ImportLeadscaptainCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_starts_lead_import_from_command(): void
    {
        Bus::fake();

        Http::fake([
            'https://api.leadscaptain.com/leads*' => Http::response([
                'data' => [],
                'page' => 1,
                'limit' => 100,
                'total' => 200,
                'total_pages' => 2,
            ], 200),
        ]);

        $this->artisan('leadscaptain:import')
            ->expectsOutput('Leadscaptain import started.')
            ->assertExitCode(0);

        Bus::assertBatched(function ($batch): bool {
            return $batch->name === 'Leadscaptain Lead Import'
                && count($batch->jobs) === 1;
        });
    }
}