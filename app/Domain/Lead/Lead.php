<?php

declare(strict_types=1);

namespace App\Domain\Lead;

final readonly class Lead
{
    public function __construct(
        private string $id,
        private string $firstName,
        private string $lastName,
        private ?string $companyName,
        private ?string $positionTitle,
        private ?string $emailStatus,
        private ?string $linkedinUrl,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function companyName(): ?string
    {
        return $this->companyName;
    }

    public function positionTitle(): ?string
    {
        return $this->positionTitle;
    }

    public function emailStatus(): ?string
    {
        return $this->emailStatus;
    }

    public function linkedinUrl(): ?string
    {
        return $this->linkedinUrl;
    }
}