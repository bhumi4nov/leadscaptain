<?php

declare(strict_types=1);

namespace App\Infrastructure\Leadscaptain;

use Illuminate\Http\Client\Response;

final class LeadscaptainRetryPolicy
{
    public function delay(
        int $attempt,
        ?Response $response = null
    ): int {
        if ($response?->status() === 429) {
            $retryAfter = $response->header('Retry-After');

            if (is_numeric($retryAfter)) {
                return max(0, (int) $retryAfter * 1000);
            }
        }

        return match ($attempt) {
            1 => 1000,
            2 => 5000,
            default => 30000,
        };
    }
}