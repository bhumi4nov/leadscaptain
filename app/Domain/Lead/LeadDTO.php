<?php

declare(strict_types=1);

namespace App\Domain\Lead;

final readonly class LeadDTO
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public ?string $companyName,
        public ?string $positionTitle,
        public ?string $emailStatus,
        public ?string $linkedinUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['leadscaptain_id'] ?? $data['key'] ?? ''),
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: (string) ($data['last_name'] ?? ''),
            companyName: isset($data['company_name'])
                ? (string) $data['company_name']
                : null,
            positionTitle: isset($data['position_title'])
                ? (string) $data['position_title']
                : null,
            emailStatus: isset($data['email_status'])
                ? (string) $data['email_status']
                : null,
            linkedinUrl: isset($data['linkedin_url'])
                ? (string) $data['linkedin_url']
                : null,
        );
    }

    public function toDomain(): Lead
    {
        return new Lead(
            id: $this->id,
            firstName: $this->firstName,
            lastName: $this->lastName,
            companyName: $this->companyName,
            positionTitle: $this->positionTitle,
            emailStatus: $this->emailStatus,
            linkedinUrl: $this->linkedinUrl,
        );
    }
}