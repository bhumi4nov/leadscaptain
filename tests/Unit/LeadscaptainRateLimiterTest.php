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
        Redis::shouldReceive('zremrangebyscore')
            ->once();

        Redis::shouldReceive('zcard')
            ->once()
            ->andReturn(10);

        Redis::shouldReceive('zadd')
            ->once();

        Redis::shouldReceive('expire')
            ->once();

        $limiter = new LeadscaptainRateLimiter();

        $limiter->waitIfNeeded();

        $this->assertTrue(true);
    }
}