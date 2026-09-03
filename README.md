![test passed](https://github.com/litalico-engineering/eg-r2/actions/workflows/test.yml/badge.svg)

# Easy request validation and route generation from open API specifications (for Laravel)

`eg-r2` means `eg` in the sense that it makes `Easy(eg)` the `two R(r2)`s: `Request validation` and `Routing generation`.

## Installation

1. Install via Composer
    ```console
    composer require litalico-engineering/eg-r2
    ```

2. (Optional) Publish configuration file
    ```console
    php artisan vendor:publish --provider="Litalico\EgR2\Providers\EgR2ServiceProvider" --tag=eg-r2-config
    ```
    This creates `config/eg_r2.php` for customization. If not published, default configuration will be used.

3. (Optional) Publish language files
    ```console
    php artisan vendor:publish --provider="Litalico\EgR2\Providers\EgR2ServiceProvider" --tag=eg-r2-lang
    ```
    This copies language files to `resources/lang/vendor/eg_r2/` for customization. Default language files (Japanese and English) are automatically loaded from the package.

## Usage

### Basic Setup

1. Add [swagger-php](https://github.com/zircote/swagger-php) attributes to the classes (Controller and FormRequest) corresponding to each API to create an OpenAPI document.  
see. https://zircote.github.io/swagger-php/guide/attributes.html  

> [!IMPORTANT]  
> No need to define routing for Controller methods

2. Configure the `config/eg_r2.php` (if you published it in step 2 of Installation)  
Describe the namespace of the Controller that describes the OpenAPI Attribute.  
If you didn't publish the config file, you can create it manually at `config/eg_r2.php`:
    ```php
    <?php
    
    return [
        'namespaces' => [
            'App\\Http\\Controllers',
        ],
        'route_path' => base_path('routes/eg_r2.php'),
        'security' => [
            'mapping' => [
                'bearerAuth' => ['auth:api', 'scope:{scopes}'],
                'apiKeyAuth' => 'auth.apikey',
                'bearerAuth&&apiKeyAuth' => ['auth:bearer_and_api'],
            ],
            'undefined_scheme_policy' => 'ignore',
            'multiple_requirements_policy' => 'error',
        ],
    ];
    ```

3. Generate Route Files  
    ```console
    php artisan eg-r2:generate-route
    ```

### Security Middleware Mapping

`eg-r2:generate-route` can convert OpenAPI `security` definitions into Laravel route middleware.

- Single requirement object with multiple schemes is treated as `AND`.
- Multiple requirement objects are OpenAPI `OR`, and controlled by `security.multiple_requirements_policy`.

Configuration keys:

- `security.mapping`
    - Maps OpenAPI scheme names to middleware (`string` or `string[]`).
    - `{scopes}` placeholder is replaced with comma-separated scopes from OpenAPI.
    - AND composite mapping can be defined with `&&` (for example, `bearerAuth&&apiKeyAuth`).
    - Composite key matching is order-insensitive (`apiKeyAuth&&bearerAuth` equals `bearerAuth&&apiKeyAuth`).
    - Middleware output order follows your config definition order.
- `security.undefined_scheme_policy`
    - `ignore`: skip unknown schemes.
    - `warning`: output warning and skip.
    - `error`: fail route generation.
- `security.multiple_requirements_policy`
    - `error`: fail route generation.
    - `warning_first`: warn and use only the first requirement object.
    - `warning_skip`: warn and skip middleware generation for that operation.

Example for a real project:

```php
'security' => [
    'mapping' => [
        // Standard bearer token endpoints
        'bearerAuth' => ['auth:api', 'scope:{scopes}'],

        // Internal API key endpoints
        'apiKeyAuth' => 'auth.internal_api_key',

        // Endpoints that require both bearer token and internal API key
        'bearerAuth&&apiKeyAuth' => [
            'auth:bearer_and_internal_key',
            'scope:{scopes}',
        ],
    ],
    'undefined_scheme_policy' => 'error',
    'multiple_requirements_policy' => 'warning_skip',
],
```

In this example:

- `bearerAuth` routes become `auth:api` and `scope:{scopes}`.
- `apiKeyAuth` routes become `auth.internal_api_key`.
- `bearerAuth&&apiKeyAuth` matches the same OpenAPI requirement even if the schemes are written in reverse order.
- `warning_skip` lets route generation continue for OpenAPI `OR` security definitions while leaving those routes without generated security middleware.

### Auto-generating FormRequest attributes() Method

The `RequestAttributesGeneratorTrait` automatically generates the `attributes()` method from OpenAPI `#[Property]` attributes:

```php
use Illuminate\Foundation\Http\FormRequest;
use Litalico\EgR2\Http\Requests\RequestAttributesGeneratorTrait;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Items;

class MyFormRequest extends FormRequest
{
    use RequestAttributesGeneratorTrait;
    
    #[Property(
        property: 'facilityCode',
        description: '事業所コード',
        type: 'string'
    )]
    public string $facilityCode;
    
    #[Property(
        property: 'calculateTargets',
        description: '計算対象配列',
        type: 'array',
        items: new Items(
            properties: [
                new Property(
                    property: 'billingCode',
                    description: '請求記録コード',
                    type: 'string'
                ),
            ]
        )
    )]
    public array $calculateTargets;
    
    // Trait automatically provides attributes() method
    // Or you can override it to customize specific attributes
    public function attributes(): array
    {
        return array_merge($this->generatedAttributes(), [
            'facilityCode' => 'Custom facility code label',
        ]);
    }
}
```

The trait generates validation message keys with proper formatting:
- Simple properties: `facilityCode`
- Array items: `calculateTargets.*`
- Nested array properties: `calculateTargets.*.billingCode` (with `:position` placeholder for row numbers)

#### Attribute Resolution Priority

For each property, the trait uses:
1. **Description field** (highest priority) - typically in Japanese
2. **Title field** - if description is not set
3. **Property name** - if neither description nor title is set

#### Array Item Formatting

For properties inside array items, the trait automatically:
- Adds the `:position` placeholder for row number substitution
- Formats as: `{arrayName}の :position 行目の「{propertyDescription}」`
- Generates both `{arrayName}.*` and `{arrayName}.*.{propertyName}` keys

#### Multi-Language Support

The trait supports multiple languages based on Laravel's `app.locale` and `app.fallback_locale` configuration. Language files are automatically loaded from the package, so no additional setup is required.

**Supported Languages:**
- English (`en`)
- Japanese (`ja`)

**Configuration:**

The package respects Laravel's locale configuration:
- `config('app.locale')` - Current application locale (Laravel default: `en`)
- `config('app.fallback_locale')` - Fallback locale when translations are not found (Laravel default: `en`)

**Examples:**

Japanese (`config('app.locale', 'ja')`):
```
items.* => 'itemsの各項目'
items.*.code => 'itemsの :position 行目の「code」'
```

English (`config('app.locale', 'en')`):
```
items.* => 'Each item of items'
items.*.code => 'Row :position of items: "code"'
```

**Fallback Behavior:**

If a translation is not found for the current locale, the package falls back to `config('app.fallback_locale')`. 

Example:
- Current locale: `fr` (French) - not supported
- Fallback locale: `en` (Laravel default) or `ja` (if configured)
- Result: Translations from the fallback locale will be used

To use Japanese as the default fallback, configure it in your `config/app.php`:
```php
'locale' => 'ja',           // Set current locale to Japanese
'fallback_locale' => 'ja',  // Set fallback locale to Japanese
```

**Customizing Language Files:**

If you want to customize the default messages:

1. Publish the language files:
    ```console
    php artisan vendor:publish --provider="Litalico\EgR2\Providers\EgR2ServiceProvider" --tag=eg-r2-lang
    ```

2. Edit the published files in `resources/lang/vendor/eg_r2/{locale}/eg_r2.php`

**Adding New Languages:**

To add support for a new language:

1. Publish the language files (if not already done)
2. Create a new language file at `resources/lang/vendor/eg_r2/{locale}/eg_r2.php`:

```php
<?php

declare(strict_types=1);

return [
    'array_items' => '{locale-specific format for array items}',
    'nested_array_item' => '{locale-specific format for nested item}',
    'nested_array_items' => '{locale-specific format for nested array items}',
];
```

Example for French (`resources/lang/vendor/eg_r2/fr/eg_r2.php`):
```php
<?php

declare(strict_types=1);

return [
    'array_items' => 'Chaque élément de :description',
    'nested_array_item' => 'Ligne :position de :arrayName: ":description"',
    'nested_array_items' => 'Chaque élément de la ligne :position de :arrayName: ":description"',
];
```

**Available Placeholders:**
- `:description` - The field description from OpenAPI attributes
- `:arrayName` - The parent array field name
- `:position` - Row number placeholder (replaced by Laravel during validation)

If the current locale is not supported, the package will fallback to the configured `app.fallback_locale` (Laravel default: `en`).

### Generating response examples

`ResponseExampleGenerator` builds a random example payload from an OpenAPI schema in a document scanned by swagger-php.
`$ref`, `allOf`, `oneOf`, and `anyOf` are resolved against that document, so response DTOs can be composed the same way as in the published specification.

```php
use Litalico\EgR2\Services\ResponseExampleGenerator;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;

#[Schema]
final class FacilityResponse
{
    #[Property(property: 'id', type: 'integer', minimum: 1)]
    public int $id;

    #[Property(property: 'owner', ref: OwnerResponse::class)]
    public OwnerResponse $owner;

    #[Property(property: 'contacts', type: 'array', minItems: 1, maxItems: 3, items: new Items(ref: OwnerResponse::class))]
    public array $contacts;
}

$openApi = (new Generator())->generate([app_path('Http/Responses')]);
$generator = new ResponseExampleGenerator($openApi);

$json = json_encode($generator->generateForClass(FacilityResponse::class), JSON_THROW_ON_ERROR);
// or, for any schema object taken from the document (e.g. an operation response):
$example = $generator->generate($schema);
```

For each schema the value is resolved in this order: `example`, a matching custom rule, a random `enum` value, `default`, then a random value for its type.
Random values honor `minimum`, `maximum`, and exclusive bounds, `minLength`, `maxLength`, and supported `pattern` syntax, and `minItems` and `maxItems`.
`date`, `date-time`, `email`, and `uuid` formats produce valid values.
Properties marked `writeOnly` are omitted.

`allOf` merges the generated objects of every branch; `oneOf` and `anyOf` pick one branch at random.
A recursive `$ref` produces `null` when the referencing schema is nullable, or an empty array when it is the item schema of an array without `minItems`; otherwise it is reported as invalid.

Pass a seeded `Random\Randomizer` to make output reproducible, for example in tests:

```php
use Random\Engine\Mt19937;
use Random\Randomizer;

$generator = new ResponseExampleGenerator($openApi, randomizer: new Randomizer(new Mt19937(42)));
```

#### Custom generation rules

Rules override generated values for a property name, a format, or a type, in that order of precedence.
They are read from `eg_r2.response_example.rules`, or passed to the constructor directly.
A rule is a fixed value, a callable receiving `(Schema $schema, string $path)`, or the class name of an invokable.

```php
// config/eg_r2.php
'response_example' => [
    'rules' => [
        'property:user_id' => App\OpenApi\UuidExample::class,
        'format:uri' => 'https://example.com',
        'type:boolean' => true,
    ],
],
```

An explicit `example` on the schema always wins over a rule.
Mapping examples to mock endpoints is not provided.
