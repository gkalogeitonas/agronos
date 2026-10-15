import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    /** Short label shown next to the title, e.g. PROPOSED for features not built yet. */
    badge?: string;
}

/** One-time messages flashed by controllers, delivered as Inertia flash data (`HandleInertiaRequests`). */
export interface FlashMessages {
    success?: string;
    error?: string;
    info?: string;
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    /** Battery percentage below which a device counts as low battery (`config/agronos.php`). */
    lowBatteryPercent: number;
    /** Backend enum cases with labels (`HandleInertiaRequests`). */
    enums: {
        deviceStatuses: EnumOption[];
        sensorTypes: (EnumOption & { unit: string })[];
    };
};

export interface EnumOption {
    value: string;
    label: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface FarmSummary {
    id: number;
    name: string;
    location: string | null;
    size: number | null;
    center: { lng: number; lat: number } | null;
    /** Mirrors App\\Enums\\FarmHealth. */
    health: 'ok' | 'warning' | 'offline' | 'unknown';
    healthLabel: string;
    sensors: number;
    devicesOnline: number;
    devicesTotal: number;
    moisture: number | null;
    temperature: number | null;
}

export interface DeviceSummary {
    id: number;
    name: string;
    type: string;
    status: 'registered' | 'online' | 'offline' | 'error';
    signal_strength: number | null;
    battery_level: number | null;
    last_seen_at: string | null;
}
