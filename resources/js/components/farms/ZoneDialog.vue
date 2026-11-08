<script setup lang="ts">
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
import { ref } from 'vue';

/**
 * Dialog that adds a zone to a farm, or renames one. An empty name gives the zone the next automatic name.
 */
const props = defineProps<{
    farmId: number;
    /** Zone to rename; omit to add a new one. */
    zone?: { id: number; name: string };
    /** The name the zone gets when the field is left empty. */
    nextZoneName: string;
}>();

const open = ref(false);
const form = useForm({ name: props.zone?.name ?? '' });
const fieldId = props.zone ? `zone-name-${props.zone.id}` : 'zone-name';

function onOpenChange(isOpen: boolean): void {
    open.value = isOpen;
    if (isOpen) {
        form.defaults({ name: props.zone?.name ?? '' });
        form.reset();
        form.clearErrors();
    }
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (props.zone) {
        form.put(route('farms.zones.update', [props.farmId, props.zone.id]), options);
    } else {
        form.post(route('farms.zones.store', props.farmId), options);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <DialogHeader class="space-y-3">
                    <DialogTitle>{{ zone ? `Rename ${zone.name}` : 'Add zone' }}</DialogTitle>
                    <DialogDescription>A zone is a named part of this farm. Leave the name empty to call it {{ nextZoneName }}.</DialogDescription>
                </DialogHeader>
                <FormField :id="fieldId" label="Name" :error="form.errors.name">
                    <Input :id="fieldId" v-model="form.name" :placeholder="nextZoneName" maxlength="255" />
                </FormField>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing">{{ zone ? 'Save' : 'Add zone' }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
