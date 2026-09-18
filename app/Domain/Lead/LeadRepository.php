<?php

declare(strict_types=1);

namespace App\Domain\Lead;

interface LeadRepository
{
    public function save(Lead $lead): void;
}