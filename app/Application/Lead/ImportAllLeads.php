<?php

declare(strict_types=1);

namespace App\Application\Lead;

use App\Jobs\FetchLeadPageJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

final class ImportAllLeads
{
    public function __construct(
        private readonly ImportLeadPage $importLeadPage,
        private readonly HandleLeadImportFailure $handleFailure,
    ) {}

    public function execute(): Batch
    {
        $firstPage = $this->importLeadPage->execute(
            page: 1,
            limit: 100,
        );

        $jobs = [];

        for (
            $page = 2;
            $page <= $firstPage->totalPages;
            $page++
        ) {
            $jobs[] = new FetchLeadPageJob(
                page: $page,
                limit: 100,
            );
        }

        return Bus::batch($jobs)
            ->name('Leadscaptain Lead Import')
            ->catch([
                $this->handleFailure,
                'execute',
            ])
            ->dispatch();
    }
}