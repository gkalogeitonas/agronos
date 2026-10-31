<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BellRing, Cpu, Folder, LayoutGrid, LucideWheat, QrCode, Radio, RadioTower, Settings, ToggleRight } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Farms',
        href: '/farms',
        icon: LucideWheat,
    },
    {
        title: 'Devices',
        href: '/devices',
        icon: Cpu,
    },
    {
        title: 'Gateways',
        href: '/gateways',
        icon: RadioTower,
    },
    {
        title: 'Sensors',
        href: '/sensors',
        icon: Radio,
    },
    {
        title: 'Actuators',
        href: '/actuators',
        icon: ToggleRight,
        badge: 'PROPOSED',
    },
    {
        title: 'Alerts',
        href: '/alerts',
        icon: BellRing,
    },
    {
        title: 'Settings',
        href: '/settings',
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/gkalogeitonas/agronos',
        icon: Folder,
    },
    {
        title: 'Device QR Generator',
        href: '/tools/device-qr',
        icon: QrCode,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader class="px-4 pt-6 pb-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="hover:bg-transparent active:bg-transparent">
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="gap-3 px-3 pb-4">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
