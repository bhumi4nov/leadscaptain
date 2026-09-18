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

    public function test_it_rejects_missing_data(): void
    {
        $this->expectException(
            InvalidLeadscaptainResponse::class
        );

        LeadPageDTO::fromArray([
            'page' => 1,
            'limit' => 100,
            'total' => 0,
            'total_pages' => 0,
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

    public function test_it_rejects_non_array_lead_items(): void
    {
        $this->expectException(
            InvalidLeadscaptainResponse::class
        );

        LeadPageDTO::fromArray([
            'data' => [
                'invalid-lead',
            ],
            'page' => 1,
            'limit' => 100,
            'total' => 1,
            'total_pages' => 1,
        ]);
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

    public function test_it_rejects_non_numeric_pagination_fields(): void
    {
        $fields = [
            'page',
            'limit',
            'total',
            'total_pages',
        ];

        foreach ($fields as $field) {
            $response = [
                'data' => [],
                'page' => 1,
                'limit' => 100,
                'total' => 0,
                'total_pages' => 0,
            ];

            $response[$field] = 'invalid';

            try {
                LeadPageDTO::fromArray($response);

                $this->fail(
                    "Expected exception for field [{$field}]."
                );
            } catch (InvalidLeadscaptainResponse $exception) {
                $this->assertStringContainsString(
                    "field [{$field}] is invalid",
                    $exception->getMessage()
                );
            }
        }
    }

    public function test_it_rejects_page_zero(): void
    {
        $this->expectException(
            InvalidLeadscaptainResponse::class
        );

        LeadPageDTO::fromArray([
            'data' => [],
            'page' => 0,
            'limit' => 100,
            'total' => 0,
            'total_pages' => 0,
        ]);
    }

    public function test_it_rejects_negative_total(): void
    {
        $this->expectException(
            InvalidLeadscaptainResponse::class
        );

        LeadPageDTO::fromArray([
            'data' => [],
            'page' => 1,
            'limit' => 100,
            'total' => -1,
            'total_pages' => 0,
        ]);
    }

    public function test_it_rejects_negative_total_pages(): void
    {
        $this->expectException(
            InvalidLeadscaptainResponse::class
        );

        LeadPageDTO::fromArray([
            'data' => [],
            'page' => 1,
            'limit' => 100,
            'total' => 0,
            'total_pages' => -1,
        ]);
    }

    public function test_it_rejects_limit_above_100(): void
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

    public function test_it_rejects_limit_below_one(): void
    {
        $this->expectException(
            InvalidLeadscaptainResponse::class
        );

        LeadPageDTO::fromArray([
            'data' => [],
            'page' => 1,
            'limit' => 0,
            'total' => 0,
            'total_pages' => 0,
        ]);
    }
}