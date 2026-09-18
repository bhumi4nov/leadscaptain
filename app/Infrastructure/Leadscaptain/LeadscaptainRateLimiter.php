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

            $script = <<<'LUA'
                local now = tonumber(ARGV[1])
                local window = tonumber(ARGV[2])
                local limit = tonumber(ARGV[3])
                local member = ARGV[4]

                redis.call(
                    'ZREMRANGEBYSCORE',
                    KEYS[1],
                    '-inf',
                    now - window
                )

                local count = redis.call(
                    'ZCARD',
                    KEYS[1]
                )

                if count < limit then
                    redis.call(
                        'ZADD',
                        KEYS[1],
                        now,
                        member
                    )

                    redis.call(
                        'EXPIRE',
                        KEYS[1],
                        window + 5
                    )

                    return 0
                end

                local oldest = redis.call(
                    'ZRANGE',
                    KEYS[1],
                    0,
                    0,
                    'WITHSCORES'
                )

                if #oldest == 0 then
                    return 0
                end

                return tonumber(oldest[2])
            LUA;

            $oldestTimestamp = Redis::eval(
                $script,
                1,
                $key,
                (string) $now,
                (string) self::WINDOW_SECONDS,
                (string) self::LIMIT,
                bin2hex(random_bytes(16)),
            );

            $oldestTimestamp = (float) $oldestTimestamp;

            if ($oldestTimestamp === 0.0) {
                return;
            }

            $sleepSeconds = max(
                0,
                ($oldestTimestamp + self::WINDOW_SECONDS)
                    - microtime(true),
            );

            if ($sleepSeconds > 0) {
                usleep((int) ($sleepSeconds * 1_000_000));
            }
        }
    }
}