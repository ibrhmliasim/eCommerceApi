<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Laravel stores "which rule failed" in $validator->failed(), but by default
 * only ready-made English text from resources/lang is sent out.
 *
 * This layer converts the failed() structure into stable codes like
 * "validation.email.required" - frontend matches by code, not by text,
 * therefore changing the Laravel locale or message wording does not break i18n.
 */
final class ValidationErrorCodeMapper
{
    /**
     * @return array<string, array<int, string>> field => [error_code, ...]
     */
    public static function map(ValidationException $exception): array
    {
        $codes = [];

        foreach ($exception->validator->failed() as $field => $rules) {
            $codes[$field] = array_map(
                static fn (string $rule): string => self::toCode($field, $rule),
                array_keys($rules),
            );
        }

        return $codes;
    }

    private static function toCode(string $field, string $rule): string
    {
        // Laravel gives the name of the rule in StudlyCase ("RequiredIf", etc.) - let's normalize.
        $normalizedRule = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $rule));

        return "validation.{$field}.{$normalizedRule}";
    }
}