<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Lead\LeadPageDTO;
use App\Exceptions\InvalidLeadscaptainResponse;
use PHPUnit\Framework\TestCase;

final class LeadPageDTOTest extends TestCase
{
    public function test_it_maps_a_valid_response(): void
    {
        $dto = LeadPageDTO::fromArray([
            'data' => [
                [
                    'leadscaptain_id' => 'lead-1',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                ],
            ],
            'page' => 1,
            'limit' => 100,
            'total' => 1,
            'total_pages' => 1,
        ]);

        $this->assertSame(1, $dto->page);
        $this->assertSame(100, $dto->limit);
        $this->assertSame(1, $dto->total);
        $this->assertSame(1, $dto->totalPages);
        $this->assertCount(1, $dto->leads);
    }

    public function test_it_rejects_missing_pagination_fields(): void
    {
        $this->expectException(
            InvalidLeadscaptainResponse::class
        );

        LeadPageDTO::fromArray([
            'data' => [],
            'page' => 1,
        ]);
    }

    public function test_it_rejects_invalid_data_structure(): void
    {
        $this->expectException(
            InvalidLeadscaptainResponse::class
        );

        LeadPageDTO::fromArray([
            'data' => 'invalid',
            'page' => 1,
            'limit' => 100,
            'total' => 0,
            'total_pages' => 0,
        ]);
    }

    public function test_it_rejects_invalid_limit(): void
    {
        $this->expectException(
            InvalidLeadscaptainResponse::class
        );

        LeadPageDTO::fromArray([
            'data' => [],
            'page' => 1,
            'limit' => 101,
            'total' => 0,
            'total_pages' => 0,
        ]);
    }
}