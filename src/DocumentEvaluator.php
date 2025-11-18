<?php

declare(strict_types=1);

namespace Wafl\Core;

/**
 * Walks resolved documents and evaluates __expr blocks, tags and conditional lists.
 */
final class DocumentEvaluator
{
    private TagRegistry $tagRegistry;

    /**
     * @param TagRegistry|null $tagRegistry Allows registering custom tags for tests/extensions
     */
    public function __construct(?TagRegistry $tagRegistry = null)
    {
        $this->tagRegistry = $tagRegistry ?? new TagRegistry();
    }

    /**
     * Evaluates a resolved document.
     *
     * @param mixed $doc Document or node to walk
     * @param array $options Supported keys: env, symbols, baseDir
     */
    public function evaluate(mixed $doc, array $options = []): mixed
    {
        $env = $options['env'] ?? $_ENV;
        $symbols = $options['symbols'] ?? [];
        $baseDir = $options['baseDir'] ?? getcwd();

        return $this->walk($doc, $env, $symbols, ['baseDir' => $baseDir]);
    }

    /**
     * Recursively evaluates a node.
     */
    private function walk(mixed $node, array $env, array $symbols, array $ctx): mixed
    {
        if (is_array($node) && !Utils::isAssoc($node)) {
            $result = [];
            foreach ($node as $item) {
                if (is_array($item) && isset($item['__if'])) {
                    if ($this->safeEval($item['__if'], $env, $symbols)) {
                        $result[] = $this->walk($item['value'] ?? null, $env, $symbols, $ctx);
                    }
                    continue;
                }

                $result[] = $this->walk($item, $env, $symbols, $ctx);
            }

            return $result;
        }

        if (is_array($node) && Utils::isAssoc($node)) {
            if (isset($node['__if'])) {
                return $node;
            }

            if (isset($node['__expr'])) {
                return $this->safeEval($node['__expr'], $env, $symbols);
            }

            if (isset($node['__tag'])) {
                return $this->tagRegistry->run((string) $node['__tag'], $node['args'] ?? [], $env, $ctx);
            }

            $result = [];
            foreach ($node as $key => $value) {
                $result[$key] = $this->walk($value, $env, $symbols, $ctx);
            }

            return $result;
        }

        return $node;
    }

    /**
     * Evaluates an inline PHP expression with access to $ENV and symbols.
     */
    private function safeEval(mixed $expr, array $env, array $symbols, bool $allowFallback = true): mixed
    {
        if (!is_string($expr)) {
            return $expr;
        }

        $trimmed = trim($expr);
        if ($allowFallback && preg_match('/^\$ENV\.([A-Za-z0-9_]+)\s*\|\|\s*(.+)$/', $trimmed, $match)) {
            $key = $match[1];
            $fallbackExpr = trim($match[2]);
            $value = $env[$key] ?? null;
            if ($value !== null && $value !== '') {
                return $value;
            }

            return $this->safeEval($fallbackExpr, $env, $symbols, false);
        }

        if (str_contains($expr, ':')) {
            return $expr;
        }

        $normalized = preg_replace_callback('/\$ENV\.([A-Za-z0-9_]+)/', static function (array $matches) use ($env) {
            $key = $matches[1];
            $value = $env[$key] ?? null;
            if (is_string($value)) {
                return var_export($value, true);
            }
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            if ($value === null) {
                return 'null';
            }

            return (string) $value;
        }, $expr) ?? $expr;

        $normalized = str_replace(['===', '!=='], ['==', '!='], $normalized);

        foreach ($symbols as $key => $value) {
            $normalized = str_replace('$' . $key, var_export($value, true), $normalized);
        }

        try {
            /** @phpstan-ignore-next-line */
            return eval(sprintf('return %s;', $normalized));
        } catch (\Throwable) {
            return $expr;
        }
    }
}
