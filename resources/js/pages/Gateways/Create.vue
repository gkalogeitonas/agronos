<script setup lang="ts">
import SectionCard from '@/components/agronos/SectionCard.vue';
import FormField from '@/components/FormField.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gateways', href: route('gateways.index') },
    { title: 'Add gateway', href: route('gateways.create') },
];

const form = useForm({
    name: '',
});

function submit() {
    form.post(route('gateways.store'));
}
</script>

<template>
    <Head title="Add gateway" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Gateways" title="Add gateway" />

            <SectionCard title="Gateway details" class="max-w-2xl">
                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <FormField id="name" label="Gateway name" :error="form.errors.name"
                        ><Input id="name" v-model="form.name" placeholder="Barn gateway" required
                    /></FormField>
                    <p class="m-0 text-body text-muted-foreground">Agronos generates the MQTT username and password for you.</p>
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child><Link :href="route('gateways.index')">Cancel</Link></Button>
                        <Button type="submit" :disabled="form.processing">Create gateway</Button>
                    </div>
                </form>
            </SectionCard>
        </PageContainer>
    </AppLayout>
</template>
