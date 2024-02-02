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
];
