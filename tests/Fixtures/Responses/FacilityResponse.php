<?php

declare(strict_types=1);

namespace Tests\Fixtures\Responses;

use OpenApi\Attributes as OA;

#[OA\Schema]
class FacilityResponse
{
    #[OA\Property(property: 'id', type: 'integer', minimum: 1, maximum: 10)]
    public int $id;

    #[OA\Property(property: 'status', enum: FacilityStatus::class)]
    public string $status;

    #[OA\Property(property: 'name', type: 'string', example: 'Central')]
    public string $name;

    #[OA\Property(property: 'owner', ref: OwnerResponse::class)]
    public OwnerResponse $owner;

    #[OA\Property(property: 'contacts', type: 'array', minItems: 1, maxItems: 3, items: new OA\Items(ref: OwnerResponse::class))]
    public array $contacts;

    #[OA\Property(property: 'kind', oneOf: [
        new OA\Schema(type: 'integer', minimum: 7, maximum: 7),
        new OA\Schema(type: 'string', enum: ['branch']),
    ])]
    public int|string $kind;

    #[OA\Property(property: 'secret', type: 'string', writeOnly: true)]
    public string $secret;

    #[OA\Property(property: 'parent', ref: self::class, nullable: true)]
    public ?FacilityResponse $parent;

    #[OA\Property(property: 'children', type: 'array', items: new OA\Items(ref: self::class))]
    public array $children;
}
