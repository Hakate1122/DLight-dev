<?php

declare(strict_types=1);

use DLight\Attribute\DLightRemoved;
use PHPUnit\Framework\TestCase;

class DLightRemovedTest extends TestCase
{
    public function testDeprecatedAttributeCanEmitWarning(): void
    {
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            if ($severity === E_USER_DEPRECATED) {
                $warnings[] = $message;
                return true;
            }

            return false;
        });

        try {
            $attribute = new DLightRemoved('Use DLight\\Attribute\\Route instead.');
            $attribute->warn();
        } finally {
            restore_error_handler();
        }

        $this->assertSame(['Use DLight\\Attribute\\Route instead.'], $warnings);
    }
}
