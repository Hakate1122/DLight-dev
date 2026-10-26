<?php

namespace DLight\Command\Helper;

use function array_key_exists;
use function count;
use function in_array;
use function is_string;

/**
 * **ConsoleInput Helper**
 * 
 * ConsoleInput provides methods to prompt user input from the console,
 * including validation and special input types.
 * 
 * Example usage: 
 * 
 * * ConsoleInput::prompt("Enter your name:");
 * * ConsoleInput::askYesNo("Continue?", true);
 * * ConsoleInput::select("Choose option:", ["1" => "One", "2" => "Two"], "1");
 * * ConsoleInput::askChoice("What is 2+2?", ["3", "4", "5"], 1);
 * * ConsoleInput::promptSecret("Enter password:");
 */
class ConsoleInput
{
    /**
     * Basic prompt with optional validation.
     * 
     * Usage:
     * 
     * ConsoleInput::prompt("Enter your name:", "Default Name", ConsoleInput::validateNotEmpty());
     *
     * @param string $message The prompt message.
     * @param string|null $default The default value if input is empty.
     * @param callable|null $validator  Return true if valid, or string error message if invalid. The valid validator fields include: `ConsoleInput::validateNotEmpty()`, `ConsoleInput::validateString()`, `ConsoleInput::validateUrl()`, `ConsoleInput::validateNumber()`, `ConsoleInput::validateEmail()`, `ConsoleInput::validateRegex($pattern)`.
     */
    public static function prompt(
        string $message,
        ?string $default = null,
        ?callable $validator = null
    ): string {
        while (true) {
            $msg = $default !== null
                ? "{$message} [default: {$default}]: "
                : "{$message}: ";

            echo $msg;

            $input = fgets(STDIN);

            if ($input === false) {
                return $default ?? '';
            }

            $input = trim($input);

            if ($input === '' && $default !== null) {
                return $default;
            }

            if ($validator !== null) {
                $valid = $validator($input);

                if ($valid === true) {
                    return $input;
                }

                if (is_string($valid)) {
                    echo cli_red("Wrong input: {$valid}\n");
                    continue;
                }
            }

            if ($input !== '') {
                return $input;
            }

            echo cli_red("Input cannot be empty. Please try again.\n");
        }
    }


    /**
     * Ask Yes/No question.
     * Return: true = Yes, false = No
     *
     * @param string $message The question message.
     * @param bool $default Default answer if input is empty (true = Yes, false = No).
     */
    public static function askYesNo(
        string $message,
        bool $default = true
    ): bool {
        $defaultText = $default ? "Y/n" : "y/N";

        while (true) {
            echo "{$message} [{$defaultText}]: ";
            $input = trim(fgets(STDIN));

            if ($input === '') {
                return $default;
            }

            $lower = strtolower($input);

            if (in_array($lower, ['y', 'yes'], true)) {
                return true;
            }
            if (in_array($lower, ['n', 'no'], true)) {
                return false;
            }

            echo cli_red("Please answer yes or no.\n");
        }
    }

    /**
     * Shortcut for askYesNo with default = false.
     *
     * @param string $message The question message.
     */
    public static function confirm(string $message): bool
    {
        return self::askYesNo($message, false);
    }

    /**
     * Let user select an option from a list.
     *
     * @param string $message The prompt message.
     * @param array $options Key-value pairs of options (key => label).
     * @return string selected key
     */
    public static function select(
        string $message,
        array $options,
        ?string $defaultKey = null
    ): string {

        echo "{$message}:\n";

        foreach ($options as $key => $label) {
            echo "  [$key] $label\n";
        }

        while (true) {
            $prompt = $defaultKey ? "Choose option [default: {$defaultKey}]: " : "Choose option: ";
            echo $prompt;

            $input = trim(fgets(STDIN));

            if ($input === '' && $defaultKey !== null) {
                return $defaultKey;
            }

            if (array_key_exists($input, $options)) {
                return $input;
            }

            echo cli_red("Invalid option. Try again.\n");
        }
    }

    /**
     * Ask a multiple-choice question and return if the selected answer is correct.
     *
     * @param string $question The question to ask.
     * @param array $choices List of answer choices (index => label).
     * @param int $correctIndex The index of the correct answer in the choices array.
     * @return bool True if the user's choice is correct, false otherwise.
     */
    public static function askChoice(
        string $question,
        array $choices,
        int $correctIndex
    ): bool {
        echo "Question: {$question}\n";

        foreach ($choices as $index => $label) {
            $displayIndex = $index + 1;
            echo "  [$displayIndex] $label\n";
        }

        while (true) {
            echo "Your answer (1-" . count($choices) . "): ";
            $input = trim(fgets(STDIN));

            if (!is_numeric($input)) {
                echo cli_red("Please enter a number.\n");
                continue;
            }

            $selectedIdx = (int)$input - 1;

            if (isset($choices[$selectedIdx])) {
                return $selectedIdx === $correctIndex;
            }

            echo cli_red("Invalid choice. Please choose between 1 and " . count($choices) . ".\n");
        }
    }

    /**
     * Prompt for secret input (e.g., password) without echoing.
     *
     * **Note:** Not working on Windows consoles.
     *
     * @param string $message The prompt message.
     * @param string|null $default The default value if input is empty.
     */
    public static function promptSecret(
        string $message,
        ?string $default = null
    ): string {
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            echo cli_red("Warning: Secret input may be visible on Windows consoles.\n");
            return self::prompt($message, $default);
        }
        $cmd = "/usr/bin/env bash -c 'read -s -p \"" . addslashes($message) . ": \" mypassword && echo \$mypassword'";
        $input = rtrim(shell_exec($cmd));
        echo "\n";
        if ($input === '' && $default !== null) {
            return $default;
        }
        return $input;
    }


    // --- Validators ---

    /* Validate that input is not empty. */
    public static function validateNotEmpty(): callable
    {
        return fn($value) => $value !== ''
            ? true
            : "Value cannot be empty.";
    }

    /* Validate that input is a string. */
    public static function validateString(): callable
    {
        return fn($value) => is_string($value)
            ? true
            : "Value must be a string.";
    }

    /* Validate that input is a valid URL. */
    public static function validateUrl(): callable
    {
        return fn($value) => filter_var($value, FILTER_VALIDATE_URL)
            ? true
            : "Invalid URL format.";
    }

    /** Validate that input is a number. */
    public static function validateNumber(): callable
    {
        return fn($value) => is_numeric($value)
            ? true
            : "Value must be a number.";
    }

    /** Validate that input is a valid email address. */
    public static function validateEmail(): callable
    {
        return fn($value) => filter_var($value, FILTER_VALIDATE_EMAIL)
            ? true
            : "Invalid email format.";
    }

    /** Validate that input matches a regex pattern. */
    public static function validateRegex(string $pattern): callable
    {
        return fn($value) => preg_match($pattern, $value)
            ? true
            : "Input does not match required pattern.";
    }
}
