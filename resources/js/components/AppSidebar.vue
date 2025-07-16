<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Users, Notebook  } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import useAuth from '@/composables/useAuth';

const { hasPermission } = useAuth();

const allNavItems: NavItem[] = [
<<<<<<< HEAD
    { title: 'Inicio', href: '/dashboard', icon: LayoutGrid },
    { title: 'Plantillas', href: '/templates', icon: Notebook },
    { title: 'Usuarios', href: '/users', icon: Users },
=======
    { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
    { title: 'Students', href: '/students', icon: Notebook },
    { title: 'Users', href: '/users', icon: Users },
>>>>>>> gitlab/main
];

const mainNavItems = allNavItems.filter(item => {
    if (item.title === 'Users') {
        return hasPermission('users');
    }
    return true;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
<<<<<<< HEAD
                        
=======

>>>>>>> gitlab/main
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
<<<<<<< HEAD
            <!--<NavFooter :items="footerNavItems" />-->
=======
            <NavFooter :items="footerNavItems" />
>>>>>>> gitlab/main
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
