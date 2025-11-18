<?php

declare(strict_types=1);

namespace Wafl\Core;

/**
 * Extracts key<Type> metadata prior to resolution so schema validation can reference it later.
 */
final class TypeMetadataExtractor
{
    /**
     * @return array<string, string> Map of dotted path => type name
     */
    public function extract(array $doc): array
    {
        $metadata = [];
        $this->walk($doc, '', $metadata);

        return $metadata;
    }

    /**
     * Depth-first traversal that captures metadata for nested structures.
     */
    private function walk(mixed $node, string $path, array &$metadata): void
    {
        if (!is_array($node) || !Utils::isAssoc($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (preg_match('/^(.*)<([A-Za-z0-9_]+)>$/', $key, $match)) {
                $baseKey = $match[1] !== '' ? $match[1] : $key;
                $typeName = $match[2];
                $fullPath = $path !== '' ? $path . '.' . $baseKey : $baseKey;
                $metadata[$fullPath] = $typeName;
            }

            $cleanKey = preg_replace('/<.*>$/', '', $key);
            $nextPath = $path !== '' ? $path . '.' . $cleanKey : $cleanKey;
            $this->walk($value, $nextPath, $metadata);
        }
    }
}
