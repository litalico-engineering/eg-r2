<?php

declare(strict_types=1);

namespace Litalico\EgR2\Services;

use Throwable;
use function config;
use function is_array;
use function is_string;
use function trans;

/**
 * Service for generating localized attribute messages for form validation.
 *
 * @package Litalico\EgR2\Services
 */
class AttributeMessageService
{
    /**
     * Format message for array items (e.g., "items.*").
     *
     * @param string $description The description of the array field
     * @return string Localized message
     */
    public function formatArrayItemsLabel(string $description): string
    {
        return $this->translate('array_items', [
            'description' => $description,
        ]);
    }

    /**
     * Format message for nested array item property (e.g., "items.*.name").
     *
     * @param string $arrayName The name/description of the parent array
     * @param string $description The description of the nested property
     * @return string Localized message
     */
    public function formatNestedArrayItemLabel(string $arrayName, string $description): string
    {
        return $this->translate('nested_array_item', [
            'arrayName' => $arrayName,
            'description' => $description,
            'position' => ':position',
        ]);
    }

    /**
     * Format message for nested array items wildcard (e.g., "items.*.children.*").
     *
     * @param string $arrayName The name/description of the parent array
     * @param string $description The description of the nested array
     * @return string Localized message
     */
    public function formatNestedArrayItemsLabel(string $arrayName, string $description): string
    {
        return $this->translate('nested_array_items', [
            'arrayName' => $arrayName,
            'description' => $description,
            'position' => ':position',
        ]);
    }

    /**
     * Translate a key with replacements using Laravel's translation system.
     *
     * @param string $key Translation key (without the 'eg_r2::eg_r2.' prefix)
     * @param array<string, string> $replace Replacement parameters
     * @return string Translated and formatted message
     */
    private function translate(string $key, array $replace = []): string
    {
        $locale = $this->getLocale();

        // Use Laravel's trans() helper which respects the translation priority:
        // 1. resources/lang/vendor/eg_r2/{locale}/eg_r2.php (user customization)
        // 2. vendor/litalico/eg-r2/resources/lang/{locale}/eg_r2.php (package default)
        $fullKey = 'eg_r2::eg_r2.' . $key;
        $translation = trans($fullKey, $replace, $locale);

        // Handle array or string response from trans()
        if (is_array($translation)) {
            // If trans returns an array, the translation was not found
            $fallbackLocale = $this->getFallbackLocale();
            $translation = trans($fullKey, $replace, $fallbackLocale);

            // If still an array after fallback, return the key
            if (is_array($translation)) {
                return $fullKey;
            }
        }

        // Ensure we have a string at this point
        $translationString = (string) $translation;

        // If translation key not found (trans returns the key), fallback to configured fallback locale
        if ($translationString === $fullKey || $translationString === $key) {
            $fallbackLocale = $this->getFallbackLocale();
            $fallbackTranslation = trans($fullKey, $replace, $fallbackLocale);

            // Handle array response from fallback as well
            if (is_array($fallbackTranslation)) {
                return $fullKey;
            }

            $translationString = (string) $fallbackTranslation;
        }

        return $translationString;
    }

    /**
     * Get the current locale from application config.
     *
     * @return string Current locale code
     */
    private function getLocale(): string
    {
        try {
            $locale = config('app.locale');
            if (is_string($locale)) {
                return $locale;
            }

            // If app.locale is not set, use fallback locale
            return $this->getFallbackLocale();
        } catch (Throwable $e) {
            return $this->getFallbackLocale();
        }
    }

    /**
     * Get the fallback locale from application config.
     *
     * @return string Fallback locale code
     */
    private function getFallbackLocale(): string
    {
        try {
            $fallbackLocale = config('app.fallback_locale');

            return is_string($fallbackLocale) ? $fallbackLocale : 'ja';
        } catch (Throwable $e) {
            return 'ja';
        }
    }
}
