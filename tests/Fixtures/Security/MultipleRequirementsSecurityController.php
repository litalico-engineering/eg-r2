<?php

declare(strict_types=1);

namespace Tests\Fixtures\Security;

use OpenApi\Attributes as OA;

class MultipleRequirementsSecurityController
{
    #[OA\Get(path: '/multi', security: [
        ['bearerAuth' => ['read']],
        ['apiKeyAuth' => []],
    ])]
    public function multi(): void
    {
    }
}
