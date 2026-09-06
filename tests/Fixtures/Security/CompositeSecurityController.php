<?php

declare(strict_types=1);

namespace Tests\Fixtures\Security;

use OpenApi\Attributes as OA;

class CompositeSecurityController
{
    #[OA\Get(path: '/composite', security: [[
        'apiKeyAuth' => [],
        'bearerAuth' => ['read'],
    ]])]
    public function index(): void
    {
    }
}
