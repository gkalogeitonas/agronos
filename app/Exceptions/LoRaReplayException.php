<?php

namespace App\Exceptions;

class LoRaReplayException extends \RuntimeException
{
    public function __construct(int $incomingFcnt, int $storedFcnt)
    {
        parent::__construct(
            "Replay detected: incoming frame counter ({$incomingFcnt}) <= stored counter ({$storedFcnt})."
        );
    }
}
