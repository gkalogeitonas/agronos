<script setup lang="ts">
import ConfirmDeleteButton from '@/components/agronos/ConfirmDeleteButton.vue';
import DescriptionList from '@/components/agronos/DescriptionList.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Gateway } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    gateway: Gateway;
    password: string;
    topic: string;
    /** Public broker address from MQTT_PUBLIC_URL; null when the setting is empty. */
    brokerUrl: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gateways', href: route('gateways.index') },
    { title: props.gateway.name, href: route('gateways.show', props.gateway.id) },
];

const showPassword = ref(false);

const credentials = computed(() => [
    ...(props.brokerUrl ? [{ mono: true, stacked: true, label: 'Broker', value: props.brokerUrl }] : []),
    { mono: true, stacked: true, label: 'Username', value: props.gateway.mqtt_username },
    { mono: true, stacked: true, key: 'password', label: 'Password', value: props.password },
    { mono: true, stacked: true, label: 'Topic', value: props.topic },
]);

const rotateForm = useForm({});
const rotatePassword = () => rotateForm.post(route('gateways.rotate-password', props.gateway.id), { preserveScroll: true });
</script>

<template>
    <Head :title="gateway.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PageContainer>
            <PageHeader eyebrow="Gateways" :title="gateway.name">
                <template #actions>
                    <ConfirmDeleteButton
                        :url="route('gateways.destroy', gateway.id)"
                        :item-name="gateway.name"
                        description="The gateway's MQTT credentials stop working. This cannot be undone."
                    />
                </template>
            </PageHeader>

            <SectionCard title="MQTT credentials" class="max-w-2xl">
                <template #actions>
                    <Button variant="outline" :disabled="rotateForm.processing" @click="rotatePassword"><RefreshCw /> Rotate password</Button>
                </template>
                <p class="m-0 text-body text-muted-foreground">Enter these in the gateway's web config.</p>
                <DescriptionList :items="credentials">
                    <template #value-password>
                        <span class="flex items-center gap-3">
                            {{ showPassword ? password : '•'.repeat(8) }}
                            <Button variant="ghost" size="sm" @click="showPassword = !showPassword">{{ showPassword ? 'Hide' : 'Show' }}</Button>
                        </span>
                    </template>
                </DescriptionList>
            </SectionCard>
        </PageContainer>
    </AppLayout>
</template>
