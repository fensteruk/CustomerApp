<?php

namespace App\SourceImport\Knowledge;

use InvalidArgumentException;

final class Canonical
{
    public static function json(mixed $value, int $limit = 262144): string
    {
        $json = json_encode(self::sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if (strlen($json) > $limit) {
            throw new InvalidArgumentException('knowledge_payload_limit');
        }

        return $json;
    }

    public static function hash(mixed $value): string
    {
        return hash('sha256', self::json($value));
    }

    /** Hash-only transient engine evidence; never a persistence payload allowance.
     * Same canonical bytes as hash(), streamed with an independent finite 4 MiB ceiling.
     */
    public static function evidenceHash(mixed $value): string
    {
        $context = hash_init('sha256');
        $bytes = 0;
        $append = function (string $part) use ($context, &$bytes): void {
            $bytes += strlen($part);
            if ($bytes > 4 * 1024 * 1024) {
                throw new InvalidArgumentException('transient_evidence_hash_limit');
            }
            hash_update($context, $part);
        };
        $walk = function (mixed $node) use (&$walk, $append): void {
            if (! is_array($node)) {
                $append(self::json($node));

                return;
            }
            $list = array_is_list($node);
            if (! $list) {
                ksort($node, SORT_STRING);
            }
            $append($list ? '[' : '{');
            $first = true;
            foreach ($node as $key => $child) {
                if (! $first) {
                    $append(',');
                } $first = false;
                if (! $list) {
                    $append(self::json((string) $key).':');
                }
                $walk($child);
            }
            $append($list ? ']' : '}');
        };
        $walk($value);

        return hash_final($context);
    }

    private static function sort(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }

            return array_map(self::sort(...), $value);
        }
        if (is_object($value) || is_resource($value)) {
            throw new InvalidArgumentException('knowledge_requires_plain_data');
        }

        return $value;
    }
}
