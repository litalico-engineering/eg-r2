<?php

declare(strict_types=1);

namespace Tests\Fixtures\Security;

use OpenApi\Attributes as OA;

class UndefinedSchemeSecurityController
{
    #[OA\Get(path: '/undefined', security: [[
        'unknownAuth' => [],
    ]])]
    public function undefinedScheme(): void
    {
    }
}
