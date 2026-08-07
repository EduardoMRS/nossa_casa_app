<script setup lang="ts">
defineProps<{
    kicker: string;
    title: string;
    description: string;
}>();
</script>

<template>
    <header
        class="flex flex-col justify-between gap-5 rounded-2xl bg-gradient-to-br from-indigo-950 to-indigo-800 p-7 text-white shadow-sm md:flex-row md:items-end"
    >
        <div>
            <p
                class="font-mono text-[10px] font-bold tracking-[0.2em] text-amber-300 uppercase"
            >
                {{ kicker }}
            </p>
            <h1 class="mt-2 text-3xl font-black tracking-tight">{{ title }}</h1>
            <p class="mt-2 max-w-3xl text-sm text-indigo-100">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap gap-2">
            <slot />
        </div>
    </header>
</template>
