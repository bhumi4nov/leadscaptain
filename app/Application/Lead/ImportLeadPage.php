<?php

declare(strict_types=1);

namespace App\Application\Lead;

use App\Domain\Lead\LeadRepository;
use App\Events\LeadImported;
use App\Infrastructure\Leadscaptain\LeadscaptainClient;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class ImportLeadPage
{
    public function __construct(
        private readonly LeadscaptainClient $client,
        private readonly LeadRepository $repository,
    ) {
    }

    public function execute(
        int $page = 1,
        int $limit = 100
    ): LeadPageDTO {
        $responses = $this->client->getLeadsConcurrently(
            pages: [$page],
            limit: $limit,
        );

        $response = $responses[$page]
            ?? throw new RuntimeException(
                "Leadscaptain response for page {$page} was not returned."
            );

        $pageData = LeadPageDTO::fromArray(
            $response->json()
        );

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