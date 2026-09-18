<?php

declare(strict_types=1);

namespace App\Infrastructure\Leadscaptain;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class LeadscaptainClient
{
    public function __construct(
        private readonly LeadscaptainRateLimiter $rateLimiter,
        private readonly LeadscaptainRetryPolicy $retryPolicy,
    ) {
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(
            rtrim(
                (string) config('leadscaptain.base_url'),
                '/'
            )
        )
            ->acceptJson()
            ->withHeaders([
                'X-API-Key' => (string) config(
                    'leadscaptain.api_key'
                ),
            ])
            ->timeout(
                (int) config('leadscaptain.timeout')
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function getLeads(
        int $page = 1,
        int $limit = 100
    ): array {
        $maxRetries = (int) config(
            'leadscaptain.retry_times',
            3
        );

        $maxAttempts = $maxRetries + 1;

        for (
            $attempt = 1;
            $attempt <= $maxAttempts;
            $attempt++
        ) {
            $this->rateLimiter->waitIfNeeded();

            Log::channel('leadscaptain')->info(
                'Leadscaptain request attempt',
                [
                    'attempt' => $attempt,
                    'page' => $page,
                    'limit' => $limit,
                ]
            );

            try {
                $response = $this->client()->get('/leads', [
                    'page' => $page,
                    'limit' => $limit,
                ]);
            } catch (ConnectionException $exception) {
                Log::channel('leadscaptain')->warning(
                    'Leadscaptain connection failure',
                    [
                        'attempt' => $attempt,
                        'page' => $page,
                        'message' => $exception->getMessage(),
                    ]
                );

                if ($attempt >= $maxAttempts) {
                    throw $exception;
                }

                $delay = $this->retryPolicy->delay($attempt);

                $this->logRetry(
                    $attempt,
                    $delay,
                    $page,
                    $exception->getMessage()
                );

                $this->sleep($delay);

                continue;
            }

            if ($response->successful()) {
                return (array) $response->json();
            }

            if (!$this->isRetryable($response)) {
                $response->throw();
            }

            if ($attempt >= $maxAttempts) {
                $response->throw();
            }

            $delay = $this->retryPolicy->delay(
                $attempt,
                $response
            );

            $this->logRetry(
                $attempt,
                $delay,
                $page,
                'HTTP ' . $response->status()
            );

            $this->sleep($delay);
        }

        throw new RuntimeException(
            'Leadscaptain request failed unexpectedly.'
        );
    }

    private function isRetryable(Response $response): bool
    {
        return $response->status() === 429
            || $response->serverError();
    }

    private function sleep(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    private function logRetry(
        int $attempt,
        int $delay,
        int $page,
        string $reason
    ): void {
        Log::channel('leadscaptain')->warning(
            'Leadscaptain request retry',
            [
                'attempt' => $attempt,
                'page' => $page,
                'delay_ms' => $delay,
                'reason' => $reason,
            ]
        );
    }
}