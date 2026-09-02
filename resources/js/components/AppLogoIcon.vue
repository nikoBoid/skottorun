<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="none"
            stroke="currentColor"
            stroke-width="3"
            stroke-linejoin="round"
            d="M24 3 42 15l-7 24H13L6 15 24 3Z"
        />
        <path
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linejoin="round"
            d="m6 15 18 8 18-8M24 3v20m0 0 11 16m-11-16L13 39"
        />
        <circle cx="24" cy="23" r="3" fill="currentColor" />
    </svg>
</template>
