<script setup lang="ts">
export interface ActivityItem {
    id: string | number;
    when: string;
    subject?: string;
    text: string;
}

defineProps<{
    items: ActivityItem[];
}>();
</script>

<template>
    <ul class="m-0 flex list-none flex-col p-0">
        <li v-for="item in items" :key="item.id" class="flex flex-wrap items-baseline gap-x-5 gap-y-2 border-b border-row py-3 last:border-b-0">
            <span class="flex-[0_0_72px] font-mono text-[13px] text-muted-foreground">{{ item.when }}</span>
            <span class="flex-[1_1_260px] text-[15px]">
                <template v-if="item.subject"
                    ><span class="font-semibold">{{ item.subject }}</span> · </template
                >{{ item.text }}
            </span>
        </li>
        <li v-if="items.length === 0" class="py-4 text-[15px] text-muted-foreground">No activity yet.</li>
    </ul>
</template>
