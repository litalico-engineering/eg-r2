<?php

declare(strict_types=1);

namespace Tests\Fixtures\Security;

use OpenApi\Attributes as OA;

class CompositeMultiScopesSecurityController
{
    #[OA\Get(path: '/composite-multi-scopes', security: [[
        'apiKeyAuth' => [],
        'bearerAuth' => ['read', 'write'],
    ]])]
    public function index(): void
    {
    }
}
