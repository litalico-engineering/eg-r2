![test passsed](https://github.com/litalico-engineering/eg-r2/actions/workflows/test.yml/badge.svg)

# Easy request validation and route generation from open API specifications (for Laravel)

`eg-r2` means `eg` in the sense that it `Easy(eg)` the `two R(r2)`s `Request validation` and `Routing generation`.

## Installation

1. composer install
    ```console
    composer require litalico-engineering/eg-r2
    ```
2. vendor publish  
    ```console
    php artisan vendor:publish --provider="Litalico\EgR2\Providers\GenerateRouteServiceProvider"
    ```

## Usage

1. Add [swagger-php](https://github.com/zircote/swagger-php) attributes to the classes (Controller and FormRequest) corresponding to each API to create an OpenAPI document.  
see. https://zircote.github.io/swagger-php/guide/attributes.html  

> [!IMPORTANT]  
> No need to define routing for Controller methods

2. Configure the `config/eg-r2.php`  
Describe the namespace of the Controller that describes the OpenAPI Attribute
3. Generate Route Files  
    ```console
    php artisan eg-r2:generate-route
    ```
