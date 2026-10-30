<script setup>
import Icon from './Icon.vue';
import { theme, toggleTheme } from '../lib/theme';
</script>

<template>
    <button type="button" class="btn btn-ghost !min-h-[40px] !px-2.5" @click="toggleTheme"
            :aria-label="theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'" :title="theme === 'dark' ? 'Light theme' : 'Dark theme'">
        <Icon :name="theme === 'dark' ? 'sun' : 'moon'" />
    </button>
</template>
