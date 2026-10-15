import { capitalize } from '@/lib/format';

/**
 * Signal colours from the Field instrument palette, for places that need raw values
 * (SVG strokes, ApexCharts). Keep in sync with the tokens in resources/css/app.css.
 */
export const signalColors = {
    ink: '#12261C',
    canopy: '#1F7A4D',
    leaf: '#14532D',
    water: '#1F6FA8',
    threshold: '#B7791F',
    alert: '#9B2C1C',
    caution: '#8A4B00',
    grid: '#DDE4D9',
    muted: '#4A6455',
} as const;

export type StatusTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

/**
 * Maps any status string used across the app (device, sensor, farm) to a colour tone.
 */
export function statusTone(status: string | null | undefined): StatusTone {
    switch ((status ?? '').toLowerCase()) {
        case 'online':
        case 'ok':
            return 'success';
        case 'delayed':
        case 'warning':
        case 'registered':
            return 'warning';
        case 'offline':
        case 'error':
            return 'danger';
        case 'info':
            return 'info';
        default:
            return 'neutral';
    }
}

/** Raw colour per status tone, for places that cannot use the CSS tokens such as map markers. */
export const toneColors: Record<StatusTone, string> = {
    success: signalColors.canopy,
    warning: signalColors.caution,
    danger: signalColors.alert,
    info: signalColors.water,
    neutral: signalColors.muted,
};

/** Units per sensor type. Mirrors App\\Enums\\SensorType::unit(). */
export const sensorUnits: Record<string, string> = {
    moisture: '%',
    temperature: '°C',
    humidity: '%',
    light: 'lux',
    battery: '%',
    other: '',
};

/** Trend line colour per sensor type, one colour per signal. */
export const sensorColors: Record<string, string> = {
    moisture: signalColors.water,
    temperature: signalColors.threshold,
    humidity: signalColors.leaf,
    light: signalColors.ink,
};

export const sensorTypeLabel = (type: string | null | undefined): string => (type ? capitalize(type) : 'Unknown');
