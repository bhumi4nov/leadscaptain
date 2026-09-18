<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Lead\ImportLeadPage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class FetchLeadPageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $page,
        public readonly int $limit = 100,
    ) {
    }

    public function handle(ImportLeadPage $importLeadPage): void
    {
        $importLeadPage->execute(
            page: $this->page,
            limit: $this->limit,
        );
    }
}