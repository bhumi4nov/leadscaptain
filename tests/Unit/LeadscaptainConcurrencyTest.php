<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class LeadscaptainConcurrencyTest extends TestCase
{
    public function test_leadscaptain_horizon_allows_concurrent_workers(): void
    {
        $maxProcesses = (int) config(
            'horizon.environments.local.supervisor-1.maxProcesses'
        );

        $this->assertGreaterThanOrEqual(
            10,
            $maxProcesses
        );
    }
}