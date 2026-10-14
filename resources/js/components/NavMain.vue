<script setup lang="ts">
import { SidebarGroup, SidebarMenu, SidebarMenuBadge, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();

/**
 * A nav item is active on its own URL and on any page nested under it (e.g. /farms/3).
 */
const isActive = (item: NavItem): boolean => page.url === item.href || page.url.startsWith(`${item.href}/`);
</script>

<template>
    <SidebarGroup class="px-3 py-0">
        <SidebarMenu class="gap-1">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton as-child :is-active="isActive(item)" :tooltip="item.title" class="h-11 text-body">
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuBadge
                    v-if="item.badge"
                    class="top-3 right-2 rounded-full bg-sun px-2 font-mono text-micro font-medium text-sidebar peer-hover/menu-button:text-sidebar"
                >
                    {{ item.badge }}
                </SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
