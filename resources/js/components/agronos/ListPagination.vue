<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { Pagination } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    pagination: Pagination;
    /** Plural noun for the rows, e.g. "devices". */
    noun: string;
}>();
</script>

<template>
    <nav aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3">
        <span class="font-mono text-meta text-muted-foreground" aria-live="polite">
            Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }} {{ noun }}
        </span>
        <div v-if="pagination.last_page > 1" class="flex items-center gap-2">
            <Button v-if="pagination.prev_url" variant="outline" size="sm" as-child>
                <Link :href="pagination.prev_url" preserve-scroll rel="prev">Previous</Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled>Previous</Button>
            <span class="font-mono text-meta text-muted-foreground">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
            <Button v-if="pagination.next_url" variant="outline" size="sm" as-child>
                <Link :href="pagination.next_url" preserve-scroll rel="next">Next</Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled>Next</Button>
        </div>
    </nav>
</template>
