<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import ThemeToggle from '../Components/ThemeToggle.vue';
import Toasts from '../Components/Toasts.vue';

defineProps({ title: String });
const company = usePage().props.company;
</script>

<template>
    <Head :title="title" />
    <div class="flex min-h-screen flex-col items-center px-3 py-6 sm:px-6 sm:py-10">
        <div class="w-full max-w-portal">
            <!-- Glass letterhead (§10.4) -->
            <header class="glass rise relative px-5 pb-4 pt-5 sm:px-8">
                <div class="absolute right-3 top-3"><ThemeToggle /></div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-5">
                    <span class="logo-plate inline-flex w-fit items-center px-1.5 py-1">
                        <img src="/brand/hexa-logo.png" alt="Hexa" width="120" height="28" class="block h-auto w-[120px]" />
                    </span>
                    <div class="sm:border-l sm:border-[var(--line)] sm:pl-5">
                        <div class="font-display text-[15px] font-semibold text-ink">{{ company.legal_name }}</div>
                        <div class="text-xs text-ink-muted">{{ company.address }}</div>
                    </div>
                </div>
                <div class="brand-line mt-4"></div>
            </header>

            <main id="main" class="mt-4">
                <slot />
            </main>

            <footer class="glass glass-strong mt-6 px-5 py-4 text-center text-xs leading-relaxed text-ink-muted">
                {{ company.legal_name }}, {{ company.address }}, {{ company.website }}
                <div class="mt-1">HexaQualify · Pre-pitch vendor screening</div>
            </footer>
        </div>
    </div>
    <Toasts />
</template>
