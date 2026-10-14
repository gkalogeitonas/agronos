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

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
};

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
    center: { lng: number; lat: number } | null;
    status: 'ok' | 'warning' | 'offline' | 'unknown';
    sensors: number;
    devicesOnline: number;
    devicesTotal: number;
    moisture: number | null;
    temperature: number | null;
}
