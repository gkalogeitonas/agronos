<script setup lang="ts" generic="Row extends Record<string, unknown>">
import { computed } from 'vue';

export interface DataTableColumn {
    key: string;
    label: string;
    /** CSS grid track size, e.g. `2fr` or `minmax(0,1fr)`. Defaults to `1fr`. */
    width?: string;
    /** Render the cell in the mono reading face. */
    mono?: boolean;
    align?: 'start' | 'end';
}

interface Props {
    columns: DataTableColumn[];
    rows: Row[];
    rowKey: keyof Row;
    minWidth?: string;
    emptyText?: string;
}

const props = withDefaults(defineProps<Props>(), {
    minWidth: '620px',
    emptyText: 'Nothing to show yet.',
});

const gridTemplate = computed(() => props.columns.map((column) => column.width ?? '1fr').join(' '));
</script>

<template>
    <div class="overflow-x-auto">
        <div role="table" class="flex flex-col" :style="{ minWidth }">
            <div role="row" class="grid gap-4 border-b px-3 pb-2.5 eyebrow text-xs" :style="{ gridTemplateColumns: gridTemplate }">
                <span v-for="column in columns" :key="column.key" role="columnheader" :class="{ 'text-right': column.align === 'end' }">{{
                    column.label
                }}</span>
            </div>
            <div
                v-for="row in rows"
                :key="String(row[rowKey])"
                role="row"
                class="grid items-center gap-4 border-b border-row px-3 py-3.5 text-body"
                :style="{ gridTemplateColumns: gridTemplate }"
            >
                <span
                    v-for="column in columns"
                    :key="column.key"
                    role="cell"
                    class="min-w-0"
                    :class="{ 'font-mono text-meta': column.mono, 'justify-self-end text-right': column.align === 'end' }"
                >
                    <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">{{ row[column.key] ?? '–' }}</slot>
                </span>
            </div>
            <div v-if="rows.length === 0" class="px-3 py-8 text-center text-body text-muted-foreground">
                <slot name="empty">{{ emptyText }}</slot>
            </div>
        </div>
    </div>
</template>
