<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Lead\ImportAllLeads;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ImportAllLeadsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [60, 300, 1800];
    }

    public function handle(
        ImportAllLeads $importAllLeads
    ): void {
        $importAllLeads->execute();
    }
}