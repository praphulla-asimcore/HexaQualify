<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Icon from '../../Components/Icon.vue';
import { formatDate, formatPoints } from '../../lib/format';

defineProps({ items: Array, can: Object });
</script>

<template>
    <AppLayout title="Questionnaires">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold sm:text-[28px]">Questionnaires</h1>
                <p class="text-ink-muted">One questionnaire and hidden scoring guide per service category.</p>
            </div>
            <Link v-if="can.edit" href="/questionnaires/create" class="btn btn-primary"><Icon name="plus" :size="16" />New questionnaire</Link>
        </div>

        <EmptyState v-if="!items.length" class="mt-6" title="No questionnaires" text="Create the first category questionnaire to start inviting vendors." />

        <div v-else class="mt-6 grid gap-4 md:grid-cols-2">
            <article v-for="q in items" :key="q.id" class="glass lift rise flex flex-col p-5" :class="{ 'opacity-70': q.archived }">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold">{{ q.name }}</h2>
                        <p class="text-sm text-ink-muted">{{ q.category }}</p>
                    </div>
                    <span class="badge" :class="q.archived ? 'badge-fail' : q.active ? 'badge-pass' : 'badge-neutral'">{{ q.archived ? 'Archived' : q.active ? 'Active' : 'Inactive' }}</span>
                </div>
                <p v-if="q.description" class="mt-2 line-clamp-2 text-sm text-ink-muted">{{ q.description }}</p>
                <dl class="panel mt-4 grid grid-cols-4 gap-2 p-3 text-center">
                    <div><dt class="text-[11px] uppercase tracking-wide text-ink-muted">Questions</dt><dd class="font-display text-lg font-semibold">{{ q.questions }}</dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-ink-muted">Pass mark</dt><dd class="font-display text-lg font-semibold">{{ q.pass_mark != null ? q.pass_mark + '%' : '—' }}</dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-ink-muted">Knockouts</dt><dd class="font-display text-lg font-semibold">{{ q.knockouts ?? '—' }}</dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-ink-muted">Version</dt><dd class="font-display text-lg font-semibold">v{{ q.version }}</dd></div>
                </dl>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="mr-auto text-xs text-ink-muted">{{ q.max_points != null ? formatPoints(q.max_points) + ' points · ' : '' }}updated {{ formatDate(q.updated_at) }}</span>
                    <a :href="`/questionnaires/${q.id}/preview`" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" v-if="can.guide"><Icon name="eye" :size="14" />Preview</a>
                    <template v-if="can.edit">
                        <button v-if="q.archived" class="btn btn-secondary btn-sm" @click="router.post(`/questionnaires/${q.id}/restore`)">Restore</button>
                        <Link v-else :href="`/questionnaires/${q.id}/edit`" class="btn btn-secondary btn-sm">Edit</Link>
                    </template>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
