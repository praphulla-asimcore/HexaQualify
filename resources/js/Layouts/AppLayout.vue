<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import HexaLockup from '../Components/HexaLockup.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';
import Toasts from '../Components/Toasts.vue';
import Icon from '../Components/Icon.vue';
import { toast } from '../lib/toast';

defineProps({ title: String });
const page = usePage();
const user = computed(() => page.props.auth?.user);
const menuOpen = ref(false);
const navOpen = ref(false);

const nav = computed(() => {
    const role = user.value?.role;
    return [
        { label: 'Dashboard', href: '/', icon: 'dashboard', show: true, match: (u) => u === '/' || u.startsWith('/responses') || u.startsWith('/?') },
        { label: 'Invitations', href: '/invitations', icon: 'mail', show: role === 'admin' || role === 'reviewer' },
        { label: 'Questionnaires', href: '/questionnaires', icon: 'list', show: true },
        { label: 'Settings', href: '/settings', icon: 'settings', show: role === 'admin' },
    ].filter((n) => n.show);
});
const isActive = (n) => (n.match ? n.match(page.url) : page.url.startsWith(n.href));

watch(() => page.props.flash, (f) => {
    if (f?.success) toast(f.success, 'success');
    if (f?.error) toast(f.error, 'error');
}, { immediate: true, deep: true });

router.on('navigate', () => { menuOpen.value = false; navOpen.value = false; });
const initials = computed(() => (user.value?.name || '?').split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase());
</script>

<template>
    <Head :title="title" />
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[80] btn btn-secondary">Skip to content</a>
    <nav class="glass sticky top-3 z-40 mx-3 mt-3 sm:mx-6" aria-label="Main">
        <div class="flex items-center gap-2 px-3 py-2 sm:px-4">
            <Link href="/" class="mr-2 shrink-0 rounded-lg" aria-label="HexaQualify home"><HexaLockup :height="24" collapse /></Link>
            <div class="hidden flex-1 items-center gap-1 md:flex">
                <Link v-for="n in nav" :key="n.href" :href="n.href" class="btn btn-ghost !min-h-[40px]"
                      :class="isActive(n) ? '!bg-[var(--brand-tint-strong)]' : ''" :aria-current="isActive(n) ? 'page' : undefined">
                    <Icon :name="n.icon" :size="16" />{{ n.label }}
                </Link>
            </div>
            <div class="ml-auto flex items-center gap-1">
                <ThemeToggle />
                <button type="button" class="btn btn-ghost !min-h-[40px] !px-2.5 md:!hidden" @click="navOpen = !navOpen" :aria-expanded="navOpen" aria-label="Menu">
                    <Icon name="menu" />
                </button>
                <div class="relative">
                    <button type="button" class="btn btn-ghost !min-h-[40px] !px-1.5" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen" aria-haspopup="menu" :aria-label="`Account menu for ${user?.name}`">
                        <img v-if="user?.avatar_url" :src="user.avatar_url" alt="" class="h-8 w-8 rounded-full" referrerpolicy="no-referrer" />
                        <span v-else class="grid h-8 w-8 place-items-center rounded-full bg-[var(--brand-tint-strong)] text-xs font-semibold">{{ initials }}</span>
                    </button>
                    <div v-if="menuOpen" role="menu" class="panel absolute right-0 top-12 w-64 p-2 shadow-xl">
                        <div class="px-3 py-2">
                            <div class="font-semibold">{{ user?.name }}</div>
                            <div class="truncate text-xs text-ink-muted">{{ user?.email }}</div>
                            <div class="mt-1 text-xs capitalize text-ink-muted">Role: {{ user?.role }}</div>
                        </div>
                        <Link href="/styleguide" role="menuitem" class="btn btn-ghost w-full !justify-start"><Icon name="palette" :size="16" />Style guide</Link>
                        <Link href="/logout" method="post" as="button" role="menuitem" class="btn btn-ghost w-full !justify-start"><Icon name="logout" :size="16" />Sign out</Link>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="navOpen" class="flex flex-col gap-1 border-t border-[var(--line)] p-2 md:hidden">
            <Link v-for="n in nav" :key="n.href" :href="n.href" class="btn btn-ghost !justify-start" :class="isActive(n) ? '!bg-[var(--brand-tint-strong)]' : ''">
                <Icon :name="n.icon" :size="16" />{{ n.label }}
            </Link>
        </div>
    </nav>

    <main id="main" class="mx-auto w-full max-w-7xl px-4 pb-16 pt-6 sm:px-6">
        <slot />
    </main>
    <Toasts />
</template>
