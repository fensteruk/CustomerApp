<?php

namespace App\SourceImport\Semantics\Data;

use InvalidArgumentException;
use JsonSerializable;

final readonly class DictionaryIdentity implements JsonSerializable
{
    private function __construct(public string $version, public string $fingerprint) {}

    public static function fromDefinition(string $version, array $definition, ?self $previous = null): self
    {
        if (! preg_match('/^customerapp\.source-dictionary\.v[1-9][0-9]*$/D', $version)) {
            throw new InvalidArgumentException('invalid_dictionary_version');
        }
        $fingerprint = hash('sha256', json_encode(self::canonical($definition), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
        if ($previous !== null && $previous->version === $version && $previous->fingerprint !== $fingerprint) {
            throw new InvalidArgumentException('dictionary_change_requires_new_version');
        }

        return new self($version, $fingerprint);
    }

    private static function canonical(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }

            return array_map(self::canonical(...), $value);
        }
        if (! is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException('invalid_dictionary_definition');
        }

        return $value;
    }

    public function jsonSerialize(): array
    {
        return ['version' => $this->version, 'fingerprint' => $this->fingerprint];
    }
}
