<script setup lang="ts">
import FarmForm from '@/components/farms/FarmForm.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Farms', href: route('farms.index') },
    { title: 'Add farm', href: route('farms.create') },
];
</script>

<template>
    <Head title="Add farm" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Farms" title="Add farm" />
            <FarmForm :action="route('farms.store')" method="post">
                <template #cancel>
                    <Button variant="outline" as-child><Link :href="route('farms.index')">Cancel</Link></Button>
                </template>
            </FarmForm>
        </PageContainer>
    </AppLayout>
</template>
