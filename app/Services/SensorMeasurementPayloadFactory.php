<?php

namespace App\Services;

use App\Models\Sensor;

class SensorMeasurementPayloadFactory
{
    /**
     * Build the payload array for InfluxDB writeArray.
     *
     * @param Sensor $sensorModel
     * @param float|int $value
     * @return array
     */
    public static function make(Sensor $sensorModel, $value): array
    {
        return [
            'name' => 'sensor_measurement',
            'tags' => [
                'user_id'    => $sensorModel->user_id,
                'farm_id'    => $sensorModel->farm_id,
                'sensor_id'  => $sensorModel->id,
                'sensor_type'=> $sensorModel->type,
            ],
            'fields' => [
                // cast to float to avoid integer/float column type conflicts in InfluxDB
                'value' => (float) $value,
            ],
            // use integer seconds to match precision=s
            'time' => time(), // use server time
        ];
    }
}
