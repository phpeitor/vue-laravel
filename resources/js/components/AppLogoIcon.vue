<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <span class="font-script text-4xl leading-none">Talina</span>
    <img src="/img/x.gif" class="w-[30px] h-auto max-w-none" :class="className" v-bind="$attrs" alt="Logo" />
</template>