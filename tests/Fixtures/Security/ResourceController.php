<?php

declare(strict_types=1);

namespace Tests\Fixtures\Security;

use OpenApi\Attributes as OA;

class ResourceController
{
    #[OA\Get(
        path: '/resources/{id}',
        operationId: 'showResource',
    )]
    public function show(): void
    {
    }

    #[OA\Post(
        path: '/resources',
        operationId: 'createResource',
    )]
    public function create(): void
    {
    }

    #[OA\Put(
        path: '/resources/{id}',
        operationId: 'updateResource',
    )]
    public function update(): void
    {
    }
}
