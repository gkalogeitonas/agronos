<?php

namespace App\Exceptions;

class LoRaFrameCounterGapException extends \RuntimeException
{
    public function __construct(int $gap, int $maxGap)
    {
        parent::__construct(
            "Frame counter gap ({$gap}) exceeds maximum allowed ({$maxGap})."
        );
    }
}
