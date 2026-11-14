<script setup lang="ts">
import NativeSelect from '@/components/agronos/NativeSelect.vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

export interface FarmPlanting {
    id: number;
    crop_id: number;
    crop_name: string;
    variety: string | null;
    started_on: string | null;
    ended_on: string | null;
    status: 'current' | 'upcoming' | 'past';
}

export interface CropOption {
    id: number;
    name: string;
}

/**
 * Dialog that adds a whole-farm planting, or corrects one. Both dates are optional.
 * When the new planting would follow the current one, the server asks first; the grower can then end the current one on the new start date.
 */
const props = defineProps<{
    farmId: number;
    crops: CropOption[];
    /** Planting to correct; omit to add a new one. */
    planting?: FarmPlanting;
}>();

const open = ref(false);

function initialValues() {
    return {
        crop_id: (props.planting?.crop_id ?? '') as number | '',
        variety: props.planting?.variety ?? '',
        started_on: props.planting?.started_on ?? '',
        ended_on: props.planting?.ended_on ?? '',
        end_current: false,
    };
}

const form = useForm(initialValues());
const idSuffix = props.planting ? `-${props.planting.id}` : '';

function onOpenChange(isOpen: boolean): void {
    open.value = isOpen;
    if (isOpen) {
        form.defaults(initialValues());
        form.reset();
        form.clearErrors();
    }
}

/** The offer to end the current planting names the start date, so it no longer holds once that date changes. */
watch(
    () => form.started_on,
    () => form.clearErrors('end_current'),
);

function submit(endCurrent = false): void {
    form.end_current = endCurrent;
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (props.planting) {
        form.put(route('farms.plantings.update', [props.farmId, props.planting.id]), options);
    } else {
        form.post(route('farms.plantings.store', props.farmId), options);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <form class="flex flex-col gap-6" @submit.prevent="submit()">
                <DialogHeader class="space-y-3">
                    <DialogTitle>{{ planting ? `Edit ${planting.crop_name}` : 'Add crop' }}</DialogTitle>
                    <DialogDescription>
                        What grows on the whole farm. Leave a date empty if you do not know it: no start means long ago, no end means still growing.
                    </DialogDescription>
                </DialogHeader>
                <FormField :id="`planting-crop${idSuffix}`" label="Crop" :error="form.errors.crop_id">
                    <NativeSelect :id="`planting-crop${idSuffix}`" v-model="form.crop_id" required>
                        <option value="" disabled>Choose a crop</option>
                        <option v-for="crop in crops" :key="crop.id" :value="crop.id">{{ crop.name }}</option>
                    </NativeSelect>
                </FormField>
                <FormField :id="`planting-variety${idSuffix}`" label="Variety (optional)" :error="form.errors.variety">
                    <Input :id="`planting-variety${idSuffix}`" v-model="form.variety" maxlength="255" />
                </FormField>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField :id="`planting-start${idSuffix}`" label="Started (optional)" :error="form.errors.started_on">
                        <Input :id="`planting-start${idSuffix}`" v-model="form.started_on" type="date" />
                    </FormField>
                    <FormField :id="`planting-end${idSuffix}`" label="Ended (optional)" :error="form.errors.ended_on">
                        <Input :id="`planting-end${idSuffix}`" v-model="form.ended_on" type="date" />
                    </FormField>
                </div>
                <div v-if="form.errors.end_current" class="flex flex-col gap-3 rounded-md border p-3 text-body">
                    <p class="m-0">{{ form.errors.end_current }}</p>
                    <div>
                        <Button type="button" size="sm" :disabled="form.processing" @click="submit(true)">End it and add</Button>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing">{{ planting ? 'Save' : 'Add crop' }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
