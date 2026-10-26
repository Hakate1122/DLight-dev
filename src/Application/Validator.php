<?php

namespace DLight\Application;

use function is_array;
use function is_bool;
use function is_string;
use function in_array;

/**
 * **Data Validator**
 * 
 * Validator class for validating data against a set of rules.
 * Supports basic validation rules and file upload validation rules.
 */
class Validator
{
    /**
     * Validation errors collected after make()
     * @var array<string, list<string>>
     */
    private array $errors = [];

    /**
     * The first validation error encountered (preserve order)
     */
    private ?string $firstError = null;

    /* ----- BASIC RULES ----- */
    /**
     * Check if a value is present and not empty (except for '0').
     * @param mixed $value The value to check.
     */
    public static function required($value): bool
    {
        return !empty($value) || $value === '0';
    }

    /**
     * Check if a value is a valid email address.
     * @param mixed $value The value to check.
     */
    public static function email($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Check if a string has a minimum length.
     * @param mixed $value The value to check.
     * @param int $min The minimum length.
     */
    public static function minLength($value, int $min): bool
    {
        return mb_strlen((string) $value) >= $min;
    }

    /**
     * Check if a string does not exceed a maximum length.
     * @param mixed $value The value to check.
     * @param int $max The maximum length.
     */
    public static function maxLength($value, int $max): bool
    {
        return mb_strlen((string) $value) <= $max;
    }

    /**
     * Check if a value is numeric.
     * @param mixed $value The value to check.
     */
    public static function numeric($value): bool
    {
        return is_numeric($value);
    }

    /**
     * Check if a value is an integer.
     * @param mixed $value The value to check.
     */
    public static function integer($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Check if a value is a boolean.
     * @param mixed $value The value to check.
     */
    public static function boolean($value): bool
    {
        if (is_bool($value)) {
            return true;
        }
        return in_array($value, ['true', 'false', '1', '0', 1, 0], true);
    }

    /**
     * Check if a value is a string containing only alphabetic characters.
     * @param mixed $value The value to check.
     */
    public static function alpha($value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z]+$/', $value);
    }

    /**
     * Check if a value is a string containing only alphanumeric characters.
     * @param mixed $value The value to check.
     */
    public static function alphaNum($value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9]+$/', $value);
    }

    /**
     * Check if a value is in a comma-separated list of allowed values.
     * @param mixed $value The value to check.
     * @param string $param Comma-separated list of allowed values.
     */
    public static function inList($value, string $param): bool
    {
        return in_array($value, explode(',', $param), true);
    }

    /**
     * Check if a value is not in a comma-separated list of disallowed values.
     * @param mixed $value The value to check.
     * @param string $param Comma-separated list of disallowed values.
     */
    public static function notInList($value, string $param): bool
    {
        return !in_array($value, explode(',', $param), true);
    }

    /**
     * Check if a value is a valid URL.
     * @param mixed $value The value to check.
     */
    public static function url($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Check if a value is a valid date.
     * @param mixed $value The value to check.
     */
    public static function date($value): bool
    {
        return strtotime($value) !== false;
    }

    /* ----- FILE UPLOAD RULES ----- */
    /**
     * Check if a value is a valid uploaded file.
     * @param mixed $value The value to check.
     */
    public static function isFile($value): bool
    {
        return is_array($value)
            && isset($value['error'], $value['tmp_name'])
            && $value['error'] === UPLOAD_ERR_OK
            && is_uploaded_file($value['tmp_name']);
    }

    /**
     * Check if a file is an image based on MIME type.
     * @param mixed $value The value to check.
     */
    public static function isImage($value): bool
    {
        if (!self::isFile($value)) {
            return false;
        }

        $mime = mime_content_type($value['tmp_name']);
        return in_array($mime, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/svg+xml',
        ], true);
    }

    /**
     * Check if a file has an allowed extension.
     * @param mixed $value The value to check.
     * @param string $param Comma-separated list of allowed extensions (without dot).
     */
    public static function mimes($value, string $param): bool
    {
        if (!self::isFile($value)) {
            return false;
        }

        $allowed = explode(',', strtolower($param));
        $ext = strtolower(pathinfo($value['name'], PATHINFO_EXTENSION));

        return in_array($ext, $allowed, true);
    }

