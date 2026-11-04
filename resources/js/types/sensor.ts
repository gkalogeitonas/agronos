export interface Sensor {
    id: number;
    name: string;
    uuid: string;
    type: string;
    device_id: number;
    /** The sensor's farm is its device's farm (ADR-0008); `farm_id` is null while the device is unassigned. */
    device?: { id: number; name: string; status: string; farm_id: number | null } | null;
    lat: number;
    lon: number;
    last_reading_at?: string | null;
    last_reading?: string | number | null;
}
