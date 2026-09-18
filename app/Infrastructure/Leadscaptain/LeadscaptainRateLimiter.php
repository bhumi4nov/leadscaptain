<?php

declare(strict_types=1);

namespace App\Infrastructure\Leadscaptain;

use Illuminate\Support\Facades\Redis;

final class LeadscaptainRateLimiter
{
    private const LIMIT = 60;

    private const WINDOW_SECONDS = 60;

    public function waitIfNeeded(): void
    {
        $key = 'leadscaptain:request-timestamps';

        while (true) {
            $now = microtime(true);
            $windowStart = $now - self::WINDOW_SECONDS;

            Redis::zremrangebyscore(
                $key,
                '-inf',
                $windowStart,
            );

            $count = Redis::zcard($key);

            if ($count < self::LIMIT) {
                Redis::zadd(
                    $key,
                    $now,
                    uniqid('', true),
                );

                Redis::expire(
                    $key,
                    self::WINDOW_SECONDS + 5,
                );

                return;
            }

            $oldest = Redis::zrange($key, 0, 0, true);

            if ($oldest === []) {
                continue;
            }

            $oldestTimestamp = (float) array_values($oldest)[0];

            $sleepSeconds = max(
                0,
                ($oldestTimestamp + self::WINDOW_SECONDS) - microtime(true),
            );

            if ($sleepSeconds > 0) {
                usleep((int) ($sleepSeconds * 1_000_000));
            }
        }
    }
}