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

];
