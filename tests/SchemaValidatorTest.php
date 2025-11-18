<?php

declare(strict_types=1);

namespace Wafl\Core\Tests;

use Wafl\Core\SchemaValidator;
use Wafl\Core\TypeMetadataExtractor;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorTest extends TestCase
{
    public function testValidatesUsingTypeMetadata(): void
    {
        $typedDoc = [
            'app<App>' => [
                'name' => 'Demo',
                'version' => 2,
            ],
        ];

        $resolvedDoc = [
            'app' => [
                'name' => 'Demo',
                'version' => 2,
            ],
        ];

        $schema = [
            'App' => [
                'name' => 'string',
                'version' => 'number',
            ],
        ];

        $metadata = (new TypeMetadataExtractor())->extract($typedDoc);
        $validator = new SchemaValidator();

        $this->assertTrue($validator->validate($resolvedDoc, $schema, $metadata));
    }

    public function testThrowsWhenTypeMismatchOccurs(): void
    {
        $typedDoc = [
            'app<App>' => [
                'name' => 'Demo',
                'version' => 'oops',
            ],
        ];

        $resolvedDoc = [
            'app' => [
                'name' => 'Demo',
                'version' => 'oops',
            ],
        ];

        $schema = [
            'App' => [
                'name' => 'string',
                'version' => 'number',
            ],
        ];

        $metadata = (new TypeMetadataExtractor())->extract($typedDoc);
        $validator = new SchemaValidator();

        $this->expectException(\RuntimeException::class);
        $validator->validate($resolvedDoc, $schema, $metadata);
    }
}
