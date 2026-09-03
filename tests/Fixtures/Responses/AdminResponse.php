<?php

declare(strict_types=1);

namespace Tests\Fixtures\Responses;

use OpenApi\Attributes as OA;

#[OA\Schema(allOf: [
    new OA\Schema(ref: OwnerResponse::class),
    new OA\Schema(properties: [new OA\Property(property: 'role', type: 'string', example: 'admin')]),
])]
class AdminResponse
{
    #[OA\Property(property: 'permissions', type: 'array', minItems: 2, maxItems: 2, items: new OA\Items(type: 'string', anyOf: [
        new OA\Schema(type: 'string', enum: ['read']),
        new OA\Schema(type: 'string', enum: ['write']),
    ]))]
    public array $permissions;
}
