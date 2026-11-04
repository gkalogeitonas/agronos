<?php

namespace App\Services;

use App\Models\Sensor;

class SensorMeasurementPayloadFactory
{
    /**
     * Build the payload array for InfluxDB writeArray.
     *
     * The farm is the one the sensor's device was on when the reading was taken (ADR-0008).
     * A reading from an unassigned device gets no farm tag.
     *
     * @param  float|int  $value
     */
    public static function make(Sensor $sensorModel, $value, ?int $farmId): array
    {
        $tags = ['user_id' => $sensorModel->user_id];
        if ($farmId !== null) {
            $tags['farm_id'] = $farmId;
        }
        $tags['sensor_id'] = $sensorModel->id;
        $tags['sensor_type'] = $sensorModel->type;

        return [
            'name' => 'sensor_measurement',
            'tags' => $tags,
            'fields' => [
                // cast to float to avoid integer/float column type conflicts in InfluxDB
                'value' => (float) $value,
            ],
            // use integer seconds to match precision=s
            'time' => time(), // use server time
        ];
    }
}
