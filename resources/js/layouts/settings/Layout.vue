<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: '/settings/profile',
    },
    {
        title: 'Password',
        href: '/settings/password',
    },
    {
        title: 'Application',
        href: '/settings/app',
    },
];

const page = usePage();

const currentPath = page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname : '';
</script>

<template>
    <div class="flex flex-col gap-6 p-6 md:p-8">
        <PageHeader eyebrow="Account" title="Settings" />

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-12">
            <aside class="w-full lg:w-48">
                <nav aria-label="Settings" class="flex flex-row flex-wrap gap-1 lg:flex-col">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['justify-start lg:w-full', { 'bg-secondary font-semibold': currentPath === item.href }]"
                        as-child
                    >
                        <Link :href="item.href" :aria-current="currentPath === item.href ? 'page' : undefined">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="lg:hidden" />

            <div class="flex-1 lg:max-w-2xl">
                <section class="flex max-w-xl flex-col gap-12 rounded-xl border bg-card p-6 md:p-8">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
