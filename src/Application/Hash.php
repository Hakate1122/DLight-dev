<?php

namespace DLight\Application;

use RuntimeException;

/**
 * **Hashing Utility**
 *
 * A simple utility class for hashing and verifying strings using default, bcrypt.
 */
class Hash
{
    /**
     * Generate a secure hash of a string using default.
     * 
     * @param string $string The input string to hash
     * @return string The hashed string
     */
    public static function default(string $string): string
    {
        $hash = password_hash($string, PASSWORD_DEFAULT);
        return $hash;
    }

    /**
     * Generate a secure hash of a string using bcrypt.
     * 
     * @param string $string The input string to hash
     * @param array $options Optional options for bcrypt (e.g., ['cost' => 12])
     * @return string The bcrypt hashed string
     */
    public static function bcrypt(string $string, $options = []): string
    {
        $hash = password_hash($string, PASSWORD_BCRYPT, $options);
        return $hash;
    }

    /**
     * Generate a secure hash of a string using Argon2i.
     *
     * @param string $string The input string to hash
     * @param array $options Optional options for Argon2i
     * @return string The Argon2i hashed string or bcrypt fallback
     */
    public static function argon2i(string $string, array $options = []): string
    {
        $hash = password_hash($string, PASSWORD_ARGON2I, $options);
        return $hash;
    }

    /**
     * Verify if a given string matches a default or bcrypt hash.
     * @param string $string The input string to verify
     * @param string $hash The hash to compare against
     * @param bool $error_on_failure Whether to throw an exception on failure
     * @return bool True if the string matches the hash, false otherwise
     */
    public static function verify(string $string, string $hash, bool $error_on_failure = false): bool
    {
        $verified = password_verify($string, $hash);

        if (!$verified && $error_on_failure) {
            throw new RuntimeException('Hash verification failed. Please check your credentials.');
        }

        return $verified;
    }
}
