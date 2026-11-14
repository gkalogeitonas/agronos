<script setup lang="ts">
import ConfirmDeleteButton from '@/components/agronos/ConfirmDeleteButton.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import PlantingDialog, { type CropOption, type FarmPlanting } from '@/components/farms/PlantingDialog.vue';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { CalendarCheck, Pencil, Plus, Trash2 } from 'lucide-vue-next';

/**
 * The Crops section of Farm detail: lists what grows on the whole farm, current plantings first, then past ones.
 */
const props = defineProps<{
    farmId: number;
    plantings: FarmPlanting[];
    crops: CropOption[];
}>();

const statusLabels: Record<FarmPlanting['status'], string> = { current: 'Current', upcoming: 'Upcoming', past: 'Past' };

function period(planting: FarmPlanting): string {
    if (!planting.started_on && !planting.ended_on) {
        return 'No dates';
    }

    return `${planting.started_on ?? 'Earlier'} to ${planting.ended_on ?? 'now'}`;
}

function end(planting: FarmPlanting): void {
    router.patch(route('farms.plantings.end', [props.farmId, planting.id]), {}, { preserveScroll: true });
}
</script>

<template>
    <SectionCard title="Crops">
        <template #actions>
            <PlantingDialog :farm-id="farmId" :crops="crops">
                <Button variant="outline" size="sm"><Plus /> Add crop</Button>
            </PlantingDialog>
        </template>
        <ul v-if="plantings.length > 0" class="m-0 flex list-none flex-col divide-y p-0">
            <li v-for="planting in plantings" :key="planting.id" class="flex flex-wrap items-center justify-between gap-3 py-2">
                <div class="flex min-w-0 flex-col">
                    <span class="truncate font-medium"
                        >{{ planting.crop_name }}<template v-if="planting.variety"> · {{ planting.variety }}</template></span
                    >
                    <span class="font-mono text-meta text-muted-foreground">{{ statusLabels[planting.status] }} · {{ period(planting) }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <Button
                        v-if="planting.status === 'current'"
                        variant="ghost"
                        size="icon"
                        :aria-label="`End ${planting.crop_name} today`"
                        @click="end(planting)"
                    >
                        <CalendarCheck />
                    </Button>
                    <PlantingDialog :farm-id="farmId" :crops="crops" :planting="planting">
                        <Button variant="ghost" size="icon" :aria-label="`Edit ${planting.crop_name}`"><Pencil /></Button>
                    </PlantingDialog>
                    <ConfirmDeleteButton
                        :url="route('farms.plantings.destroy', [farmId, planting.id])"
                        :item-name="planting.crop_name"
                        preserve-scroll
                    >
                        <template #trigger>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="text-destructive hover:text-destructive"
                                :aria-label="`Delete ${planting.crop_name}`"
                            >
                                <Trash2 />
                            </Button>
                        </template>
                    </ConfirmDeleteButton>
                </div>
            </li>
        </ul>
        <p v-else class="m-0 text-body text-muted-foreground">No crops yet. Add what grows on this farm.</p>
    </SectionCard>
</template>
