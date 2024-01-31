<?php
declare(strict_types=1);

return [
    /*
    |---------------------------------------------------------------------------
    | namespace in which the OpenAPI specification is defined
    |---------------------------------------------------------------------------
    |
    | Specify the namespace of the controller in which the OpenAPI http method is defined in swagger-php
    | Specifies the name of a group that organizes multiple APIs and the namespace in which the Controller in which the API is implemented is located.
    */
    'namespaces' => [
        // Example 'group_name' => 'App\Http\Controllers'
        '' => '',
    ],

    /*
    |---------------------------------------------------------------------------
    | Output route definition class
    |---------------------------------------------------------------------------
    |
    | Specify the path to the root definition file automatically generated from the OpenAPI specification
    */
    'route_path' => base_path('routes/eg_r2.php'),
];
