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

class ClassInheritedSecurityController
{
    #[OA\Get(path: '/inherited')]
    public function inherited(): void
    {
    }
}

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

class UndefinedSchemeSecurityController
{
    #[OA\Get(path: '/undefined', security: [[
        'unknownAuth' => [],
    ]])]
    public function undefinedScheme(): void
    {
    }
}
