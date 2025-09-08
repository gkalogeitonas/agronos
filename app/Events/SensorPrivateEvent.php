<?php

namespace App\Events;

/**
 * Backwards-compat shim. The event was renamed to SensorReadingEvent.
 * Keep this class so older code referencing the old name keeps working,
 * but prefer using SensorReadingEvent in new code.
 */
trigger_error('App\\Events\\SensorPrivateEvent is deprecated; use App\\Events\\SensorReadingEvent instead', E_USER_DEPRECATED);

class SensorPrivateEvent extends SensorReadingEvent
{
    // empty shim - functionality lives in SensorReadingEvent
}
