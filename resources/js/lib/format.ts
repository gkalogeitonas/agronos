import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';

/**
 * Display formatters for readings and device health. Missing values render as an en dash.
 */
export const formatReading = (value: string | number | null | undefined, unit = '', digits = 1): string => {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
        return '–';
    }
    const rounded = Number(Number(value).toFixed(digits));
    return unit ? `${rounded} ${unit}` : String(rounded);
};

export const formatSignal = (dbm: number | null | undefined): string => (dbm === null || dbm === undefined ? '–' : `${dbm} dBm`);

/** Threshold comes from the backend (`config/agronos.php`) via the shared `lowBatteryPercent` prop. */
export const isLowBattery = (level: number | null | undefined): boolean =>
    level !== null && level !== undefined && level < usePage<AppPageProps>().props.lowBatteryPercent;

export const formatBattery = (level: number | null | undefined): string => {
    if (level === null || level === undefined) {
        return '–';
    }
    return isLowBattery(level) ? `${level} % · low` : `${level} %`;
};

export const deviceTypeLabel = (type: string): string => ({ lora: 'LoRa', wifi: 'Wi-Fi', other: 'Other' })[type.toLowerCase()] ?? type;

/** Upper-cases the first letter: `online` → `Online`. */
export const capitalize = (value: string): string => value.charAt(0).toUpperCase() + value.slice(1);
