<script setup>

import { ref } from "vue";
import ApplicationLogo from "@/components/ApplicationLogo.vue";
import Dropdown from "@/components/Dropdown.vue";
import DropdownLink from "@/components/DropdownLink.vue";
import NavLink from "@/components/NavLink.vue";
import ResponsiveNavLink from "@/components/ResponsiveNavLink.vue";
import { Link } from "@inertiajs/vue3";
import useAuth from "@/composables/useAuth";

const { hasPermission } = useAuth();
const showingNavigationDropdown = ref(false);

import { onMounted } from 'vue';
import { useToast } from '@/components/ui/toast/use-toast';
import { Toaster } from '@/components/ui/toast';

const { toast } = useToast();

onMounted(() => {
    window.Echo.channel('online-users')
        .listen('.UserLoggedIn', (e) => {
            toast({
                    title: 'Usuario en línea',
                    description: `${e.user.name}`,
                    variant: 'success',
                });
        });
});
</script>

<template>
   
    <div class="min-h-screen bg-background text-foreground dark:bg-background dark:text-foreground transition-colors duration-300">

        <nav class="bg-background dark:bg-background border-b border-border">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="shrink-0 flex items-center">
                            <Link :href="route('dashboard')">
                                <ApplicationLogo
                                    class="block h-9 w-auto fill-current text-foreground"
                                />
                            </Link>
                        </div>

                        <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                            <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                Inicio
                            </NavLink>
                            
                            <NavLink :href="route('templates.index')" 
                                    :active="
                                            route().current('templates.index') ||
                                            route().current('templates.create') ||
                                            route().current('templates.edit')
                                        "
                                    v-if="hasPermission('templates')"> Plantillas 
                            </NavLink>

                            <NavLink :href="route('users.index')" 
                                    :active="
                                            route().current('users.index') ||
                                            route().current('users.create') ||
                                            route().current('users.edit')
                                        "
                                    v-if="hasPermission('users')"> Usuarios 
                            </NavLink>
                        </div>
                    </div>

                    <div class="hidden sm:flex sm:items-center sm:ms-6">
                        <!-- Settings Dropdown -->
                        <div class="ms-3 relative">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <span class="inline-flex rounded-md">
                                        <button
                                            type="button"
                                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-muted-foreground bg-background hover:text-foreground-700 focus:outline-none transition ease-in-out duration-150"
                                        >
                                            {{ $page.props.auth.user.name }}

                                            <svg
                                                class="ms-2 -me-0.5 h-4 w-4"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </span>
                                </template>

                                <template #content>
                                    <DropdownLink
                                        :href="route('profile.edit')"
                                    >
                                        Profile
                                    </DropdownLink>
                                    <DropdownLink
                                        :href="route('logout')"
                                        method="post"
                                        as="button"
                                    >
                                        Log Out
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <!-- Hamburger -->
                    <div class="-me-2 flex items-center sm:hidden">
                        <button
                            @click="
                                showingNavigationDropdown =
                                    !showingNavigationDropdown
                            "
                            class="inline-flex items-center justify-center p-2 rounded-md text-foreground-400 hover:text-muted-foreground hover:bg-background focus:outline-none focus:bg-background focus:text-muted-foreground transition duration-150 ease-in-out"
                        >
                            <svg
                                class="h-6 w-6"
                                stroke="currentColor"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    :class="{
                                        hidden: showingNavigationDropdown,
                                        'inline-flex':
                                            !showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path
                                    :class="{
                                        hidden: !showingNavigationDropdown,
                                        'inline-flex':
                                            showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div
                :class="{
                    block: showingNavigationDropdown,
                    hidden: !showingNavigationDropdown,
                }"
                class="sm:hidden"
            >
                <div class="pt-2 pb-3 space-y-1">
                    <ResponsiveNavLink
                        :href="route('dashboard')"
                        :active="route().current('dashboard')"
                    >
                        Dashboard
                    </ResponsiveNavLink>
                </div>

                <div class="pt-4 pb-1 border-t border-border">
                    <div class="px-4">
                        <div class="font-medium text-base text-foreground">
                            {{ $page.props.auth.user.name }}
                        </div>
                        <div class="font-medium text-sm text-muted-foreground">
                            {{ $page.props.auth.user.email }}
                        </div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('profile.edit')">
                            Profile
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('logout')"
                            method="post"
                            as="button"
                        >
                            Log Out
                        </ResponsiveNavLink>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Heading -->
        <header class="bg-muted shadow" v-if="$slots.header">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main >
            <slot />
        </main>
    </div>
   
    <Toaster />

</template>