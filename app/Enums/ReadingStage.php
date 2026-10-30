<?php

namespace App\Enums;

/**
 * How far a simulated reading got along the ingestion path.
 */
enum ReadingStage: string
{
    case Sent = 'sent';
    case Received = 'received';
    case Stored = 'stored';
}
