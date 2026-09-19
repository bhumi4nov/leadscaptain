<?php

declare(strict_types=1);

namespace App\Infrastructure\Leadscaptain;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Throwable;

final class LeadscaptainClient
{
    public function __construct(
        private readonly LeadscaptainRateLimiter $rateLimiter,
        private readonly LeadscaptainRetryPolicy $retryPolicy,
    ) {
    }

    /**
     * Fetch a single page.
     *
     * @return array<string, mixed>
     */
    public function getLeads(
        int $page = 1,
        int $limit = 100
    ): array {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $this->rateLimiter->waitIfNeeded();

                $startedAt = microtime(true);

                Log::channel('leadscaptain')->info(
                    'Leadscaptain request started',
                    [
                        'page' => $page,
                        'limit' => $limit,
                        'attempt' => $attempt,
                    ]
                );

                $response = $this->request(
                    page: $page,
                    limit: $limit,
                );

                Log::channel('leadscaptain')->info(
                    'Leadscaptain request completed',
                    [
                        'page' => $page,
                        'limit' => $limit,
                        'attempt' => $attempt,
                        'status' => $response->status(),
                        'duration_ms' => $this->durationMs($startedAt),
                    ]
                );

                if ($response->successful()) {
                    /** @var array<string, mixed> $data */
                    $data = $response->json();

                    return $data;
                }

                if (
                    $response->status() !== 429
                    && !$response->serverError()
                ) {
                    $response->throw();
                }

                if ($attempt >= $this->maxAttempts()) {
                    throw new RuntimeException(
                        sprintf(
                            'Leadscaptain request failed after %d attempts for page %d.',
                            $attempt,
                            $page
                        )
                    );
                }

                $delay = $this->retryPolicy->delay(
                    attempt: $attempt,
                    response: $response,
                );

                Log::channel('leadscaptain')->warning(
                    'Leadscaptain request will retry',
                    [
                        'page' => $page,
                        'limit' => $limit,
                        'attempt' => $attempt,
                        'status' => $response->status(),
                        'delay_ms' => $delay,
                        'reason' => 'HTTP ' . $response->status(),
                    ]
                );

                usleep($delay * 1000);
            } catch (ConnectionException $exception) {
                if ($attempt >= $this->maxAttempts()) {
                    throw $exception;
                }

                $delay = $this->retryPolicy->delay(
                    attempt: $attempt,
                    response: null,
                );

                Log::channel('leadscaptain')->warning(
                    'Leadscaptain connection failure, retrying',
                    [
                        'page' => $page,
                        'limit' => $limit,
                        'attempt' => $attempt,
                        'delay_ms' => $delay,
                        'reason' => $exception->getMessage(),
                    ]
                );

                usleep($delay * 1000);
            }
        }
    }

    /**
     * Fetch multiple pages concurrently.
     *
     * @param list<int> $pages
     * @return array<int, Response>
     */
    public function getLeadsConcurrently(
        array $pages,
        int $limit = 100
    ): array {
        $maxConcurrency = max(
            1,
            (int) config(
                'leadscaptain.concurrency',
                10
            )
        );

        $results = [];

        foreach (
            array_chunk($pages, $maxConcurrency) as $pageChunk
        ) {
            $chunkResults = $this->requestPageChunk(
                pages: $pageChunk,
                limit: $limit,
            );

            foreach ($chunkResults as $page => $response) {
                $results[$page] = $response;
            }
        }

        ksort($results);

        return $results;
    }

    /**
     * @param list<int> $pages
     * @return array<int, Response>
     */
    private function requestPageChunk(
        array $pages,
        int $limit
    ): array {
        $startedAt = [];

        foreach ($pages as $page) {
            $this->rateLimiter->waitIfNeeded();

            $startedAt[$page] = microtime(true);

            Log::channel('leadscaptain')->info(
                'Leadscaptain concurrent request started',
                [
                    'page' => $page,
                    'limit' => $limit,
                ]
            );
        }

        try {
            $responses = Http::pool(
                function (Pool $pool) use (
                    $pages,
                    $limit
                ): array {
                    $requests = [];

                    foreach ($pages as $page) {
                        $requests[] = $pool
                            ->as('page_' . $page)
                            ->withHeaders([
                                'X-API-Key' => (string) config(
                                    'leadscaptain.api_key'
                                ),
                            ])
                            ->acceptJson()
                            ->timeout(
                                (int) config(
                                    'leadscaptain.timeout',
                                    30
                                )
                            )
                            ->get(
                                rtrim(
                                    (string) config(
                                        'leadscaptain.base_url'
                                    ),
                                    '/'
                                ) . '/leads',
                                [
                                    'page' => $page,
                                    'limit' => $limit,
                                ]
                            );
                    }

                    return $requests;
                }
            );
        } catch (Throwable $exception) {
            Log::channel('leadscaptain')->error(
                'Leadscaptain concurrent pool failed',
                [
                    'pages' => $pages,
                    'limit' => $limit,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]
            );

            throw $exception;
        }

        $result = [];

        foreach ($pages as $page) {
            $poolKey = 'page_' . $page;

            $response = $responses[$poolKey] ?? null;

            /*
             * Laravel HTTP pool may return a Throwable for a
             * failed pooled request instead of a Response.
             */
            if ($response instanceof Throwable) {
                Log::channel('leadscaptain')->warning(
                    'Leadscaptain concurrent request failed',
                    [
                        'page' => $page,
                        'limit' => $limit,
                        'exception' => $response::class,
                        'message' => $response->getMessage(),
                        'duration_ms' => $this->durationMs(
                            $startedAt[$page]
                        ),
                    ]
                );

                $result[$page] = $this->retryPage(
                    page: $page,
                    limit: $limit,
                    response: null,
                    reason: $response->getMessage(),
                );

                continue;
            }

            if (!$response instanceof Response) {
                throw new RuntimeException(
                    sprintf(
                        'Invalid response for Leadscaptain page %d. Got: %s',
                        $page,
                        get_debug_type($response)
                    )
                );
            }

            Log::channel('leadscaptain')->info(
                'Leadscaptain concurrent request completed',
                [
                    'page' => $page,
                    'limit' => $limit,
                    'status' => $response->status(),
                    'duration_ms' => $this->durationMs(
                        $startedAt[$page]
                    ),
                ]
            );

            if ($response->successful()) {
                $result[$page] = $response;

                continue;
            }

            if (
                $response->status() === 429
                || $response->serverError()
            ) {
                $result[$page] = $this->retryPage(
                    page: $page,
                    limit: $limit,
                    response: $response,
                    reason: 'HTTP ' . $response->status(),
                );

                continue;
            }

            throw new RuntimeException(
                sprintf(
                    'Leadscaptain request failed for page %d with HTTP %d.',
                    $page,
                    $response->status()
                )
            );
        }

        return $result;
    }

    private function request(
        int $page,
        int $limit
    ): Response {
        return Http::withHeaders([
            'X-API-Key' => (string) config(
                'leadscaptain.api_key'
            ),
        ])
            ->acceptJson()
            ->timeout(
                (int) config(
                    'leadscaptain.timeout',
                    30
                )
            )
            ->get(
                rtrim(
                    (string) config(
                        'leadscaptain.base_url'
                    ),
                    '/'
                ) . '/leads',
                [
                    'page' => $page,
                    'limit' => $limit,
                ]
            );
    }

    private function retryPage(
        int $page,
        int $limit,
        ?Response $response,
        string $reason
    ): Response {
        $attempt = 1;

        while ($attempt < $this->maxAttempts()) {
            $delay = $this->retryPolicy->delay(
                attempt: $attempt,
                response: $response,
            );

            Log::channel('leadscaptain')->warning(
                'Leadscaptain page retry scheduled',
                [
                    'page' => $page,
                    'limit' => $limit,
                    'attempt' => $attempt,
                    'delay_ms' => $delay,
                    'reason' => $reason,
                ]
            );

            usleep($delay * 1000);

            $attempt++;

            try {
                $this->rateLimiter->waitIfNeeded();

                $startedAt = microtime(true);

                $response = $this->request(
                    page: $page,
                    limit: $limit,
                );

                Log::channel('leadscaptain')->info(
                    'Leadscaptain retry completed',
                    [
                        'page' => $page,
                        'limit' => $limit,
                        'attempt' => $attempt,
                        'status' => $response->status(),
                        'duration_ms' => $this->durationMs($startedAt),
                    ]
                );

                if ($response->successful()) {
                    return $response;
                }

                $reason = 'HTTP ' . $response->status();
            } catch (ConnectionException $exception) {
                $response = null;
                $reason = $exception->getMessage();

                Log::channel('leadscaptain')->warning(
                    'Leadscaptain retry connection failure',
                    [
                        'page' => $page,
                        'limit' => $limit,
                        'attempt' => $attempt,
                        'reason' => $reason,
                    ]
                );
            }
        }

        throw new RuntimeException(
            sprintf(
                'Leadscaptain page %d failed after %d attempts. Reason: %s',
                $page,
                $this->maxAttempts(),
                $reason
            )
        );
    }

    private function maxAttempts(): int
    {
        return max(
            1,
            (int) config(
                'leadscaptain.retry_times',
                3
            )
        );
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round(
            (microtime(true) - $startedAt) * 1000
        );
    }
}