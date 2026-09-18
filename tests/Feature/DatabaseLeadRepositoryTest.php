<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Lead\Lead;
use App\Infrastructure\Leadscaptain\DatabaseLeadRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DatabaseLeadRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_a_lead_to_the_database(): void
    {
        $lead = new Lead(
            id: 'lead-123',
            firstName: 'Abhishek',
            lastName: 'Kumar',
            companyName: 'Avancer Pvt Ltd',
            positionTitle: 'Manager',
            emailStatus: 'verified',
            linkedinUrl: 'https://linkedin.com/in/abhishek',
        );

        $repository = new DatabaseLeadRepository();

        $repository->save($lead);

        $this->assertDatabaseHas('leads', [
            'leadscaptain_id' => 'lead-123',
            'first_name' => 'Abhishek',
            'last_name' => 'Kumar',
            'company_name' => 'Avancer Pvt Ltd',
        ]);
    }

    public function test_it_updates_an_existing_lead_instead_of_creating_duplicate(): void
    {
        $repository = new DatabaseLeadRepository();

        $firstLead = new Lead(
            id: 'lead-123',
            firstName: 'Abhishek',
            lastName: 'Kumar',
            companyName: 'Avancer Pvt Ltd',
            positionTitle: 'Manager',
            emailStatus: 'verified',
            linkedinUrl: null,
        );

        $repository->save($firstLead);

        $updatedLead = new Lead(
            id: 'lead-123',
            firstName: 'Abhishek',
            lastName: 'Sharma',
            companyName: 'Avancer Pvt Ltd',
            positionTitle: 'Director',
            emailStatus: 'verified',
            linkedinUrl: null,
        );

        $repository->save($updatedLead);

        $this->assertDatabaseCount('leads', 1);

        $this->assertDatabaseHas('leads', [
            'leadscaptain_id' => 'lead-123',
            'last_name' => 'Sharma',
            'company_name' => 'Avancer Pvt Ltd',
            'position_title' => 'Director',
        ]);
    }
}