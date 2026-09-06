<?php

declare(strict_types=1);

namespace Tests\Fixtures\Security;

use OpenApi\Attributes as OA;

class ClassInheritedSecurityController
{
    #[OA\Get(path: '/inherited')]
    public function inherited(): void
    {
    }
}