    /**
     * Check if a file has an allowed MIME type.
     * @param mixed $value The value to check.
     * @param string $param Comma-separated list of allowed MIME types.
     */
    public static function mimeTypes($value, string $param): bool
    {
        if (!self::isFile($value)) {
            return false;
        }

        $allowed = explode(',', strtolower($param));
        $mime = strtolower(mime_content_type($value['tmp_name']));

        return in_array($mime, $allowed, true);
    }

    /**
     * Check if a file does not exceed a maximum size in kilobytes.
     * @param mixed $value The value to check.
     * @param int $maxKB The maximum file size in kilobytes.
     */
    public static function maxFile($value, int $maxKB): bool
    {
        if (!self::isFile($value)) {
            return false;
        }

        return ($value['size'] / 1024) <= $maxKB;
    }

    /**
     * Check if a file size is between a minimum and maximum in kilobytes.
     * @param mixed $value The value to check.
     * @param string $param Comma-separated min and max file size in kilobytes (e.g. "100,500").
     */
    public static function betweenFile($value, string $param): bool
    {
        if (!self::isFile($value)) {
            return false;
        }

        [$min, $max] = array_map('intval', explode(',', $param));
        $sizeKB = $value['size'] / 1024;

        return $sizeKB >= $min && $sizeKB <= $max;
    }

    /* ----- MAIN VALIDATION METHOD ----- */

    /**
     * Validate data against a set of rules.
     * @param array<string, mixed> $data The data to validate.
     * @param array<string, string> $rules The validation rules (e.g. ['email' => 'required|email']).
     * @param array<string, string> $messages Custom error messages (e.g. ['email.required' => 'Email is required.']).
     */
    public function make(array $data, array $rules, array $messages = []): void
    {
        $this->errors = [];
        $this->firstError = null;

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $ruleList = explode('|', $ruleString);

            foreach ($ruleList as $rule) {

                $param = null;
                $ruleName = $rule;

                if (str_contains($rule, ':')) {
                    [$ruleName, $param] = explode(':', $rule, 2);
                }

                $fileRuleCheck = null;
                if (self::isFile($value)) {
                    $fileRuleCheck = match ($ruleName) {
                        'file'       => true,
                        'image'      => self::isImage($value),
                        'mimes'      => self::mimes($value, (string)$param),
                        'mimetypes'  => self::mimeTypes($value, (string)$param),
                        'max'        => self::maxFile($value, (int)$param),
                        'between'    => self::betweenFile($value, (string)$param),
                        default      => null
                    };
                }

                if ($fileRuleCheck !== null) {
                    $valid = $fileRuleCheck;
                } else {
                    $valid = match ($ruleName) {
                        'required' => self::required($value),
                        'email' => self::email($value),
                        'string' => is_string($value),
                        'min' => self::minLength($value, (int)$param),
                        'max' => self::maxLength($value, (int)$param),
                        'numeric' => self::numeric($value),
                        'integer' => self::integer($value),
                        'boolean' => self::boolean($value),
                        'alpha' => self::alpha($value),
                        'alpha_num' => self::alphaNum($value),
                        'in' => self::inList($value, (string)$param),
                        'not_in' => self::notInList($value, (string)$param),
                        'date' => self::date($value),
                        'url' => self::url($value),
                        'array' => is_array($value),
                        default => throw new \Exception("Validation rule '$ruleName' does not exist."),
                    };
                }

                if (!$valid) {
                    $key = $field . '.' . $ruleName;
                    $msg = $messages[$key] ?? "$field validation failed for $ruleName";
                    $this->errors[$field][] = $msg;

                    $this->firstError ??= $msg;
                }
            }
        }
    }

    /* ----- RESULTS ----- */
    /**
     * Check if validation failed.
     */
    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Get all validation errors.
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the first validation error message.
     */
    public function first(): ?string
    {
        return $this->firstError;
    }
}
