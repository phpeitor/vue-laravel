<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});


import { onMounted } from 'vue';

onMounted(() => {

    console.log('AppLayout mounted');
    window.Echo.channel('online-users')
        .listen('.UserLoggedIn', (e: any) => {
            alert(`${e.user.name} está en línea`);
        });
});


</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
