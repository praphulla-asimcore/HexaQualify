<script setup>
// Header lockup (§10.1): [Hexa logo] | HexaQualify. The official logo file is used unaltered.
defineProps({ height: { type: Number, default: 26 }, product: { type: Boolean, default: true }, collapse: Boolean });
</script>

<template>
    <span class="inline-flex items-center">
        <span class="logo-plate inline-flex items-center px-1.5 py-1">
            <img src="/brand/hexa-logo.png" alt="Hexa" :height="height" :width="Math.round(height * 477 / 110)" class="block h-auto"
                 :class="height >= 26 ? 'w-[113px]' : 'w-[96px]'" />
        </span>
        <span v-if="product" class="inline-flex items-center" :class="{ 'max-[400px]:hidden': collapse }">
            <span class="mx-3 h-6 w-px bg-[var(--line)]" aria-hidden="true"></span>
            <span class="font-display font-semibold leading-none text-ink" :class="height >= 26 ? 'text-[16px]' : 'text-[14px]'">HexaQualify</span>
        </span>
    </span>
</template>
