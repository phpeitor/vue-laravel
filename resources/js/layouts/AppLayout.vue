<script setup lang="ts">

declare global {
    interface Window {
        Echo: any;
    }
}

import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});


import { onMounted } from 'vue';
import { useToast } from '@/components/ui/toast/use-toast';
import { Toaster } from '@/components/ui/toast';

const { toast } = useToast();

onMounted(() => {
    window.Echo.channel('online-users')
        .listen('.UserLoggedIn', (e: any) => {
            toast({
                    title: 'Usuario en línea',
                    description: `${e.user.name}`,
                    variant: 'success',
                });
        });

    window.Echo.channel('external-events')
        .listen('.ExternalEvent', (e: any) => {
            toast({
                title: `Nuevo evento: ${e.payload.type}`,
                description: JSON.stringify(e.payload.data),
                variant: 'info', 
            });
        });
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Toaster />
        <slot />
    </AppLayout>
</template>