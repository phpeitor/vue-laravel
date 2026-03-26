<script setup lang="ts">
declare global {
    interface Window {
        Echo: any;
    }
}

import axios from 'axios';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePage } from '@inertiajs/vue3';
import type { BreadcrumbItemType } from '@/types';
import { Bug } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { Toaster as Sonner, toast as sonnerToast } from 'vue-sonner';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

import { Toaster } from '@/components/ui/toast';
const page = usePage();
const openIssueDialog = ref(false);
const issueType = ref('BUG_REPORT');
const issueSeverity = ref('MINOR');
const issueTitle = ref('');
const issueDescription = ref('');
const issueSubmitting = ref(false);

function resetIssueForm() {
    issueType.value = 'BUG_REPORT';
    issueSeverity.value = 'MINOR';
    issueTitle.value = '';
    issueDescription.value = '';
}

async function submitIssue() {
    if (!issueTitle.value.trim() || !issueDescription.value.trim()) {
        sonnerToast.error('Completa titulo y descripcion');
        return;
    }

    if (issueType.value === 'BUG_REPORT' && !issueSeverity.value) {
        sonnerToast.error('Selecciona la severidad del bug');
        return;
    }

    issueSubmitting.value = true;

    try {
        const payload = {
            tipo: issueType.value,
            title: issueTitle.value.trim(),
            descripcion: issueDescription.value.trim(),
            severity: issueType.value === 'BUG_REPORT' ? issueSeverity.value : null,
        };

        const response = await axios.post(route('issues.store'), payload, {
            headers: {
                Accept: 'application/json',
            },
        });

        sonnerToast.success(response?.data?.message || 'Incidencia registrada correctamente.');
        openIssueDialog.value = false;
        resetIssueForm();
    } catch (error: any) {
        sonnerToast.error(error?.response?.data?.message || 'No se pudo registrar la incidencia');
    } finally {
        issueSubmitting.value = false;
    }
}

onMounted(() => {
    window.Echo.channel('online-users')
        .listen('.UserLoggedIn', (e: any) => {
            const currentUserId = Number((page.props as any)?.auth?.user?.id ?? 0);
            const eventUserId = Number(e?.user?.id ?? 0);

            if (currentUserId > 0 && currentUserId === eventUserId) {
                return;
            }

            sonnerToast.success('Usuario en línea', {
                description: e?.user?.name ?? 'Usuario',
            });
        });


});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Toaster />
        <Sonner rich-colors position="bottom-right" />
        <slot />

        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        class="fixed bottom-6 right-6 z-[60] h-11 w-11 p-0.5 rounded-full shadow-lg [&_svg]:size-6"
                        @click="openIssueDialog = true"
                    >
                        <Bug />
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="left" align="center">
                    <span>Issue</span>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>

        <Dialog v-model:open="openIssueDialog">
            <DialogContent class="max-w-xl">
                <DialogHeader>
                    <DialogTitle>Registrar incidencia</DialogTitle>
                    <DialogDescription>
                        Reporta un bug o registra una sugerencia
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            :class="issueType === 'BUG_REPORT' ? 'border-red-500 text-red-600' : ''"
                            @click="issueType = 'BUG_REPORT'"
                        >
                            Bug Reporte
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            :class="issueType === 'SUGGESTION' ? 'border-blue-500 text-blue-600' : ''"
                            @click="issueType = 'SUGGESTION'"
                        >
                            Sugerencia
                        </Button>
                    </div>

                    <div v-if="issueType === 'BUG_REPORT'" class="space-y-2">
                        <p class="text-sm font-medium text-muted-foreground">Severity</p>
                        <div class="grid grid-cols-3 gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                :class="issueSeverity === 'MINOR' ? 'border-green-500 text-green-600' : ''"
                                @click="issueSeverity = 'MINOR'"
                            >
                                Baja
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                :class="issueSeverity === 'MODERATE' ? 'border-yellow-500 text-yellow-600' : ''"
                                @click="issueSeverity = 'MODERATE'"
                            >
                                Moderado
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                :class="issueSeverity === 'CRITICAL' ? 'border-red-500 text-red-600' : ''"
                                @click="issueSeverity = 'CRITICAL'"
                            >
                                Crítico
                            </Button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <p class="text-sm font-medium text-muted-foreground">Title</p>
                        <Input
                            v-model="issueTitle"
                            type="text"
                            maxlength="50"
                        />
                    </div>

                    <div class="space-y-2">
                        <p class="text-sm font-medium text-muted-foreground">Description</p>
                        <textarea
                            v-model="issueDescription"
                            rows="5"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm ring-1 ring-border focus:outline-none"
                            placeholder="Describe brevemente el problema o sugerencia..."
                            maxlength="255"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="outline">Cancelar</Button>
                    </DialogClose>
                    <Button type="button" :disabled="issueSubmitting" @click="submitIssue">
                        <template v-if="issueSubmitting">Guardando...</template>
                        <template v-else>Enviar</template>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>