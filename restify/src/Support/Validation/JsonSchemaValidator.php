<?php

declare(strict_types=1);

namespace Restify\Support\Validation;

use DateTimeImmutable;

final class JsonSchemaValidator
{
    /**
     * @param array<string, mixed> $schema
     * @return array<int, string>
     */
    public static function validate(mixed $data, array $schema): array
    {
        return self::validateNode($data, $schema, '$');
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<int, string>
     */
    private static function validateNode(mixed $data, array $schema, string $path): array
    {
        $errors = [];
        $types = self::normaliseTypes($schema['type'] ?? null);
        $actualType = self::detectType($data);

        if ($types !== [] && !self::matchesAllowedType($actualType, $types, $data)) {
            $errors[] = sprintf('%s: expected type %s, %s given', $path, implode('|', $types), $actualType);

            return $errors;
        }

        if (array_key_exists('const', $schema) && $data !== $schema['const']) {
            $errors[] = sprintf('%s: value must equal constant definition', $path);
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($data, $schema['enum'], true)) {
            $errors[] = sprintf('%s: value must be one of %s', $path, implode(', ', array_map('strval', $schema['enum'])));
        }

        if ($actualType === 'null') {
            return $errors;
        }

        if ($actualType === 'object') {
            if (!is_array($data)) {
                $errors[] = sprintf('%s: expected object', $path);

                return $errors;
            }

            $properties = $schema['properties'] ?? [];
            $required = $schema['required'] ?? [];

            if (is_array($required)) {
                foreach ($required as $requiredKey) {
                    if (!array_key_exists($requiredKey, $data)) {
                        $errors[] = sprintf('%s.%s: field is required', $path, $requiredKey);
                    }
                }
            }

            if (is_array($properties)) {
                foreach ($properties as $property => $definition) {
                    if (!array_key_exists($property, $data)) {
                        continue;
                    }

                    if (!is_array($definition)) {
                        continue;
                    }

                    $errors = array_merge(
                        $errors,
                        self::validateNode($data[$property], $definition, $path . '.' . $property)
                    );
                }
            }

            if (array_key_exists('additionalProperties', $schema) && $schema['additionalProperties'] === false) {
                $allowed = is_array($properties) ? array_keys($properties) : [];

                foreach (array_keys($data) as $key) {
                    if (!in_array($key, $allowed, true)) {
                        $errors[] = sprintf('%s.%s: additional properties are not allowed', $path, $key);
                    }
                }
            }

            return $errors;
        }

        if ($actualType === 'array' && is_array($data)) {
            $count = count($data);

            if (isset($schema['minItems']) && $count < (int) $schema['minItems']) {
                $errors[] = sprintf('%s: must contain at least %d items', $path, (int) $schema['minItems']);
            }

            if (isset($schema['maxItems']) && $count > (int) $schema['maxItems']) {
                $errors[] = sprintf('%s: must contain no more than %d items', $path, (int) $schema['maxItems']);
            }

            if (!empty($schema['uniqueItems'])) {
                $unique = array_unique(array_map('serialize', $data));
                if (count($unique) !== $count) {
                    $errors[] = sprintf('%s: items must be unique', $path);
                }
            }

            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($data as $index => $value) {
                    $errors = array_merge(
                        $errors,
                        self::validateNode($value, $schema['items'], sprintf('%s[%d]', $path, $index))
                    );
                }
            }

            return $errors;
        }

        if ($actualType === 'string' && is_string($data)) {
            $length = function_exists('mb_strlen') ? mb_strlen($data) : strlen($data);

            if (isset($schema['minLength']) && $length < (int) $schema['minLength']) {
                $errors[] = sprintf('%s: length must be at least %d characters', $path, (int) $schema['minLength']);
            }

            if (isset($schema['maxLength']) && $length > (int) $schema['maxLength']) {
                $errors[] = sprintf('%s: length must be at most %d characters', $path, (int) $schema['maxLength']);
            }

            if (isset($schema['pattern']) && is_string($schema['pattern'])) {
                if (@preg_match($schema['pattern'], '') === false) {
                    $errors[] = sprintf('%s: invalid pattern definition', $path);
                } elseif (preg_match($schema['pattern'], $data) !== 1) {
                    $errors[] = sprintf('%s: value does not match required pattern', $path);
                }
            }

            if (isset($schema['format']) && is_string($schema['format'])) {
                if (!self::validateFormat($data, strtolower($schema['format']))) {
                    $errors[] = sprintf('%s: value does not match %s format', $path, strtolower($schema['format']));
                }
            }

            return $errors;
        }

        if (($actualType === 'integer' || $actualType === 'number') && (is_int($data) || is_float($data))) {
            $value = (float) $data;

            if (isset($schema['minimum']) && $value < (float) $schema['minimum']) {
                $errors[] = sprintf('%s: must be greater than or equal to %s', $path, $schema['minimum']);
            }

            if (isset($schema['maximum']) && $value > (float) $schema['maximum']) {
                $errors[] = sprintf('%s: must be less than or equal to %s', $path, $schema['maximum']);
            }

            if (isset($schema['exclusiveMinimum']) && $value <= (float) $schema['exclusiveMinimum']) {
                $errors[] = sprintf('%s: must be greater than %s', $path, $schema['exclusiveMinimum']);
            }

            if (isset($schema['exclusiveMaximum']) && $value >= (float) $schema['exclusiveMaximum']) {
                $errors[] = sprintf('%s: must be less than %s', $path, $schema['exclusiveMaximum']);
            }

            if (isset($schema['multipleOf']) && (float) $schema['multipleOf'] > 0.0) {
                $multiple = (float) $schema['multipleOf'];
                if (fmod($value, $multiple) !== 0.0) {
                    $errors[] = sprintf('%s: must be a multiple of %s', $path, $schema['multipleOf']);
                }
            }

            return $errors;
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    private static function normaliseTypes(mixed $type): array
    {
        if ($type === null) {
            return [];
        }

        if (is_string($type)) {
            return [$type];
        }

        if (is_array($type)) {
            return array_values(array_filter(array_map(
                static fn (mixed $entry): string => strtolower((string) $entry),
                $type
            )));
        }

        return [];
    }

    private static function detectType(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'number';
        }

        if (is_string($value)) {
            return 'string';
        }

        if (is_array($value)) {
            return array_is_list($value) ? 'array' : 'object';
        }

        return 'object';
    }

    /**
     * @param array<int, string> $types
     */
    private static function matchesAllowedType(string $actual, array $types, mixed $value): bool
    {
        foreach ($types as $type) {
            if ($type === $actual) {
                return true;
            }

            if ($type === 'number' && in_array($actual, ['integer', 'number'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function validateFormat(string $value, string $format): bool
    {
        return match ($format) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'uri', 'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1,
            'date-time' => self::isValidDateTime($value),
            default => true,
        };
    }

    private static function isValidDateTime(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $value);

        if ($date instanceof DateTimeImmutable) {
            return true;
        }

        return strtotime($value) !== false;
    }
}
