<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Lead\ImportAllLeads;
use Illuminate\Console\Command;

final class ImportLeadscaptainCommand extends Command
{
    protected $signature = 'leadscaptain:import';

    protected $description = 'Import leads from Leadscaptain';

    public function handle(ImportAllLeads $importAllLeads): int
    {
        $batch = $importAllLeads->execute();

        $this->info(
            'Leadscaptain import started.'
        );

        $this->line(
            'Batch ID: ' . $batch->id
        );

        return self::SUCCESS;
    }
}