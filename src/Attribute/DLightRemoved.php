<?php

declare(strict_types=1);

namespace DLight\Attribute;

use Attribute;

#[Attribute(
    Attribute::TARGET_CLASS |
    Attribute::TARGET_FUNCTION |
    Attribute::TARGET_METHOD |
    Attribute::TARGET_PROPERTY |
    Attribute::TARGET_PARAMETER
)]
class DLightRemoved
{
    public function __construct(
        public string $message = 'This element is deprecated.'
    ) {}

    public function getMessage(): string
    {
        return $this->message;
    }

    public function warn(?string $message = null): void
    {
        trigger_error($message ?? $this->message, E_USER_DEPRECATED);
    }

    public function __invoke(?string $message = null): void
    {
        $this->warn($message);
    }
}