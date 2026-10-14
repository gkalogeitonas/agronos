<script setup lang="ts">
export interface DescriptionItem {
    /** Names the `value-{key}` slot; defaults to the label. */
    key?: string;
    label: string;
    value?: string | number | null;
    /** Render the value in the mono reading face, truncated with the full value as its title. */
    mono?: boolean;
    /** Put the label above the value instead of beside it. */
    stacked?: boolean;
}

defineProps<{
    items: DescriptionItem[];
}>();
</script>

<template>
    <dl class="m-0 flex flex-col">
        <div
            v-for="item in items"
            :key="item.key ?? item.label"
            class="flex gap-4 border-b border-row py-3 last:border-b-0"
            :class="item.stacked ? 'flex-col gap-1' : 'justify-between'"
        >
            <dt class="text-sm text-muted-foreground">{{ item.label }}</dt>
            <dd
                class="m-0 min-w-0"
                :class="[item.mono ? 'truncate font-mono text-sm' : 'text-body', { 'text-right': !item.stacked }]"
                :title="item.mono && item.value != null ? String(item.value) : undefined"
            >
                <slot :name="`value-${item.key ?? item.label}`" :item="item">{{ item.value == null || item.value === '' ? '–' : item.value }}</slot>
            </dd>
        </div>
    </dl>
</template>
