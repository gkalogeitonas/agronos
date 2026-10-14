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

export const LOW_BATTERY_PERCENT = 20;

export const isLowBattery = (level: number | null | undefined): boolean => level !== null && level !== undefined && level < LOW_BATTERY_PERCENT;

export const formatBattery = (level: number | null | undefined): string => {
    if (level === null || level === undefined) {
        return '–';
    }
    return isLowBattery(level) ? `${level} % · low` : `${level} %`;
};

export const deviceTypeLabel = (type: string): string => ({ lora: 'LoRa', wifi: 'Wi-Fi', other: 'Other' })[type.toLowerCase()] ?? type;
