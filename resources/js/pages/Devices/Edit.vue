<script setup lang="ts">
import DescriptionList, { type DescriptionItem } from '@/components/agronos/DescriptionList.vue';
import DeviceFarmField from '@/components/agronos/DeviceFarmField.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import FormField from '@/components/FormField.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { deviceTypeLabel } from '@/lib/format';
import { type BreadcrumbItem, type FarmOption } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    device: { id: number; name: string | null; uuid: string; type: string; farm_id: number | null };
    farms: FarmOption[];
}>();

const title = props.device.name || 'Device';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Devices', href: route('devices.index') },
    { title, href: route('devices.show', props.device.id) },
    { title: 'Edit', href: route('devices.edit', props.device.id) },
];

const details: DescriptionItem[] = [
    { label: 'UUID', value: props.device.uuid, mono: true },
    { label: 'Type', value: deviceTypeLabel(props.device.type) },
];

const form = useForm({
    name: props.device.name ?? '',
    farm_id: props.device.farm_id ?? ('' as number | ''),
});

const farmChanged = computed(() => (form.farm_id || null) !== props.device.farm_id);

function submit() {
    form.put(route('devices.update', props.device.id));
}
</script>

<template>
    <Head :title="`Edit ${title}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Edit device" :title="title" />

            <SectionCard title="Device details" class="max-w-2xl">
                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <DescriptionList :items="details" />
                    <FormField id="name" label="Device name" :error="form.errors.name"><Input id="name" v-model="form.name" required /></FormField>
                    <DeviceFarmField v-model="form.farm_id" :farms="farms" :error="form.errors.farm_id" />
                    <p v-if="farmChanged" role="alert" class="m-0 rounded-md bg-warning-soft p-3 text-sm text-warning-foreground">
                        This device's sensors keep their positions. Check them and update them for the new farm if needed.
                    </p>
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child><Link :href="route('devices.show', device.id)">Cancel</Link></Button>
                        <Button type="submit" :disabled="form.processing">Save changes</Button>
                    </div>
                </form>
            </SectionCard>
        </PageContainer>
    </AppLayout>
</template>
