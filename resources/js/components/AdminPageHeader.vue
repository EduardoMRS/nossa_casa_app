<script setup lang="ts">
defineProps<{
    kicker: string;
    title: string;
    description: string;
}>();
</script>

<template>
    <header
        class="flex flex-col justify-between gap-5 rounded-2xl border border-primary/25 bg-primary p-7 text-primary-foreground shadow-sm md:flex-row md:items-end"
    >
        <div>
            <p
                class="font-mono text-[10px] font-bold tracking-[0.2em] text-primary-foreground/75 uppercase"
            >
                {{ kicker }}
            </p>
            <h1 class="mt-2 text-3xl font-black tracking-tight">{{ title }}</h1>
            <p class="mt-2 max-w-3xl text-sm text-primary-foreground/85">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap gap-2">
            <slot />
        </div>
    </header>
</template>
