<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Lead\ImportLeadPage;
use App\Events\LeadImported;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ImportLeadPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_leads_from_a_page(): void
    {
        Event::fake();

        Http::fake([
            'https://api.leadscaptain.com/leads*' => Http::response([
                'data' => [
                    [
                        'leadscaptain_id' => 'lead-001',
                        'first_name' => 'Abhishek',
                        'last_name' => 'Kumar',
                        'company_name' => 'Avancer Pvt Ltd',
                        'position_title' => 'Developer',
                        'email_status' => 'verified',
                        'linkedin_url' => 'https://linkedin.com/in/abhishek',
                    ],
                ],
                'page' => 1,
                'limit' => 100,
                'total' => 1,
                'total_pages' => 1,
            ], 200),
        ]);

        $useCase = app(ImportLeadPage::class);

        $result = $useCase->execute(
            page: 1,
            limit: 100
        );

        $this->assertSame(1, $result->totalPages);
        $this->assertCount(1, $result->leads);

        $this->assertDatabaseHas('leads', [
            'leadscaptain_id' => 'lead-001',
            'first_name' => 'Abhishek',
            'last_name' => 'Kumar',
            'company_name' => 'Avancer Pvt Ltd',
        ]);

        Http::assertSent(function ($request): bool {
            return $request->url()
                === 'https://api.leadscaptain.com/leads?page=1&limit=100';
        });

        Event::assertDispatched(
            LeadImported::class,
            1
        );
    }
}