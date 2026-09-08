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
