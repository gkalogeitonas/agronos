<script setup lang="ts">
import ConfirmDeleteButton from '@/components/agronos/ConfirmDeleteButton.vue';
import SectionCard from '@/components/agronos/SectionCard.vue';
import ZoneDialog from '@/components/farms/ZoneDialog.vue';
import { Button } from '@/components/ui/button';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';

export interface FarmZone {
    id: number;
    name: string;
    sensors_count: number;
}

/**
 * The Zones section of Farm detail: lists the farm's zones with their sensor counts, and adds, renames and deletes them.
 */
defineProps<{
    farmId: number;
    zones: FarmZone[];
    /** The name a zone gets when it is given none. */
    nextZoneName: string;
}>();

function sensorCount(count: number): string {
    return count === 1 ? '1 sensor' : `${count} sensors`;
}

function deleteDescription(zone: FarmZone): string {
    const holds = zone.sensors_count === 1 ? 'Its 1 sensor' : `Its ${zone.sensors_count} sensors`;

    return `${holds} will be left with no zone. No sensors are deleted.`;
}
</script>

<template>
    <SectionCard title="Zones">
        <template #actions>
            <ZoneDialog :farm-id="farmId" :next-zone-name="nextZoneName">
                <Button variant="outline" size="sm"><Plus /> Add zone</Button>
            </ZoneDialog>
        </template>
        <ul v-if="zones.length > 0" class="m-0 flex list-none flex-col divide-y p-0">
            <li v-for="zone in zones" :key="zone.id" class="flex flex-wrap items-center justify-between gap-3 py-2">
                <div class="flex min-w-0 flex-col">
                    <span class="truncate font-medium">{{ zone.name }}</span>
                    <span class="font-mono text-meta text-muted-foreground">{{ sensorCount(zone.sensors_count) }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <ZoneDialog :farm-id="farmId" :zone="zone" :next-zone-name="nextZoneName">
                        <Button variant="ghost" size="icon" :aria-label="`Rename ${zone.name}`"><Pencil /></Button>
                    </ZoneDialog>
                    <ConfirmDeleteButton
                        :url="route('farms.zones.destroy', [farmId, zone.id])"
                        :item-name="zone.name"
                        :description="deleteDescription(zone)"
                        preserve-scroll
                    >
                        <template #trigger>
                            <Button variant="ghost" size="icon" class="text-destructive hover:text-destructive" :aria-label="`Delete ${zone.name}`">
                                <Trash2 />
                            </Button>
                        </template>
                    </ConfirmDeleteButton>
                </div>
            </li>
        </ul>
        <p v-else class="m-0 text-body text-muted-foreground">No zones yet. Divide the farm into zones to group its sensors.</p>
    </SectionCard>
</template>
