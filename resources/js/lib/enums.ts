import { capitalize } from '@/lib/format';
import type { AppPageProps, EnumOption } from '@/types';
import { usePage } from '@inertiajs/vue3';

/**
 * Lookups over the backend enums (`App\\Enums\\DeviceStatus`, `App\\Enums\\SensorType`),
 * shared with every page as the `enums` prop by `HandleInertiaRequests`.
 */
const enums = () => usePage<AppPageProps>().props.enums;

const sensorTypeOption = (type: string | null | undefined): (EnumOption & { unit: string }) | undefined =>
    enums().sensorTypes.find((option) => option.value === type);

/** Device statuses in display order, for filters. */
export const deviceStatusOptions = (): EnumOption[] => enums().deviceStatuses;

export const sensorTypeLabel = (type: string | null | undefined): string => sensorTypeOption(type)?.label ?? (type ? capitalize(type) : 'Unknown');

/** Unit for a sensor type's readings, empty when the type has none or is unknown. */
export const sensorUnit = (type: string | null | undefined): string => sensorTypeOption(type)?.unit ?? '';
