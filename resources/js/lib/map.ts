import { signalColors } from '@/lib/theme';
import type { Feature, Polygon } from 'geojson';
import mapboxgl from 'mapbox-gl';

export const mapStyles = {
    satellite: 'mapbox://styles/mapbox/satellite-streets-v12',
    outdoors: 'mapbox://styles/mapbox/outdoors-v12',
} as const;

export type MapStyle = keyof typeof mapStyles;

/** Padding and zoom cap used whenever a map is fitted to a farm boundary. */
export const polygonFitOptions: mapboxgl.FitBoundsOptions = { padding: 40, maxZoom: 17 };

const FARM_SOURCE = 'farm-area';
const FARM_LAYERS = ['farm-fill', 'farm-outline'];

export function isPolygon(value: unknown): value is Polygon {
    return typeof value === 'object' && value !== null && (value as Polygon).type === 'Polygon' && Array.isArray((value as Polygon).coordinates?.[0]);
}

export function polygonBounds(polygon: Polygon): mapboxgl.LngLatBounds {
    const bounds = new mapboxgl.LngLatBounds();
    polygon.coordinates[0].forEach((coord) => bounds.extend(coord as [number, number]));

    return bounds;
}

export function fitToPolygon(map: mapboxgl.Map, polygon: Polygon): void {
    map.fitBounds(polygonBounds(polygon), polygonFitOptions);
}

/**
 * Draws or replaces the farm boundary, or removes it when `polygon` is null. Call after the style has loaded.
 */
export function setFarmPolygon(map: mapboxgl.Map, polygon: Polygon | null): void {
    const source = map.getSource<mapboxgl.GeoJSONSource>(FARM_SOURCE);

    if (polygon === null) {
        if (source) {
            FARM_LAYERS.forEach((id) => map.getLayer(id) && map.removeLayer(id));
            map.removeSource(FARM_SOURCE);
        }
        return;
    }

    const data: Feature<Polygon> = { type: 'Feature', properties: {}, geometry: polygon };
    if (source) {
        source.setData(data);
        return;
    }

    map.addSource(FARM_SOURCE, { type: 'geojson', data });
    map.addLayer({ id: 'farm-fill', type: 'fill', source: FARM_SOURCE, paint: { 'fill-color': signalColors.canopy, 'fill-opacity': 0.2 } });
    map.addLayer({ id: 'farm-outline', type: 'line', source: FARM_SOURCE, paint: { 'line-color': signalColors.canopy, 'line-width': 2 } });
}
