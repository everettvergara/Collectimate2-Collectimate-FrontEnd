<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class FlatJsonObject
{
    /**
     * @return array<string, string|int|float|bool|null>
     */
    public static function parse(mixed $input): array
    {
        if (is_array($input)) {
            $decoded = $input;
        } elseif (is_string($input)) {
            if (strlen($input) > 65536) {
                throw ValidationException::withMessages([
                    'custom_fields' => 'JSON payload must be 64KB or smaller.',
                ]);
            }

            $decoded = json_decode($input, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'custom_fields' => 'Invalid JSON: '.json_last_error_msg(),
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'custom_fields' => 'JSON must be an object.',
            ]);
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw ValidationException::withMessages([
                'custom_fields' => 'JSON root must be an object, not an array or value.',
            ]);
        }

        if (count($decoded) > 100) {
            throw ValidationException::withMessages([
                'custom_fields' => 'JSON may contain at most 100 keys.',
            ]);
        }

        $result = [];

        foreach ($decoded as $key => $value) {
            if (! is_string($key) || $key === '' || strlen($key) > 100) {
                throw ValidationException::withMessages([
                    'custom_fields' => 'Each key must be a non-empty string up to 100 characters.',
                ]);
            }

            if (is_array($value)) {
                throw ValidationException::withMessages([
                    'custom_fields' => "Nested objects or arrays are not allowed (key: {$key}).",
                ]);
            }

            if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value) && $value !== null) {
                throw ValidationException::withMessages([
                    'custom_fields' => "Value for {$key} must be a string, number, boolean, or null.",
                ]);
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
