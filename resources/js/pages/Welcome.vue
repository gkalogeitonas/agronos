<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { Activity, Map, Radio } from 'lucide-vue-next';

const features = [
    {
        icon: Radio,
        title: 'Wi-Fi and LoRa sensors',
        text: 'Register devices by scanning a QR code. Readings arrive over MQTT or HTTP, and LoRa payloads are AES-128 encrypted.',
    },
    {
        icon: Activity,
        title: 'Live and historical readings',
        text: 'Soil moisture, temperature, humidity and light, updated in real time and kept as time series.',
    },
    {
        icon: Map,
        title: 'Every farm on one map',
        text: 'Draw farm boundaries, place sensors where they sit and see which farm needs attention first.',
    },
];
</script>

<template>
    <Head title="Welcome" />
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-6">
            <Link :href="route('home')" class="flex items-center gap-3 text-foreground">
                <span class="flex size-10 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <AppLogoIcon class="size-5" />
                </span>
                <span class="text-2xl font-bold tracking-[0.01em]">Agronos</span>
            </Link>
            <nav aria-label="Account" class="flex items-center gap-3">
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="route('dashboard')">Open dashboard</Link>
                </Button>
                <template v-else>
                    <Button variant="outline" as-child>
                        <Link :href="route('login')">Log in</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="route('register')">Create account</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-12 px-6 py-12 md:py-20">
            <section class="flex max-w-3xl flex-col gap-5">
                <div class="font-mono text-meta tracking-[0.1em] text-muted-foreground uppercase">Smart agriculture IoT platform</div>
                <h1 class="m-0 text-4xl leading-tight font-bold md:text-[56px]">Know your fields before you walk them.</h1>
                <p class="m-0 text-lg leading-relaxed text-ink-soft">
                    Agronos collects readings from the sensors on your farms and garden beds, shows them as they happen and keeps their history, so a
                    change is noticed before it becomes a problem.
                </p>
                <div class="flex flex-wrap gap-3 pt-2">
                    <Button v-if="$page.props.auth.user" size="lg" as-child>
                        <Link :href="route('dashboard')">Open dashboard</Link>
                    </Button>
                    <Button v-else size="lg" as-child>
                        <Link :href="route('register')">Get started</Link>
                    </Button>
                </div>
            </section>

            <section aria-label="Features" class="grid gap-4 md:grid-cols-3">
                <article v-for="feature in features" :key="feature.title" class="flex flex-col gap-3 rounded-xl border bg-card p-6">
                    <span class="flex size-10 items-center justify-center rounded-md bg-success-soft text-success-foreground">
                        <component :is="feature.icon" class="size-5" aria-hidden="true" />
                    </span>
                    <h2 class="m-0 text-lg font-semibold">{{ feature.title }}</h2>
                    <p class="m-0 text-body leading-relaxed text-muted-foreground">{{ feature.text }}</p>
                </article>
            </section>
        </main>

        <footer class="mx-auto w-full max-w-6xl px-6 py-8 font-mono text-xs text-muted-foreground">
            <a
                href="https://github.com/gkalogeitonas/agronos"
                target="_blank"
                rel="noopener noreferrer"
                class="text-muted-foreground hover:text-foreground"
                >Agronos on GitHub</a
            >
        </footer>
    </div>
</template>
