<?php

declare(strict_types=1);

namespace Wafl\Core;

use RuntimeException;

/**
 * Registry of built-in and user-provided tag handlers.
 */
final class TagRegistry
{
    /** @var array<string, callable> */
    private array $handlers = [];

    public function __construct()
    {
        $this->register('rgb', function (mixed $args): string {
            $parts = is_array($args) ? $args : array_map('trim', explode(',', (string) $args));
            $numbers = array_map(static fn ($value): int => (int) $value, $parts);
            if (count($numbers) !== 3) {
                throw new RuntimeException(sprintf('!rgb expects 3 numbers, got %s', json_encode($args)));
            }

            return sprintf('rgb(%d, %d, %d)', $numbers[0], $numbers[1], $numbers[2]);
        });

        $this->register('file', function (mixed $args, array $env, array $ctx): string {
            $baseDir = $ctx['baseDir'] ?? getcwd();
            $filePath = is_string($args) ? $args : (string) ($args[0] ?? '');
            $resolved = realpath($baseDir . DIRECTORY_SEPARATOR . $filePath) ?: $baseDir . DIRECTORY_SEPARATOR . $filePath;
            if (!is_file($resolved)) {
                throw new RuntimeException(sprintf('File not found for !file: %s', $resolved));
            }

            $content = file_get_contents($resolved);
            if ($content === false) {
                throw new RuntimeException(sprintf('Unable to read file for !file: %s', $resolved));
            }

            return $content;
        });
    }

    /**
     * Registers or overrides a tag handler.
     *
     * @param callable(mixed, array, array):mixed $handler
     */
    public function register(string $tag, callable $handler): void
    {
        $this->handlers[$tag] = $handler;
    }

    /**
     * Executes a tag handler by name.
     */
    public function run(string $tag, mixed $args, array $env, array $ctx): mixed
    {
        $handler = $this->handlers[$tag] ?? null;
        if ($handler === null) {
            throw new RuntimeException(sprintf('Unknown WAFL tag "!%s"', $tag));
        }

        return $handler($args, $env, $ctx);
    }
}
