export interface Sensor {
  id: number;
  name: string;
  uuid: string;
  type: string;
  device_id: number;
  lat: number;
  lon: number;
  last_seen?: string | null;
  last_value?: string | number | null;
}
