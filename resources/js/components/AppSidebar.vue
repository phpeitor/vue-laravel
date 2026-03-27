<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Users, Notebook, MessageCircleMore, BarChart3 } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import useAuth from '@/composables/useAuth';

const { hasPermission, user, permissions } = useAuth();

/*console.log('🔐 Usuario autenticado:', user.value);
console.log('🔐 Permisos disponibles:', permissions.value);*/

const allNavItems: NavItem[] = [
  { title: 'Inicio', href: '/dashboard', icon: LayoutGrid },
  { title: 'Usuarios', href: '/users', icon: Users },
  { title: 'Plantillas', href: '/templates', icon: Notebook },
  { title: 'Campañas', href: '/campaigns', icon: Folder },
  { title: 'Chat', href: '/chat', icon: MessageCircleMore },
  {
    title: 'Reportes',
    icon: BarChart3,
    items: [
      { title: 'Interacciones', href: '/reports/interactions' },
      { title: 'Threads', href: '/reports/threads' },
    ],
  },
];

const mainNavItems = allNavItems.filter(item => {
  if (item.title === 'Usuarios') {
    return hasPermission('users');
  }
  if (item.title === 'Plantillas') {
    return hasPermission('templates'); 
  }
  if (item.title === 'Campañas') {
    return hasPermission('campaigns'); 
  }
  if (item.title === 'Reportes') {
    return hasPermission('reports'); 
  }
  return true;
})


const footerNavItems: NavItem[] = [
    // {
    //     title: 'Github Repo',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits',
    //     icon: BookOpen,
    // },
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
                        
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
