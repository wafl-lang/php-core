<?php

declare(strict_types=1);

namespace Wafl\Core;

/**
 * Helper methods shared across the PHP core.
 */
final class Utils
{
    /**
     * Checks if an array is associative (object-like) or sequential.
     */
    public static function isAssoc(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if ($value === []) {
            return true;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    /**
     * Recursively merges two values, mimicking the JavaScript behaviour.
     */
    public static function deepMerge(mixed $a, mixed $b): mixed
    {
        if ($b === null) {
            return $a;
        }

        if ($a === null) {
            return $b;
        }

        if (is_array($a) && is_array($b)) {
            if (!self::isAssoc($a) && !self::isAssoc($b)) {
                return array_merge($a, $b);
            }

            $result = $a;
            foreach ($b as $key => $value) {
                $result[$key] = array_key_exists($key, $result)
                    ? self::deepMerge($result[$key], $value)
                    : $value;
            }

            return $result;
        }

        return $b;
    }
}
