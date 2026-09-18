<?php

declare(strict_types=1);

namespace App\Application\Lead;

use App\Domain\Lead\LeadDTO;
use App\Exceptions\InvalidLeadscaptainResponse;

final readonly class LeadPageDTO
{
    /**
     * @param list<LeadDTO> $leads
     */
    public function __construct(
        public array $leads,
        public int $page,
        public int $limit,
        public int $total,
        public int $totalPages,
    ) {
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function fromArray(array $response): self
    {
        if (!array_key_exists('data', $response)
            || !is_array($response['data'])) {
            throw new InvalidLeadscaptainResponse(
                'Leadscaptain response must contain a data array.'
            );
        }

        $requiredFields = [
            'page',
            'limit',
            'total',
            'total_pages',
        ];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $response)
                || !is_numeric($response[$field])) {
                throw new InvalidLeadscaptainResponse(
                    "Leadscaptain response field [{$field}] is invalid."
                );
            }
        }

        $leads = [];

        foreach ($response['data'] as $lead) {
            if (!is_array($lead)) {
                throw new InvalidLeadscaptainResponse(
                    'Each item in the data array must be an object.'
                );
            }

            $leads[] = LeadDTO::fromArray($lead);
        }

        $page = (int) $response['page'];
        $limit = (int) $response['limit'];
        $total = (int) $response['total'];
        $totalPages = (int) $response['total_pages'];

        if ($page < 1) {
            throw new InvalidLeadscaptainResponse(
                'Response page must be greater than zero.'
            );
        }

        if ($limit < 1 || $limit > 100) {
            throw new InvalidLeadscaptainResponse(
                'Response limit must be between 1 and 100.'
            );
        }

        if ($total < 0 || $totalPages < 0) {
            throw new InvalidLeadscaptainResponse(
                'Response pagination values cannot be negative.'
            );
        }

        return new self(
            leads: $leads,
            page: $page,
            limit: $limit,
            total: $total,
            totalPages: $totalPages,
        );
    }
}