<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ImportAllLeadsJob;
use Illuminate\Console\Command;

final class ImportLeadscaptainCommand extends Command
{
    protected $signature = 'leadscaptain:import';

    protected $description = 'Import leads from Leadscaptain';

    public function handle(): int
    {
        ImportAllLeadsJob::dispatch();

        $this->info(
            'Leadscaptain import has been queued successfully.'
        );

        return self::SUCCESS;
    }
}