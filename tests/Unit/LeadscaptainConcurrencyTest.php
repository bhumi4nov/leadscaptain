<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Leadscaptain\LeadscaptainClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class LeadscaptainConcurrencyTest extends TestCase
{
    public function test_it_fetches_multiple_pages_through_http_pool(): void
    {
        Redis::shouldReceive('eval')
            ->times(3)
            ->andReturn(0);

        Http::fake([
            'https://api.leadscaptain.com/leads*' => function ($request) {
                $data = $request->data();

                $page = (int) ($data['page'] ?? 1);

                return Http::response([
                    'data' => [],
                    'page' => $page,
                    'limit' => 100,
                    'total' => 300,
                    'total_pages' => 3,
                ], 200);
            },
        ]);

        $client = app(LeadscaptainClient::class);

        $responses = $client->getLeadsConcurrently(
            pages: [1, 2, 3],
            limit: 100,
        );

        $this->assertCount(3, $responses);

        $this->assertSame(
            [1, 2, 3],
            array_keys($responses)
        );

        foreach ($responses as $page => $response) {
            $this->assertTrue(
                $response->successful()
            );

            $this->assertSame(
                $page,
                $response->json('page')
            );
        }

        Http::assertSentCount(3);

        Http::assertSent(
            fn ($request): bool =>
                $request->url()
                === 'https://api.leadscaptain.com/leads?page=1&limit=100'
        );

        Http::assertSent(
            fn ($request): bool =>
                $request->url()
                === 'https://api.leadscaptain.com/leads?page=2&limit=100'
        );

        Http::assertSent(
            fn ($request): bool =>
                $request->url()
                === 'https://api.leadscaptain.com/leads?page=3&limit=100'
        );
    }

    public function test_leadscaptain_horizon_allows_concurrent_workers(): void
    {
        $maxProcesses = (int) config(
            'horizon.environments.local.supervisor-1.maxProcesses'
        );

        $this->assertGreaterThanOrEqual(
            10,
            $maxProcesses
        );
    }
}