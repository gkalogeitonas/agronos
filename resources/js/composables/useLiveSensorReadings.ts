import type { Sensor } from '@/types/sensor';
import { useEcho } from '@laravel/echo-vue';
import { reactive } from 'vue';

export interface LiveReading {
    value: string | number | null;
    time: string | null;
}

/**
 * Keeps the last reading of each sensor up to date from its private `sensor.{id}` channel.
 * Must be called during component setup.
 */
export function useLiveSensorReadings(sensors: Sensor[]): Record<number, LiveReading> {
    const readings = reactive<Record<number, LiveReading>>({});

    sensors.forEach((sensor) => {
        readings[sensor.id] = { value: sensor.last_reading ?? null, time: sensor.last_reading_at ?? null };

        useEcho<{ value?: string | number; time?: string }>(`sensor.${sensor.id}`, 'SensorReadingEvent', (payload) => {
            if (payload?.value !== undefined) {
                readings[sensor.id].value = payload.value;
            }
            if (payload?.time) {
                readings[sensor.id].time = payload.time;
            }
        });
    });

    return readings;
}
