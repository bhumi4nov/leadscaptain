<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Leadscaptain\LeadscaptainRetryPolicy;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LeadscaptainRetryPolicyTest extends TestCase
{
    public function test_first_retry_waits_one_second(): void
    {
        $policy = new LeadscaptainRetryPolicy();

        $this->assertSame(
            1000,
            $policy->delay(1)
        );
    }

    public function test_second_retry_waits_five_seconds(): void
    {
        $policy = new LeadscaptainRetryPolicy();

        $this->assertSame(
            5000,
            $policy->delay(2)
        );
    }

    public function test_third_retry_waits_thirty_seconds(): void
    {
        $policy = new LeadscaptainRetryPolicy();

        $this->assertSame(
            30000,
            $policy->delay(3)
        );
    }

        public function test_429_uses_retry_after_header(): void
        {
            Http::fake([
                'https://example.test/*' => Http::response(
                    ['message' => 'Too many requests'],
                    429,
                    ['Retry-After' => '7']
                ),
            ]);

            $response = Http::get(
                'https://example.test/leads'
            );

            $policy = new LeadscaptainRetryPolicy();

            $this->assertSame(
                7000,
                $policy->delay(1, $response)
            );
        }
}