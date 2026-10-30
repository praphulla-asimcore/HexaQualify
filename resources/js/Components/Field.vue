<script setup>
defineProps({ label: String, error: String, hint: String, id: String, required: Boolean });
</script>

<template>
    <div>
        <label v-if="label" :for="id" class="label">{{ label }}<span v-if="required" class="text-notq" aria-hidden="true"> *</span></label>
        <slot />
        <p v-if="hint && !error" class="hint">{{ hint }}</p>
        <p v-if="error" class="error" :id="id ? `${id}-error` : undefined">{{ error }}</p>
    </div>
</template>
