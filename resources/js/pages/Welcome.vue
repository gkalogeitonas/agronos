<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { Activity, Cpu, Map, Radio, Thermometer, TriangleAlert } from 'lucide-vue-next';

const platformRepositoryUrl = 'https://github.com/gkalogeitonas/agronos';
const firmwareRepositoryUrl = 'https://github.com/gkalogeitonas/Agronos-iot-device';

const sampleMetrics = [
    { label: 'Moisture', value: '41 %' },
    { label: 'Air temp', value: '23.8 °C' },
    { label: 'Devices', value: '12 / 14' },
];

const features = [
    {
        icon: Radio,
        title: 'Wi-Fi and LoRa sensors',
        text: 'Readings arrive over MQTT or HTTP from Wi-Fi sensors, and LoRa payloads are AES-128 encrypted.',
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

const scanTargets = [
    {
        icon: Cpu,
        title: 'Devices',
        text: 'Scan the QR code on a device to add it to a farm. It receives its own credentials automatically.',
    },
    {
        icon: Thermometer,
        title: 'Sensors',
        text: 'Scan the QR code on each sensor to register it and place it on your farm. One device can carry several sensors.',
    },
];

const steps = [
    {
        number: '01',
        title: 'Register a device',
        text: 'Scan the QR code on the device and on each sensor. They receive their own credentials and appear under the right farm.',
    },
    {
        number: '02',
        title: 'Readings arrive',
        text: 'Sensors send data over Wi-Fi with MQTT or HTTP, or over LoRa through a gateway. Everything is stored as a time series.',
    },
    {
        number: '03',
        title: 'See it and act on it',
        text: 'Follow live values on the map and dashboard, and get alerted when a reading crosses a threshold.',
    },
];

const roadmap = [
    { title: 'Actuators', text: 'Control irrigation valves from the dashboard, manually or on a schedule.' },
    { title: 'Agentic irrigation', text: 'An agent that reads sensor data and decides when to water, within limits you set.' },
    { title: 'Ask your farm', text: 'Query readings in plain language and get answers from your own sensor data.' },
];

const stack = ['Laravel', 'Vue', 'ESP32', 'LoRa', 'MQTT', 'InfluxDB', 'MySQL', 'Docker'];

/** Boxes of the architecture diagram, positioned on a 960×376 grid. */
const architectureNodes = [
    { x: 0, y: 40, width: 160, title: 'ESP32', subtitle: 'Wi-Fi', tone: 'ink' },
    { x: 0, y: 288, width: 160, title: 'ESP32', subtitle: 'LoRa', tone: 'ink' },
    { x: 240, y: 288, width: 140, title: 'LoRa gateway', subtitle: null, tone: 'soft' },
    { x: 424, y: 164, width: 160, title: 'MQTT broker', subtitle: 'EMQX', tone: 'soft' },
    { x: 664, y: 40, width: 176, title: 'InfluxDB', subtitle: 'time series', tone: 'soft' },
    { x: 664, y: 164, width: 176, title: 'Laravel', subtitle: 'API + WebSockets', tone: 'primary' },
    { x: 664, y: 288, width: 176, title: 'Dashboard', subtitle: "user's browser", tone: 'ink' },
] as const;

const architectureNodeClasses = {
    ink: { box: 'fill-foreground', text: 'fill-white' },
    soft: { box: 'fill-success-soft', text: 'fill-success-foreground' },
    primary: { box: 'fill-primary', text: 'fill-primary-foreground' },
};

/** Connectors of the architecture diagram; `arrow` adds an arrowhead at the path's end. */
const architectureEdges = [
    { d: 'M 160 76 L 504 76 L 504 160', arrow: true, dashed: false },
    { d: 'M 160 324 L 236 324', arrow: true, dashed: false },
    { d: 'M 380 324 L 504 324 L 504 240', arrow: true, dashed: false },
    { d: 'M 584 200 L 660 200', arrow: true, dashed: false },
    { d: 'M 722 112 L 722 160', arrow: true, dashed: false },
    { d: 'M 781 164 L 781 116', arrow: true, dashed: false },
    { d: 'M 722 236 L 722 284', arrow: true, dashed: true },
    { d: 'M 781 236 L 781 284', arrow: true, dashed: false },
];

const architectureLabels = [
    { x: 380, y: 60, text: 'MQTT (HTTP fallback)', anchor: 'middle' },
    { x: 200, y: 308, text: 'LoRa', anchor: 'middle' },
    { x: 442, y: 308, text: 'forwards', anchor: 'middle' },
    { x: 624, y: 184, text: 'readings', anchor: 'middle' },
    { x: 710, y: 142, text: 'statistics', anchor: 'end' },
    { x: 793, y: 142, text: 'store readings', anchor: 'start' },
    { x: 710, y: 266, text: 'live data · WebSockets', anchor: 'end' },
    { x: 793, y: 266, text: 'statistics', anchor: 'start' },
] as const;

/** Light modules of the QR code illustration, on a 170×170 grid. */
const qrModules = [
    [84, 34],
    [84, 56],
    [34, 84],
    [56, 84],
    [84, 84],
    [106, 84],
    [126, 84],
    [84, 106],
    [106, 106],
    [126, 126],
    [84, 126],
];
</script>

<template>
    <Head title="Welcome" />
    <div class="min-h-screen bg-background text-foreground">
        <div class="mx-auto flex w-full max-w-[1104px] flex-col gap-16 px-6 md:gap-24">
            <header class="flex flex-wrap items-center justify-between gap-3 pt-6">
                <Link :href="route('home')" class="flex items-center gap-3 text-foreground">
                    <span class="flex size-10 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <AppLogoIcon class="size-[22px]" />
                    </span>
                    <span class="text-[22px] font-bold">Agronos</span>
                </Link>
                <nav aria-label="Main" class="flex flex-wrap items-center gap-2">
                    <a href="#how" class="inline-flex min-h-11 items-center px-3 text-body font-medium text-foreground hover:text-primary">
                        How it works
                    </a>
                    <a
                        :href="platformRepositoryUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center px-3 text-body font-medium text-foreground hover:text-primary"
                    >
                        GitHub
                    </a>
                    <Button v-if="$page.props.auth.user" as-child class="min-h-11">
                        <Link :href="route('dashboard')">Open dashboard</Link>
                    </Button>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="inline-flex min-h-11 items-center px-3 text-body font-medium text-foreground hover:text-primary"
                        >
                            Log in
                        </Link>
                        <Button as-child class="min-h-11">
                            <Link :href="route('register')">Create account</Link>
                        </Button>
                    </template>
                </nav>
            </header>

            <main class="flex flex-col gap-16 md:gap-24">
                <section aria-label="Introduction" class="flex flex-wrap items-center gap-12">
                    <div class="flex min-w-0 flex-[1_1_440px] flex-col gap-6">
                        <div class="font-mono text-meta tracking-[0.1em] text-muted-foreground uppercase">Smart agriculture IoT platform</div>
                        <h1 class="m-0 text-4xl leading-[1.1] font-bold tracking-[-0.01em] md:text-[56px]">Know your fields before you walk them.</h1>
                        <p class="m-0 max-w-[520px] text-lg leading-relaxed text-ink-soft">
                            Agronos collects readings from the sensors on your farms and garden beds, shows them as they happen and keeps their
                            history, so a change is noticed before it becomes a problem.
                        </p>
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <Button v-if="$page.props.auth.user" size="lg" as-child class="min-h-12 px-6 text-base">
                                <Link :href="route('dashboard')">Open dashboard</Link>
                            </Button>
                            <Button v-else size="lg" as-child class="min-h-12 px-6 text-base">
                                <Link :href="route('register')">Get started</Link>
                            </Button>
                            <Button variant="outline" size="lg" as-child class="min-h-12 px-6 text-base">
                                <a :href="platformRepositoryUrl" target="_blank" rel="noopener noreferrer">View on GitHub</a>
                            </Button>
                            <a href="#how" class="inline-flex min-h-12 items-center px-2 text-base font-medium text-info hover:text-foreground">
                                See how it works
                            </a>
                        </div>
                    </div>

                    <figure class="m-0 flex min-w-0 flex-[1_1_420px] flex-col gap-2.5">
                        <div class="flex flex-col gap-4 rounded-xl border bg-card p-5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-col gap-0.5">
                                    <span class="eyebrow text-micro">Farm detail</span>
                                    <span class="text-lg font-bold">North Orchard</span>
                                </div>
                                <span class="rounded-full bg-warning-soft px-3 py-1 text-meta font-semibold text-warning-foreground">Warning</span>
                            </div>
                            <div class="flex gap-2.5">
                                <div
                                    v-for="metric in sampleMetrics"
                                    :key="metric.label"
                                    class="flex flex-1 flex-col gap-0.5 rounded-md bg-background p-3"
                                >
                                    <span class="eyebrow text-[10px]">{{ metric.label }}</span>
                                    <span class="font-mono text-metric font-medium">{{ metric.value }}</span>
                                </div>
                            </div>
                            <svg
                                viewBox="0 0 400 130"
                                width="100%"
                                height="130"
                                preserveAspectRatio="none"
                                role="img"
                                aria-label="Sample chart: soil moisture for two zones drifting down toward the irrigation threshold over 24 hours"
                            >
                                <line x1="0" y1="14" x2="400" y2="14" class="stroke-border" stroke-width="1" />
                                <line x1="0" y1="64" x2="400" y2="64" class="stroke-border" stroke-width="1" />
                                <line x1="0" y1="114" x2="400" y2="114" class="stroke-border" stroke-width="1" />
                                <line x1="0" y1="96" x2="400" y2="96" class="stroke-threshold" stroke-width="2" stroke-dasharray="3 5" />
                                <polyline
                                    points="0,32 40,36 80,34 120,42 160,40 200,48 240,52 280,56 320,62 360,66 400,72"
                                    fill="none"
                                    class="stroke-chart-3"
                                    stroke-width="3"
                                    stroke-linejoin="round"
                                    stroke-linecap="round"
                                    vector-effect="non-scaling-stroke"
                                />
                                <polyline
                                    points="0,58 40,62 80,60 120,70 160,74 200,72 240,82 280,84 320,88 360,92 400,90"
                                    fill="none"
                                    class="stroke-chart-1"
                                    stroke-width="3"
                                    stroke-dasharray="9 6"
                                    stroke-linejoin="round"
                                    vector-effect="non-scaling-stroke"
                                />
                            </svg>
                            <div class="flex items-center gap-3 rounded-md bg-warning-surface p-3">
                                <span class="flex size-[26px] shrink-0 items-center justify-center rounded-sm bg-warning text-white">
                                    <TriangleAlert class="size-4" aria-hidden="true" />
                                </span>
                                <div class="flex flex-col gap-px">
                                    <span class="text-sm font-semibold">Zone B below threshold</span>
                                    <span class="text-xs text-ink-soft">Soil moisture, 12 min ago</span>
                                </div>
                            </div>
                        </div>
                        <figcaption class="font-mono text-xs text-muted-foreground">Sample data, for illustration</figcaption>
                    </figure>
                </section>

                <section aria-label="Features" class="flex flex-wrap gap-4">
                    <article
                        v-for="feature in features"
                        :key="feature.title"
                        class="flex flex-[1_1_280px] flex-col gap-3.5 rounded-xl border bg-card p-6"
                    >
                        <span class="flex size-10 items-center justify-center rounded-md bg-success-soft text-success-foreground">
                            <component :is="feature.icon" class="size-5" aria-hidden="true" />
                        </span>
                        <h2 class="m-0 text-lg font-semibold">{{ feature.title }}</h2>
                        <p class="m-0 text-body leading-relaxed text-ink-soft">{{ feature.text }}</p>
                    </article>
                </section>

                <section aria-label="Scan to register" class="flex flex-wrap items-center gap-12 rounded-xl border bg-card p-6 md:p-10">
                    <div class="flex min-w-0 flex-[1_1_400px] flex-col gap-5">
                        <div class="font-mono text-meta tracking-[0.1em] text-muted-foreground uppercase">Scan to register</div>
                        <h2 class="m-0 text-[28px] leading-[1.15] font-bold tracking-[-0.01em] md:text-4xl">
                            Add devices and sensors with your phone camera
                        </h2>
                        <p class="m-0 max-w-[520px] text-base leading-relaxed text-ink-soft">
                            Every device and every sensor carries its own QR code. Point your phone at it and Agronos registers it, with no typing of
                            IDs or keys.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <div
                                v-for="target in scanTargets"
                                :key="target.title"
                                class="flex flex-[1_1_220px] flex-col gap-2 rounded-lg bg-background p-[18px]"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span class="flex size-8 items-center justify-center rounded-sm bg-success-soft text-success-foreground">
                                        <component :is="target.icon" class="size-[18px]" aria-hidden="true" />
                                    </span>
                                    <h3 class="m-0 text-[17px] font-semibold">{{ target.title }}</h3>
                                </div>
                                <p class="m-0 text-sm leading-relaxed text-ink-soft">{{ target.text }}</p>
                            </div>
                        </div>
                    </div>
                    <figure class="m-0 flex min-w-0 flex-[1_1_320px] flex-col items-center gap-2.5">
                        <div class="flex w-[260px] max-w-full flex-col rounded-[28px] bg-sidebar p-[18px]">
                            <div class="flex h-60 items-center justify-center rounded-2xl bg-sidebar-accent">
                                <svg
                                    width="170"
                                    height="170"
                                    viewBox="0 0 170 170"
                                    fill="none"
                                    role="img"
                                    aria-label="Illustration of a camera frame scanning a QR code"
                                >
                                    <path
                                        d="M8 40V14a6 6 0 0 1 6-6h26M130 8h26a6 6 0 0 1 6 6v26M162 130v26a6 6 0 0 1-6 6h-26M40 162H14a6 6 0 0 1-6-6v-26"
                                        class="stroke-sun"
                                        stroke-width="5"
                                        stroke-linecap="round"
                                    />
                                    <template
                                        v-for="[x, y] in [
                                            [34, 34],
                                            [102, 34],
                                            [34, 102],
                                        ]"
                                        :key="`finder-${x}-${y}`"
                                    >
                                        <rect :x="x" :y="y" width="34" height="34" rx="4" class="fill-secondary" />
                                        <rect :x="x + 10" :y="y + 10" width="14" height="14" class="fill-sidebar-accent" />
                                    </template>
                                    <rect
                                        v-for="[x, y] in qrModules"
                                        :key="`module-${x}-${y}`"
                                        :x="x"
                                        :y="y"
                                        width="10"
                                        height="10"
                                        class="fill-secondary"
                                    />
                                </svg>
                            </div>
                        </div>
                        <figcaption class="font-mono text-xs text-muted-foreground">Illustration, not a real code</figcaption>
                    </figure>
                </section>

                <section id="how" aria-labelledby="how-heading" class="flex scroll-mt-6 flex-col gap-8">
                    <div class="flex flex-col gap-2">
                        <div class="font-mono text-meta tracking-[0.1em] text-muted-foreground uppercase">How it works</div>
                        <h2 id="how-heading" class="m-0 text-[28px] font-bold tracking-[-0.01em] md:text-4xl">
                            From sensor to dashboard in three steps
                        </h2>
                    </div>
                    <ol class="m-0 flex list-none flex-wrap gap-4 p-0">
                        <li v-for="step in steps" :key="step.number" class="flex flex-[1_1_280px] flex-col gap-2.5 border-t-2 border-foreground pt-4">
                            <span class="font-mono text-sm text-muted-foreground">{{ step.number }}</span>
                            <h3 class="m-0 text-xl font-semibold">{{ step.title }}</h3>
                            <p class="m-0 text-body leading-relaxed text-ink-soft">{{ step.text }}</p>
                        </li>
                    </ol>
                    <figure class="m-0 flex flex-col gap-3.5 rounded-xl border bg-card p-6">
                        <div class="eyebrow text-xs">Architecture</div>
                        <div class="overflow-x-auto">
                            <svg
                                width="960"
                                height="376"
                                viewBox="0 0 960 376"
                                role="img"
                                aria-label="Architecture diagram: ESP32 Wi-Fi devices send readings to the MQTT broker; ESP32 LoRa devices send through a LoRa gateway to the broker; the broker passes readings to Laravel, which stores them in InfluxDB, reads statistics back and serves the dashboard"
                                class="block"
                            >
                                <defs>
                                    <marker
                                        id="architecture-arrow"
                                        orient="auto"
                                        markerWidth="5"
                                        markerHeight="5"
                                        refX="2"
                                        refY="2"
                                        overflow="visible"
                                    >
                                        <path d="M0 0 L4 2 L0 4 Z" class="fill-muted-foreground" />
                                    </marker>
                                </defs>
                                <path
                                    v-for="edge in architectureEdges"
                                    :key="edge.d"
                                    :d="edge.d"
                                    fill="none"
                                    class="stroke-muted-foreground"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    :stroke-dasharray="edge.dashed ? '6 6' : undefined"
                                    :marker-end="edge.arrow ? 'url(#architecture-arrow)' : undefined"
                                />
                                <g v-for="node in architectureNodes" :key="`${node.title}-${node.y}`">
                                    <rect
                                        :x="node.x"
                                        :y="node.y"
                                        :width="node.width"
                                        height="72"
                                        rx="10"
                                        :class="architectureNodeClasses[node.tone].box"
                                    />
                                    <text
                                        :x="node.x + node.width / 2"
                                        :y="node.subtitle ? node.y + 32 : node.y + 41"
                                        text-anchor="middle"
                                        font-size="14"
                                        :class="architectureNodeClasses[node.tone].text"
                                    >
                                        <tspan font-weight="700">{{ node.title }}</tspan>
                                        <tspan v-if="node.subtitle" :x="node.x + node.width / 2" dy="20">{{ node.subtitle }}</tspan>
                                    </text>
                                </g>
                                <text
                                    v-for="label in architectureLabels"
                                    :key="`${label.text}-${label.x}`"
                                    :x="label.x"
                                    :y="label.y"
                                    :text-anchor="label.anchor"
                                    font-size="13"
                                    class="fill-ink-soft"
                                >
                                    {{ label.text }}
                                </text>
                            </svg>
                        </div>
                        <figcaption class="text-meta leading-normal text-muted-foreground">
                            Readings flow from the devices into Laravel, which stores them in InfluxDB and pushes live values to the dashboard over
                            WebSockets (dashed). Statistics are read from InfluxDB through Laravel. LoRa payloads are AES-128-CTR encrypted at the
                            application layer.
                        </figcaption>
                    </figure>
                </section>

                <section aria-labelledby="roadmap-heading" class="flex flex-col gap-6">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 id="roadmap-heading" class="m-0 text-[28px] font-bold">Coming next</h2>
                        <span class="rounded-full bg-sun px-2.5 py-1 font-mono text-micro text-foreground">ROADMAP</span>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <article
                            v-for="item in roadmap"
                            :key="item.title"
                            class="flex flex-[1_1_280px] flex-col gap-2.5 rounded-xl bg-sidebar p-6 text-white"
                        >
                            <h3 class="m-0 text-lg font-semibold">{{ item.title }}</h3>
                            <p class="m-0 text-body leading-relaxed text-sidebar-foreground">{{ item.text }}</p>
                        </article>
                    </div>
                </section>
            </main>

            <footer class="flex flex-wrap justify-between gap-8 border-t border-input pt-8 pb-10">
                <div class="flex flex-[1_1_260px] flex-col gap-2.5">
                    <div class="flex items-center gap-2.5">
                        <span class="flex size-7 items-center justify-center rounded-sm bg-primary text-primary-foreground">
                            <AppLogoIcon class="size-4" />
                        </span>
                        <span class="text-[17px] font-bold">Agronos</span>
                    </div>
                    <p class="m-0 max-w-[340px] text-sm leading-relaxed text-ink-soft">Built as a Master's thesis at Hellenic Open University.</p>
                </div>
                <nav aria-labelledby="footer-source-heading" class="flex flex-[1_1_200px] flex-col gap-1">
                    <div id="footer-source-heading" class="pb-1.5 eyebrow text-xs">Source</div>
                    <a
                        :href="platformRepositoryUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center text-body font-medium text-info hover:text-foreground"
                    >
                        Platform repository
                    </a>
                    <a
                        :href="firmwareRepositoryUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center text-body font-medium text-info hover:text-foreground"
                    >
                        Device firmware repository
                    </a>
                </nav>
                <div class="flex flex-[1_1_260px] flex-col gap-2.5">
                    <div class="eyebrow text-xs">Stack</div>
                    <ul class="m-0 flex list-none flex-wrap gap-2 p-0">
                        <li
                            v-for="technology in stack"
                            :key="technology"
                            class="rounded-full border border-input bg-card px-2.5 py-1.5 font-mono text-xs"
                        >
                            {{ technology }}
                        </li>
                    </ul>
                </div>
            </footer>
        </div>
    </div>
</template>
