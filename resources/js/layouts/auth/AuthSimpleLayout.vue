<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="flex w-full max-w-md flex-col gap-6">
            <Link :href="route('home')" class="flex items-center justify-center gap-3 text-foreground">
                <span class="flex size-10 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <AppLogoIcon class="size-5" />
                </span>
                <span class="text-2xl font-bold tracking-[0.01em]">Agronos</span>
            </Link>
            <div class="flex flex-col gap-8 rounded-xl border bg-card p-8">
                <div class="flex flex-col gap-2 text-center">
                    <h1 class="m-0 text-2xl font-bold">{{ title }}</h1>
                    <p v-if="description" class="m-0 text-[15px] text-muted-foreground">{{ description }}</p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
