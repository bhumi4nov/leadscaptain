<?php

declare(strict_types=1);

namespace App\Infrastructure\Leadscaptain;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class LeadscaptainClient
{
    public function __construct(
        private readonly LeadscaptainRateLimiter $rateLimiter,
    ) {
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(
            rtrim((string) config('leadscaptain.base_url'), '/')
        )
            ->acceptJson()
            ->withHeaders([
                'X-API-Key' => (string) config('leadscaptain.api_key'),
            ])
            ->timeout((int) config('leadscaptain.timeout'));
    }

    /**
     * @return array<string, mixed>
     */
    public function getLeads(int $page = 1, int $limit = 100): array
    {
        $this->rateLimiter->waitIfNeeded();

        return $this->client()
            ->retry(
                (int) config('leadscaptain.retry_times'),
                function (
                    int $attempt,
                    \Throwable $exception
                ): int {
                    $delay = match ($attempt) {
                        1 => 1000,
                        2 => 5000,
                        default => 30000,
                    };

                    if ($exception instanceof RequestException) {
                        $response = $exception->response;

                        if ($response->status() === 429) {
                            $retryAfter = $response->header('Retry-After');

                            if (is_numeric($retryAfter)) {
                                $delay = max(
                                    0,
                                    (int) $retryAfter * 1000
                                );
                            }
                        }
                    }

                    Log::channel('leadscaptain')->warning(
                        'Leadscaptain request retry',
                        [
                            'attempt' => $attempt,
                            'delay_ms' => $delay,
                            'message' => $exception->getMessage(),
                        ]
                    );

                    return $delay;
                },
                function (\Throwable $exception): bool {
                    if ($exception instanceof RequestException) {
                        $status = $exception->response->status();

                        return $status === 429 || $status >= 500;
                    }

                    return true;
                },
            )
            ->get('/leads', [
                'page' => $page,
                'limit' => $limit,
            ])
            ->throw()
            ->json();
    }
}