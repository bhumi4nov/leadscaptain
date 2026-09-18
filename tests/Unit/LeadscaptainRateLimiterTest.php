<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Leadscaptain\LeadscaptainRateLimiter;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class LeadscaptainRateLimiterTest extends TestCase
{
    public function test_it_allows_a_request_when_rate_limit_has_not_been_reached(): void
    {
        Redis::shouldReceive('eval')
            ->once()
            ->andReturn(0);

        $limiter = new LeadscaptainRateLimiter();

        $limiter->waitIfNeeded();

        $this->assertTrue(true);
    }

    public function test_it_waits_when_rate_limit_has_been_reached(): void
    {
        $oldestTimestamp = microtime(true) - 59.999;

        Redis::shouldReceive('eval')
            ->twice()
            ->andReturn(
                $oldestTimestamp,
                0
            );

        $limiter = new LeadscaptainRateLimiter();

        $start = microtime(true);

        $limiter->waitIfNeeded();

        $elapsed = microtime(true) - $start;

        $this->assertGreaterThan(0, $elapsed);
    }
}