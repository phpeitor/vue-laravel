<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandShortcut,
} from '@/components/ui/command';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import useAuth from '@/composables/useAuth';
import type { BreadcrumbItemType } from '@/types';
import { router } from '@inertiajs/vue3';
import { Home, MessageCircleMore, Monitor, Moon, Notebook, Search, Sun, Users, Folder } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>(), {
    breadcrumbs: () => [],
});

const { updateAppearance } = useAppearance();
const { hasPermission } = useAuth();
const commandOpen = ref(false);

type MenuItem = {
    title: string;
    href: string;
    icon: any;
    shortcut?: string;
    canView?: () => boolean;
};

const menuItems = computed<MenuItem[]>(() => {
    const items: MenuItem[] = [
        { title: 'Inicio', href: '/dashboard', icon: Home, shortcut: 'G D' },
        { title: 'Usuarios', href: '/users', icon: Users, shortcut: 'G U', canView: () => hasPermission('users') },
        { title: 'Chat', href: '/chat', icon: MessageCircleMore, shortcut: 'G C' },
        { title: 'Plantillas', href: '/templates', icon: Notebook, shortcut: 'G T', canView: () => hasPermission('templates') },
        { title: 'Campañas', href: '/campaigns', icon: Folder, shortcut: 'G P', canView: () => hasPermission('campaigns') },
    ];

    return items.filter((item) => (item.canView ? item.canView() : true));
});

const openCommandMenu = () => {
    commandOpen.value = true;
};

const navigateTo = (href: string) => {
    commandOpen.value = false;
    router.visit(href);
};

const onKeyDown = (event: KeyboardEvent) => {
    const isSearchShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k';
    if (!isSearchShortcut) return;

    event.preventDefault();
    openCommandMenu();
};

onMounted(() => {
    window.addEventListener('keydown', onKeyDown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeyDown);
});
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2 min-w-0">
            <SidebarTrigger class="-ml-1" />
            <template v-if="props.breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="props.breadcrumbs" />
            </template>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <button
                type="button"
                class="inline-flex h-9 min-w-[230px] items-center justify-between rounded-md border bg-background px-3 text-sm text-muted-foreground shadow-sm transition-colors hover:text-foreground"
                @click="openCommandMenu"
            >
                <span class="inline-flex items-center gap-2 truncate">
                    <Search class="h-4 w-4" />
                    Search documentation...
                </span>
                <kbd class="ml-3 inline-flex items-center gap-1 rounded border bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                    <span>Ctrl</span>
                    <span>K</span>
                </kbd>
            </button>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="outline" size="icon" class="h-9 w-9">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="size-4.5"
                        >
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                            <path d="M12 3l0 18" />
                            <path d="M12 9l4.65 -4.65" />
                            <path d="M12 14.3l7.37 -7.37" />
                            <path d="M12 19.6l8.85 -8.85" />
                        </svg>
                        <span class="sr-only">Cambiar tema</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-40">
                    <DropdownMenuItem @click="updateAppearance('light')">
                        <Sun class="mr-2 h-4 w-4" />
                        Light
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="updateAppearance('dark')">
                        <Moon class="mr-2 h-4 w-4" />
                        Dark
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="updateAppearance('system')">
                        <Monitor class="mr-2 h-4 w-4" />
                        System
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <CommandDialog v-model:open="commandOpen">
            <CommandInput placeholder="Buscar en el menu..." />
            <CommandList>
                <CommandEmpty>Sin resultados.</CommandEmpty>
                <CommandGroup heading="Navegacion">
                    <CommandItem
                        v-for="item in menuItems"
                        :key="item.href"
                        :value="item.title"
                        @select="() => navigateTo(item.href)"
                    >
                        <component :is="item.icon" class="mr-2 h-4 w-4" />
                        <span>{{ item.title }}</span>
                        <CommandShortcut v-if="item.shortcut">{{ item.shortcut }}</CommandShortcut>
                    </CommandItem>
                </CommandGroup>
            </CommandList>
        </CommandDialog>
    </header>
</template>
