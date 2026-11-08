<script setup lang="ts">
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
import { router } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    /** URL the DELETE request is sent to. */
    url: string;
    /** Name of the thing being deleted, shown in the dialog title. */
    itemName: string;
    description?: string;
    /** Keep the scroll position, for a delete that returns to the same page. */
    preserveScroll?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    description: 'This cannot be undone.',
    preserveScroll: false,
});

const open = ref(false);
const processing = ref(false);

function destroy(): void {
    router.delete(props.url, {
        preserveScroll: props.preserveScroll,
        onStart: () => (processing.value = true),
        onSuccess: () => (open.value = false),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot name="trigger">
                <Button variant="outline" class="text-destructive hover:text-destructive"><Trash2 /> Delete</Button>
            </slot>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>Delete {{ itemName }}?</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>
                <Button variant="destructive" :disabled="processing" @click="destroy">Delete</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
