<?php

declare(strict_types=1);

namespace Tests\Fixtures\Security;

use Attribute;
use OpenApi\Attributes as OA;

#[Attribute(Attribute::TARGET_CLASS)]
class ControllerSecurity
{
    /**
     * @param array<int, array<string, array<int, string>>> $security
     */
    public function __construct(public array $security)
    {
    }
}

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

#[ControllerSecurity([
    [
        'bearerAuth' => ['write'],
    ],
])]
class ClassSecurityController
{
    #[OA\Get(path: '/class-inherited')]
    public function inherited(): void
    {
    }

    #[OA\Get(path: '/class-override-empty', security: [])]
    public function overrideEmpty(): void
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
