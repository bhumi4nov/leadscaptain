<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\FetchLeadPageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class FetchLeadPageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_the_requested_page(): void
    {
        Http::fake([
            'https://api.leadscaptain.com/leads*' => Http::response([
                'data' => [[
                    'leadscaptain_id' => 'job-lead-001',
                    'first_name' => 'Test',
                    'last_name' => 'Lead',
                    'company_name' => 'Test Company',
                    'position_title' => 'Developer',
                    'email_status' => 'verified',
                    'linkedin_url' => 'https://linkedin.com/in/test',
                ]],
                'page' => 2,
                'limit' => 100,
                'total' => 1,
                'total_pages' => 2,
            ], 200),
        ]);

        $job = new FetchLeadPageJob(
            page: 2,
            limit: 100,
        );

        $job->handle(app(\App\Application\Lead\ImportLeadPage::class));

        $this->assertDatabaseHas('leads', [
            'leadscaptain_id' => 'job-lead-001',
            'first_name' => 'Test',
            'last_name' => 'Lead',
        ]);

        Http::assertSent(function ($request): bool {
            return $request->url() ===
                'https://api.leadscaptain.com/leads?page=2&limit=100';
        });
    }
}