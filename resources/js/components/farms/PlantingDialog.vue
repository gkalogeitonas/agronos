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
import { computed, ref, watch } from 'vue';

export interface FarmPlanting {
    id: number;
    /** The zone the planting is of, or null for the whole farm. */
    zone_id: number | null;
    zone_name: string | null;
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

export interface ZoneOption {
    id: number;
    name: string;
}

/** Value of the crop select's "Add a crop…" option, which opens the field for a new crop's name. */
const ADD_CROP = 'add';

/** What the crop select holds: a crop id, nothing chosen yet, or "Add a crop…". */
type CropChoice = number | '' | typeof ADD_CROP;

/**
 * Dialog that adds a planting of the whole farm or one of its zones, or corrects one. Both dates are optional.
 * When the new planting would follow the current one, the server asks first; the grower can then end the current one on the new start date.
 * A crop missing from the list can be added from the crop select by name; once added it is selected.
 */
const props = defineProps<{
    farmId: number;
    crops: CropOption[];
    /** The farm's zones, offered as land after the whole farm. */
    zones: ZoneOption[];
    /** Planting to correct; omit to add a new one. */
    planting?: FarmPlanting;
}>();

const open = ref(false);

function initialValues() {
    return {
        /** Empty for the whole farm; the server reads an empty value as none. */
        zone_id: (props.planting?.zone_id ?? '') as number | '',
        crop_id: (props.planting?.crop_id ?? '') as CropChoice,
        variety: props.planting?.variety ?? '',
        started_on: props.planting?.started_on ?? '',
        ended_on: props.planting?.ended_on ?? '',
        end_current: false,
    };
}

const form = useForm(initialValues());
const idSuffix = props.planting ? `-${props.planting.id}` : '';

const cropForm = useForm({ name: '' });
/** The crop chosen before "Add a crop…", restored if adding is cancelled. */
let cropBeforeAdding: CropChoice = '';

const isAddingCrop = computed(() => form.crop_id === ADD_CROP);

watch(
    () => form.crop_id,
    (cropId, previous) => {
        if (cropId === ADD_CROP && previous !== ADD_CROP) {
            cropBeforeAdding = previous;
            cropForm.reset();
            cropForm.clearErrors();
        }
    },
);

function cancelAddingCrop(): void {
    form.crop_id = cropBeforeAdding;
}

function addCrop(): void {
    cropForm.post(route('crops.store'), {
        preserveScroll: true,
        preserveState: true,
        onFlash: ({ createdCrop }) => {
            if (createdCrop) {
                form.crop_id = createdCrop.id;
            }
        },
    });
}

function onOpenChange(isOpen: boolean): void {
    open.value = isOpen;
    if (isOpen) {
        form.defaults(initialValues());
        form.reset();
        form.clearErrors();
    }
}

/** The offer to end the current planting names that planting and the start date, so it no longer holds once either the land or the date changes. */
watch(
    () => [form.started_on, form.zone_id],
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
                        What grows on the whole farm or one zone. A zone's crop overrides the farm's there. Leave a date empty if you do not know it:
                        no start means long ago, no end means still growing.
                    </DialogDescription>
                </DialogHeader>
                <FormField :id="`planting-land${idSuffix}`" label="Land" :error="form.errors.zone_id">
                    <NativeSelect :id="`planting-land${idSuffix}`" v-model="form.zone_id">
                        <option value="">Whole farm</option>
                        <option v-for="zone in zones" :key="zone.id" :value="zone.id">{{ zone.name }}</option>
                    </NativeSelect>
                </FormField>
                <FormField :id="`planting-crop${idSuffix}`" label="Crop" :error="form.errors.crop_id">
                    <NativeSelect :id="`planting-crop${idSuffix}`" v-model="form.crop_id" required>
                        <option value="" disabled>Choose a crop</option>
                        <option v-for="crop in crops" :key="crop.id" :value="crop.id">{{ crop.name }}</option>
                        <option :value="ADD_CROP">Add a crop…</option>
                    </NativeSelect>
                </FormField>
                <div v-if="isAddingCrop" class="flex flex-col gap-3 rounded-md border p-3">
                    <FormField :id="`new-crop-name${idSuffix}`" label="New crop name" :error="cropForm.errors.name">
                        <Input
                            :id="`new-crop-name${idSuffix}`"
                            v-model="cropForm.name"
                            maxlength="255"
                            autofocus
                            @keydown.enter.prevent="addCrop()"
                        />
                    </FormField>
                    <div class="flex gap-2">
                        <Button type="button" size="sm" :disabled="cropForm.processing || !cropForm.name.trim()" @click="addCrop()">
                            Add to my crops
                        </Button>
                        <Button type="button" size="sm" variant="secondary" @click="cancelAddingCrop()">Cancel</Button>
                    </div>
                </div>
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
                    <Button type="submit" :disabled="form.processing || isAddingCrop">{{ planting ? 'Save' : 'Add crop' }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
