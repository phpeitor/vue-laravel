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
import { useToast } from '@/components/ui/toast/use-toast';
import { Toaster } from '@/components/ui/toast';

const { toast } = useToast();

onMounted(() => {

    //console.log('AppLayout mounted');
    window.Echo.channel('online-users')
        .listen('.UserLoggedIn', (e: any) => {
            //alert(`${e.user.name} está en línea`);
            toast({
                    title: 'Usuario en línea',
                    description: `${e.user.name}`,
                    variant: 'success',
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
