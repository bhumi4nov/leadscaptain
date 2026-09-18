<?php

declare(strict_types=1);

namespace App\Application\Lead;

use App\Domain\Lead\LeadRepository;
use App\Infrastructure\Leadscaptain\LeadscaptainClient;
use App\Events\LeadImported;
use Illuminate\Support\Facades\Event;

final class ImportLeadPage
{
    public function __construct(
        private LeadscaptainClient $client,
        private LeadRepository $repository,
    ) {
    }

    public function execute(int $page = 1, int $limit = 100): LeadPageDTO
    {
        $response = $this->client->getLeads($page, $limit);

        $pageData = LeadPageDTO::fromArray($response);

        foreach ($pageData->leads as $leadDTO) {
            $lead = $leadDTO->toDomain();

            $this->repository->save($lead);

            Event::dispatch(
                new LeadImported($lead)
            );
        }

        return $pageData;
    }
}