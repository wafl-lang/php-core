<?php

declare(strict_types=1);

namespace Wafl\Core;

use RuntimeException;

/**
 * Validates resolved documents against schema definitions extracted from @schema blocks.
 */
final class SchemaValidator
{
    /**
     * Validates a document using either explicit metadata or classic <Type> suffixes.
     *
     * @param array $doc Resolved document
     * @param array $schemaRoot Schema definition map
     * @param array $typeMetadata keyPath => typeName metadata extracted beforehand
     */
    public function validate(array $doc, array $schemaRoot, array $typeMetadata = []): bool
    {
        if ($schemaRoot === []) {
            return true;
        }

        if ($typeMetadata !== []) {
            foreach ($typeMetadata as $path => $typeName) {
                $spec = $schemaRoot[$typeName] ?? null;
                if ($spec === null) {
                    continue;
                }

                $value = $this->getValueByPath($doc, $path);
                if ($value === null) {
                    continue;
                }

                $this->assertType($path, $value, $this->resolveType($spec));
            }

            return true;
        }

        foreach ($doc as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (preg_match('/^(.*)<([A-Za-z0-9_]+)>$/', $key, $match)) {
                $typeName = $match[2];
                $spec = $schemaRoot[$typeName] ?? null;
                if ($spec === null) {
                    continue;
                }

                $this->assertType($match[1], $value, $this->resolveType($spec));
            }
        }

        return true;
    }

    /**
     * Utility helper to fetch nested values (dot notation).
     */
    private function getValueByPath(array $doc, string $path): mixed
    {
        $segments = explode('.', $path);
        $value = $doc;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Normalises schema definitions to a consistent internal representation.
     */
    private function resolveType(string|array $spec): array
    {
        if (is_string($spec)) {
            if (preg_match('/^list<(.*)>$/', $spec, $match)) {
                return [
                    'kind' => 'list',
                    'of' => $this->resolveType($match[1]),
                ];
            }

            return ['kind' => $spec];
        }

        return [
            'kind' => 'object',
            'fields' => $spec,
        ];
    }

    /**
     * Performs recursive type checks and throws when an expectation fails.
     */
    private function assertType(string $path, mixed $value, array $type): void
    {
        switch ($type['kind']) {
            case 'string':
                if (!is_string($value)) {
                    $this->fail($path, 'string', $value);
                }
                break;
            case 'int':
            case 'number':
                if (!is_numeric($value)) {
                    $this->fail($path, $type['kind'], $value);
                }
                break;
            case 'bool':
            case 'boolean':
                if (!is_bool($value)) {
                    $this->fail($path, 'boolean', $value);
                }
                break;
            case 'list':
                if (!is_array($value) || Utils::isAssoc($value)) {
                    $this->fail($path, 'list', $value);
                }
                foreach ($value as $index => $item) {
                    $this->assertType(sprintf('%s[%d]', $path, $index), $item, $type['of']);
                }
                break;
            case 'object':
                if (!is_array($value) || !Utils::isAssoc($value)) {
                    $this->fail($path, 'object', $value);
                }

                foreach ($type['fields'] as $fieldName => $fieldSpec) {
                    $optional = str_ends_with($fieldName, '?');
                    $name = $optional ? substr($fieldName, 0, -1) : $fieldName;
                    if (!array_key_exists($name, $value)) {
                        if ($optional) {
                            continue;
                        }
                        throw new RuntimeException(sprintf('Required field "%s" missing at %s', $name, $path));
                    }

                    $this->assertType($path === '' ? $name : $path . '.' . $name, $value[$name], $this->resolveType($fieldSpec));
                }
                break;
        }
    }

    /**
     * Throws a descriptive runtime exception for type mismatches.
     */
    private function fail(string $path, string $expected, mixed $got): void
    {
        throw new RuntimeException(sprintf('Expected %s at %s, got %s', $expected, $path, get_debug_type($got)));
    }
}
