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
    This creates `config/eg-r2.php` for customization. If not published, default configuration will be used.

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

2. Configure the `config/eg-r2.php` (if you published it in step 2 of Installation)  
Describe the namespace of the Controller that describes the OpenAPI Attribute.  
If you didn't publish the config file, you can create it manually at `config/eg-r2.php`:
    ```php
    <?php
    
    return [
        'namespaces' => [
            'App\\Http\\Controllers',
        ],
        'route_path' => base_path('routes/eg_r2.php'),
    ];
    ```

3. Generate Route Files  
    ```console
    php artisan eg-r2:generate-route
    ```

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
