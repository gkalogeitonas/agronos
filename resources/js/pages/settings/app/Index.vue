<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Cpu, QrCode } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Application settings', href: '/settings/app' }];

const links = [
    { title: 'Devices', description: 'Register devices and manage their MQTT credentials.', href: '/devices', icon: Cpu },
    { title: 'Device QR generator', description: 'Print QR codes that pre-fill device registration.', href: '/tools/device-qr', icon: QrCode },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Application settings" />

        <SettingsLayout>
            <div class="flex flex-col gap-6">
                <HeadingSmall title="Application" description="Devices and tools for your organisation" />
                <ul class="m-0 flex list-none flex-col gap-3 p-0">
                    <li v-for="link in links" :key="link.href">
                        <Link :href="link.href" class="flex min-h-11 items-center gap-4 rounded-md border p-4 text-foreground hover:bg-accent">
                            <component :is="link.icon" class="size-5 text-primary" aria-hidden="true" />
                            <span class="flex flex-1 flex-col gap-0.5">
                                <span class="font-semibold">{{ link.title }}</span>
                                <span class="text-sm text-muted-foreground">{{ link.description }}</span>
                            </span>
                            <ChevronRight class="size-4 text-muted-foreground" aria-hidden="true" />
                        </Link>
                    </li>
                </ul>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
