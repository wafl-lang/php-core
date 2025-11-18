<?php

declare(strict_types=1);

namespace Wafl\Core;

use RuntimeException;

/**
 * Loads raw .wafl files, resolving imports and extracting metadata blocks.
 */
final class Loader
{
    private Parser $parser;

    /**
     * @param Parser|null $parser Allows swapping the parser implementation
     */
    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? new Parser();
    }

    /**
     * Loads the entry file and all nested imports.
     *
     * @return array{doc: array, meta: array}
     */
    public function load(string $entryPath): array
    {
        $resolvedEntry = realpath($entryPath) ?: $entryPath;
        if (!is_file($resolvedEntry)) {
            throw new RuntimeException(sprintf('Config file not found: %s', $resolvedEntry));
        }

        $baseDir = dirname($resolvedEntry);
        $visited = [];
        $result = $this->loadRecursive($resolvedEntry, $visited);
        $result['meta']['baseDir'] = $baseDir;

        return $result;
    }

    /**
     * Recursively loads imports while keeping track of visited files.
     *
     * @param string $path Absolute path to load
     * @param array<string, true> $visited Prevents import cycles
     */
    private function loadRecursive(string $path, array &$visited): array
    {
        $absolute = realpath($path) ?: $path;
        if (isset($visited[$absolute])) {
            return ['doc' => [], 'meta' => []];
        }

        $visited[$absolute] = true;
        $source = file_get_contents($absolute);
        if ($source === false) {
            throw new RuntimeException(sprintf('Unable to read file: %s', $absolute));
        }

        $lines = preg_split("/\r?\n/", $source) ?: [];
        if (isset($lines[0]) && str_starts_with(trim($lines[0]), '%WAFL') && !preg_match('/^%WAFL\s+0\.\d+\s*$/', trim($lines[0]))) {
            throw new RuntimeException(sprintf('Invalid WAFL header: %s', trim($lines[0])));
        }

        $parsed = $this->parser->parseString($source);
        $meta = [
            'imports' => [],
            'schema' => null,
            'evalBlock' => null,
            'baseDir' => dirname($absolute),
        ];

        if (isset($parsed['@import'])) {
            $imports = $parsed['@import'];
            unset($parsed['@import']);
            $meta['imports'] = is_array($imports) ? $imports : [$imports];
        }

        if (isset($parsed['@schema'])) {
            $meta['schema'] = $parsed['@schema'];
            unset($parsed['@schema']);
        }

        if (isset($parsed['@eval'])) {
            $meta['evalBlock'] = $parsed['@eval'];
            unset($parsed['@eval']);
        }

        $merged = [];
        foreach ($meta['imports'] as $importPath) {
            $resolved = $this->resolveImport($importPath, dirname($absolute));
            $imported = $this->loadRecursive($resolved, $visited);
            $merged = Utils::deepMerge($merged, $imported['doc']);
            if ($meta['schema'] === null && ($imported['meta']['schema'] ?? null) !== null) {
                $meta['schema'] = $imported['meta']['schema'];
            }
            if ($meta['evalBlock'] === null && ($imported['meta']['evalBlock'] ?? null) !== null) {
                $meta['evalBlock'] = $imported['meta']['evalBlock'];
            }
        }

        $merged = Utils::deepMerge($merged, $parsed);

        return [
            'doc' => $merged,
            'meta' => $meta,
        ];
    }

    /**
     * Resolves relative imports based on the current directory.
     */
    private function resolveImport(string $importPath, string $currentDir): string
    {
        if (str_starts_with($importPath, '/')) {
            return $importPath;
        }

        $candidate = $currentDir . DIRECTORY_SEPARATOR . $importPath;
        return realpath($candidate) ?: $candidate;
    }
}
