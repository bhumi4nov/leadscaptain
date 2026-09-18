<?php

declare(strict_types=1);

namespace App\Infrastructure\Leadscaptain;

use App\Domain\Lead\Lead;
use App\Domain\Lead\LeadRepository;
use App\Models\Lead as LeadModel;

final class DatabaseLeadRepository implements LeadRepository
{
    public function save(Lead $lead): void
        {
            LeadModel::updateOrCreate(
                [
                    'leadscaptain_id' => $lead->id(),
                ],
                [
                    'first_name' => $lead->firstName(),
                    'last_name' => $lead->lastName(),
                    'company_name' => $lead->companyName(),
                    'position_title' => $lead->positionTitle(),
                    'email_status' => $lead->emailStatus(),
                    'linkedin_url' => $lead->linkedinUrl(),
                ]
            );
        }
}