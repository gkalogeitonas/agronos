import { mapStyles, type MapStyle } from '@/lib/map';
import mapboxgl from 'mapbox-gl';
import 'mapbox-gl/dist/mapbox-gl.css';
import { onBeforeUnmount, onMounted, shallowRef, type Ref, type ShallowRef } from 'vue';

export type UseMapboxOptions = Omit<mapboxgl.MapOptions, 'container' | 'style'> & {
    style?: MapStyle;
    /** Adds the fullscreen control. Defaults to true. */
    fullscreen?: boolean;
    /** Shows the compass in the navigation control. Defaults to true. */
    compass?: boolean;
};

/**
 * Creates a Mapbox map in `container` on mount, with the app's token, style and controls, and removes it on unmount.
 * `whenLoaded` runs a callback once the style has loaded (immediately if it already has).
 * Must be called during component setup. Does nothing if the container is not rendered.
 */
export function useMapbox(
    container: Ref<HTMLElement | null>,
    options: UseMapboxOptions,
): { map: ShallowRef<mapboxgl.Map | null>; whenLoaded: (callback: (map: mapboxgl.Map) => void) => void } {
    const map = shallowRef<mapboxgl.Map | null>(null);
    const pending: ((map: mapboxgl.Map) => void)[] = [];
    let loaded = false;

    const whenLoaded = (callback: (map: mapboxgl.Map) => void): void => {
        if (map.value && loaded) {
            callback(map.value);
        } else {
            pending.push(callback);
        }
    };

    onMounted(() => {
        if (!container.value) {
            return;
        }

        const { style = 'satellite', fullscreen = true, compass = true, zoom = 12, ...mapOptions } = options;
        mapboxgl.accessToken = import.meta.env.VITE_MAPBOX_TOKEN as string;

        const instance = new mapboxgl.Map({ ...mapOptions, zoom, container: container.value, style: mapStyles[style] });
        instance.addControl(new mapboxgl.NavigationControl({ showCompass: compass }));
        if (fullscreen) {
            instance.addControl(new mapboxgl.FullscreenControl());
        }
        instance.on('load', () => {
            loaded = true;
            pending.splice(0).forEach((callback) => callback(instance));
        });

        map.value = instance;
    });

    onBeforeUnmount(() => {
        map.value?.remove();
        map.value = null;
    });

    return { map, whenLoaded };
}
