<?php

declare(strict_types=1);

namespace Tests\Fixtures\Responses;

use OpenApi\Attributes as OA;

#[OA\Schema]
class OwnerResponse
{
    #[OA\Property(property: 'name', type: 'string', minLength: 3, maxLength: 5)]
    public string $name;

    #[OA\Property(property: 'email', type: 'string', format: 'email')]
    public string $email;

    #[OA\Property(property: 'uuid', type: 'string', format: 'uuid')]
    public string $uuid;

    #[OA\Property(property: 'birthday', type: 'string', format: 'date')]
    public string $birthday;

    #[OA\Property(property: 'createdAt', type: 'string', format: 'date-time')]
    public string $createdAt;

    #[OA\Property(property: 'code', type: 'string', minLength: 6, maxLength: 8, pattern: '^[a-c]{2}_[0-9]+$')]
    public string $code;

    #[OA\Property(property: 'score', type: 'number', exclusiveMinimum: 1.5, exclusiveMaximum: 3.5)]
    public float $score;

    #[OA\Property(property: 'active', type: 'boolean')]
    public bool $active;

    #[OA\Property(property: 'plan', type: 'string', default: 'free')]
    public string $plan;
}
