<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Low Battery Threshold
    |--------------------------------------------------------------------------
    |
    | Battery percentage below which a device is reported as low battery on
    | the overview, in the device tables and by alert rules.
    |
    */

    'low_battery_percent' => (int) env('AGRONOS_LOW_BATTERY_PERCENT', 20),

    /*
    |--------------------------------------------------------------------------
    | Offline Threshold
    |--------------------------------------------------------------------------
    |
    | Minutes without a reading after which an online device is marked offline
    | by the devices:mark-offline command. Values below 6 are raised to 6, so
    | the threshold always exceeds the command's five-minute schedule.
    |
    */

    'offline_after_minutes' => (int) env('AGRONOS_OFFLINE_AFTER_MINUTES', 60),

];
