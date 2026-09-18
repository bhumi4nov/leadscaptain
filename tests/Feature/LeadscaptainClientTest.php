<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Leadscaptain\LeadscaptainClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LeadscaptainClientTest extends TestCase
{
    public function test_it_retries_when_leadscaptain_returns_server_error(): void
    {
        Http::fakeSequence()
            ->pushStatus(500)
            ->push([
                'data' => [
                    [
                        'leadscaptain_id' => 'lead-1',
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                    ],
                ],
                'page' => 1,
                'limit' => 100,
                'total' => 1,
                'total_pages' => 1,
            ]);

        $client = app(LeadscaptainClient::class);

        $response = $client->getLeads();

        $this->assertSame(
            'lead-1',
            $response['data'][0]['leadscaptain_id']
        );

        Http::assertSentCount(2);
    }

    public function test_it_retries_when_leadscaptain_returns_429(): void
    {
        Http::fakeSequence()
            ->push(
                [
                    'message' => 'Too many requests',
                ],
                429,
                [
                    'Retry-After' => '0',
                ]
            )
            ->push([
                'data' => [
                    [
                        'leadscaptain_id' => 'lead-429',
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                    ],
                ],
                'page' => 1,
                'limit' => 100,
                'total' => 1,
                'total_pages' => 1,
            ]);

        $client = app(LeadscaptainClient::class);

        $response = $client->getLeads();

        $this->assertSame(
            'lead-429',
            $response['data'][0]['leadscaptain_id']
        );

        Http::assertSentCount(2);
    }

    public function test_it_does_not_retry_client_errors(): void
    {
        Http::fake([
            'https://api.leadscaptain.com/leads*' => Http::response(
                [
                    'message' => 'Bad request',
                ],
                400
            ),
        ]);

        $client = app(LeadscaptainClient::class);

        $this->expectException(
            \Illuminate\Http\Client\RequestException::class
        );

        $client->getLeads();

        Http::assertSentCount(1);
    }
}