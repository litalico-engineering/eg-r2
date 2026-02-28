<?php

declare(strict_types=1);

namespace Tests\Unit;

use Litalico\EgR2\Services\AttributeMessageService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Testing of Attribute Message Service
 */
#[CoversClass(AttributeMessageService::class)]
class AttributeMessageServiceTest extends TestCase
{
    #[Test]
    public function canFormatArrayItemsLabelInJapanese(): void
    {
        app('config')->set('app.locale', 'ja');

        $service = new AttributeMessageService();
        $result = $service->formatArrayItemsLabel('items array');

        self::assertEquals('items arrayの各項目', $result);
    }

    #[Test]
    public function canFormatArrayItemsLabelInEnglish(): void
    {
        app('config')->set('app.locale', 'en');

        $service = new AttributeMessageService();
        $result = $service->formatArrayItemsLabel('items array');

        self::assertEquals('Each item of items array', $result);
    }

    #[Test]
    public function canFormatNestedArrayItemLabelInJapanese(): void
    {
        app('config')->set('app.locale', 'ja');

        $service = new AttributeMessageService();
        $result = $service->formatNestedArrayItemLabel('departments', 'employee id');

        self::assertEquals('departmentsの :position 行目の「employee id」', $result);
    }

    #[Test]
    public function canFormatNestedArrayItemLabelInEnglish(): void
    {
        app('config')->set('app.locale', 'en');

        $service = new AttributeMessageService();
        $result = $service->formatNestedArrayItemLabel('departments', 'employee id');

        self::assertEquals('Row :position of departments: "employee id"', $result);
    }

    #[Test]
    public function canFormatNestedArrayItemsLabelInJapanese(): void
    {
        app('config')->set('app.locale', 'ja');

        $service = new AttributeMessageService();
        $result = $service->formatNestedArrayItemsLabel('departments', 'employees');

        self::assertEquals('departmentsの :position 行目の「employees」の各項目', $result);
    }

    #[Test]
    public function canFormatNestedArrayItemsLabelInEnglish(): void
    {
        app('config')->set('app.locale', 'en');

        $service = new AttributeMessageService();
        $result = $service->formatNestedArrayItemsLabel('departments', 'employees');

        self::assertEquals('Each item of row :position of departments: "employees"', $result);
    }

    #[Test]
    public function fallbackToJapaneseForUnsupportedLocale(): void
    {
        app('config')->set('app.locale', 'fr');

        $service = new AttributeMessageService();
        $result = $service->formatArrayItemsLabel('items');

        // Should fallback to Japanese
        self::assertEquals('itemsの各項目', $result);
    }
}
