<?php
declare(strict_types=1);

return [
    /*
    |---------------------------------------------------------------------------
    | Namespace where the OpenAPI specification is defined
    |---------------------------------------------------------------------------
    |
    | Specify the namespace of the controller where the OpenAPI HTTP methods are defined using swagger-php.
    | This setting organizes multiple APIs into groups and specifies the namespace where the Controller implementing the API is located.
    | For example, you can map a group name to a namespace.
    */
    'namespaces' => [
        // Example 'group_name' => 'App\Http\Controllers'
        '' => '',
    ],

    /*
    |---------------------------------------------------------------------------
    | Output path for the Route Files
    |---------------------------------------------------------------------------
    |
    | Specify the path to the Route Files that is automatically generated from the OpenAPI specification.
    */
    'route_path' => base_path('routes/eg_r2.php'),

    /*
    |---------------------------------------------------------------------------
    | OpenAPI security to middleware mapping
    |---------------------------------------------------------------------------
    |
    | OpenAPI security scheme names are mapped to Laravel route middlewares.
    | For simple schemes, define '<schemeName>' => 'middleware' or list of middleware.
    | For AND requirements, you can define a composite key using '&&'.
    |
    | Example:
    | 'mapping' => [
    |     'bearerAuth' => ['auth:api', 'scope:{scopes}'],
    |     'apiKeyAuth' => 'auth.apikey',
    |     'bearerAuth&&apiKeyAuth' => ['auth:bearer_and_api'],
    | ],
    |
    | Composite key matching is order-insensitive when resolving from OpenAPI.
    | Middleware output order follows this config definition order.
    */
    'security' => [
        'mapping' => [],

        // Policy when mapping for a scheme does not exist: ignore | warning | error
        'undefined_scheme_policy' => 'ignore',

        // Policy when OpenAPI security has multiple requirement objects (OR): error | warning_first | warning_skip
        'multiple_requirements_policy' => 'error',
    ],
];
