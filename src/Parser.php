<?php

declare(strict_types=1);

namespace Wafl\Core;

use RuntimeException;

/**
 * Minimal indentation-aware parser for the WAFL language.
 */
final class Parser
{
    /**
     * Parses a raw .wafl string into an intermediate array structure.
     */
    public function parseString(string $source): array
    {
        $lines = preg_split("/\r?\n/", $source) ?: [];
        $filtered = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '#') || str_starts_with($trimmed, '%') || str_starts_with($trimmed, '---')) {
                continue;
            }

            $filtered[] = rtrim($line, "\r\n");
        }

        $root = [];
        $stack = [
            [
                'indent' => -1,
                'target' => &$root,
            ],
        ];

        foreach ($filtered as $rawLine) {
            $indent = $this->countIndent($rawLine);
            $line = rtrim($rawLine);

            while ($indent < $stack[array_key_last($stack)]['indent']) {
                array_pop($stack);
            }

            $currentIndex = array_key_last($stack);
            if ($currentIndex === null) {
                throw new RuntimeException('Invalid indentation in WAFL file');
            }

            $current =& $stack[$currentIndex]['target'];
            $parsedLine = $this->parseLine(trim($line));
            if ($parsedLine === null) {
                continue;
            }

            if (($parsedLine['isSection'] ?? false) === true) {
                $current[$parsedLine['key']] = [];
                $stack[] = [
                    'indent' => $indent + 1,
                    'target' => &$current[$parsedLine['key']],
                ];
                continue;
            }

            if (($parsedLine['isList'] ?? false) === true) {
                $item = $parsedLine['value'];
                if (isset($parsedLine['condition'])) {
                    $item = [
                        '__if' => ['__expr' => $parsedLine['condition']],
                        'value' => $parsedLine['value'],
                    ];
                }

                if (is_array($current) && !Utils::isAssoc($current)) {
                    $current[] = $item;
                } else {
                    $keys = array_keys($current);
                    $lastKey = end($keys);
                    if ($lastKey !== false && isset($current[$lastKey]) && is_array($current[$lastKey]) && Utils::isAssoc($current[$lastKey])) {
                        if (!isset($current[$lastKey]['_list'])) {
                            $current[$lastKey]['_list'] = [];
                        }
                        $current[$lastKey]['_list'][] = $item;
                    } else {
                        if (!isset($current['_list'])) {
                            $current['_list'] = [];
                        }
                        $current['_list'][] = $item;
                    }
                }

                continue;
            }

            $current[$parsedLine['key']] = $parsedLine['value'];
        }

        return $this->fixLists($root);
    }

    /**
     * Parses a single line and returns metadata describing its type.
     */
    private function parseLine(string $line): ?array
    {
        if ($line === '') {
            return null;
        }

        if (str_ends_with($line, ':') && !str_contains($line, '=')) {
            return [
                'key' => trim(substr($line, 0, -1)),
                'isSection' => true,
            ];
        }

        if (preg_match('/^-\s+(.*)$/', $line, $match)) {
            $content = trim($match[1]);
            if (preg_match('/^if\s+(.+?):\s*(.*)$/', $content, $conditionalMatch)) {
                return [
                    'isList' => true,
                    'condition' => trim($conditionalMatch[1]),
                    'value' => $this->interpretValue(trim($conditionalMatch[2])),
                ];
            }

            return [
                'isList' => true,
                'value' => $this->interpretValue($content),
            ];
        }

        if (preg_match('/^([^:=]+)\s*=\s*(.*)$/', $line, $match)) {
            return [
                'key' => trim($match[1]),
                'value' => ['__expr' => trim($match[2])],
            ];
        }

        if (preg_match('/^([^:=]+)\s*:\s*(.*)$/', $line, $match)) {
            return [
                'key' => trim($match[1]),
                'value' => $this->interpretValue(trim($match[2])),
            ];
        }

        return null;
    }

    /**
     * Converts a raw scalar into a typed PHP value, tag, or expression node.
     */
    private function interpretValue(mixed $raw): mixed
    {
        if (!is_string($raw)) {
            return $raw;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed === 'true') {
            return true;
        }

        if ($trimmed === 'false') {
            return false;
        }

        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        if ($trimmed[0] === '!' && preg_match('/^!(\w+)\((.*)\)$/', $trimmed, $match)) {
            $args = array_filter(array_map('trim', explode(',', $match[2])), static fn ($value) => $value !== '');
            return [
                '__tag' => $match[1],
                'args' => $args,
            ];
        }

        if ((str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"')) || (str_starts_with($trimmed, '\'') && str_ends_with($trimmed, '\''))) {
            return substr($trimmed, 1, -1);
        }

        return $trimmed;
    }

    /**
     * Counts the indentation level for stack management.
     */
    private function countIndent(string $line): int
    {
        $count = 0;
        $length = strlen($line);
        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];
            if ($char !== ' ' && $char !== "\t") {
                break;
            }
            $count++;
        }

        return $count;
    }

    /**
     * Converts temporary _list markers into real PHP arrays.
     */
    private function fixLists(array $obj): array
    {
        foreach ($obj as $key => $value) {
            if (is_array($value) && Utils::isAssoc($value)) {
                $obj[$key] = $this->fixLists($value);
            } elseif (is_array($value)) {
                $obj[$key] = array_map(function ($item) {
                    if (is_array($item) && Utils::isAssoc($item)) {
                        return $this->fixLists($item);
                    }

                    return $item;
                }, $value);
            }
        }

        if (isset($obj['_list']) && is_array($obj['_list'])) {
            $list = $obj['_list'];
            unset($obj['_list']);
            return array_map(function ($item) {
                if (is_array($item) && Utils::isAssoc($item)) {
                    return $this->fixLists($item);
                }

                return $item;
            }, $list);
        }

        return $obj;
    }
}
