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
    ) {
    }

    public function execute(): Batch
    {
        /*
         * Fetch page 1 first.
         *
         * This gives us the actual number of pages available
         * before we dispatch the remaining page jobs.
         */
        $firstPage = $this->importLeadPage->execute(
            page: 1,
            limit: 100,
        );

        $jobs = [];

        /*
         * Each remaining page is handled by a separate queue job.
         *
         * Horizon can process these jobs concurrently using
         * multiple queue workers.
         */
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

        /*
         * Laravel Batch gives us:
         * - progress tracking
         * - failed job detection
         * - batch status
         * - failure callback
         */
        return Bus::batch($jobs)
            ->name('Leadscaptain Lead Import')
            ->allowFailures(false)
            ->catch([
                $this->handleFailure,
                'execute',
            ])
            ->dispatch();
    }
}