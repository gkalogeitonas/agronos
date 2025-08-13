export interface Sensor {
  id: number;
  name: string;
  uuid: string;
  type: string;
  device_id: number;
  lat: number;
  lon: number;
  last_reading_at?: string | null;
  last_reading?: string | number | null;
}
