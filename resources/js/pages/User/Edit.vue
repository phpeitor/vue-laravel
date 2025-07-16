<script setup>
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import { watch, ref, onMounted } from "vue";
import InputError from "@/components/InputError.vue";
import { Switch } from '@/components/ui/switch'

defineProps({
    classes: { type: Object },
    roles: { type: Array, default: () => [] },
    currentRole: { type: String, default: '' },
});

const user = usePage().props.user;

const form = useForm({
    name: user.data.name,
    email: user.data.email,
    password: '',
    estado: user.data.estado == 1, 
    role: usePage().props.currentRole,
});

watch(() => form.estado, (val) => {
  console.log("Estado:", val)
});

const submit = () => {

    form.estado = form.estado ? 1 : 0;

    form.put(route("users.update", user.data.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Users" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                <div class="space-y-6 sm:px-6 lg:px-0 lg:col-span-12">
                    <form @submit.prevent="submit">
                        <div class="shadow sm:rounded-md sm:overflow-hidden">
                            <div class="bg-background text-foreground py-6 px-4 space-y-6 sm:p-6">
                                <div>
                                    <h3 class="text-lg leading-6 font-medium text-foreground">
                                        User Information
                                    </h3>
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        Use this form to edit a user.
                                    </p>

                                    <label class="inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            v-model="form.estado"
                                            class="sr-only peer"
                                        />
                                        <div
                                            class="w-11 h-6 bg-muted rounded-full peer peer-checked:bg-green-500 relative transition-colors duration-300"
                                        >
                                            <div
                                                class="absolute left-1 top-1 w-4 h-4 bg-white dark:bg-gray-200 rounded-full transition-transform duration-300 transform peer-checked:translate-x-5"
                                            ></div>
                                        </div>
                                        <span
                                            class="ml-3 text-sm font-medium"
                                            :class="form.estado ? 'text-green-600' : 'text-red-600'"
                                        >
                                            {{ form.estado ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </label>
                                </div>

                                <div class="grid grid-cols-6 gap-6">
                                    <div class="col-span-6 sm:col-span-2">
                                        <label for="name" class="block text-sm font-medium text-foreground">Name</label>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            id="name"
                                            class="mt-1 block w-full bg-background border border-input text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm"
                                            :class="{ 'border-red-500': form.errors.name }"
                                        />
                                        <InputError class="mt-2" :message="form.errors.name" />
                                    </div>

                                    <div class="col-span-6 sm:col-span-2">
                                        <label for="email" class="block text-sm font-medium text-foreground">Email</label>
                                        <input
                                            v-model="form.email"
                                            type="email"
                                            id="email"
                                            autocomplete="email"
                                            class="mt-1 block w-full bg-background border border-input text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm"
                                            :class="{ 'border-red-500': form.errors.email }"
                                        />
                                        <InputError class="mt-2" :message="form.errors.email" />
                                    </div>

                                    <div class="col-span-6 sm:col-span-2">
                                        <label for="password" class="block text-sm font-medium text-foreground">Password</label>
                                        <input
                                            v-model="form.password"
                                            type="password"
                                            id="password"
                                            autocomplete="new-password"
                                            placeholder="••••••••"
                                            class="mt-1 block w-full bg-background border border-input text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm"
                                            :class="{ 'border-red-500': form.errors.password }"
                                        />
                                        <InputError class="mt-2" :message="form.errors.password" />
                                    </div>

                                    <div class="col-span-6 sm:col-span-2">
                                        <label for="role" class="block text-sm font-medium text-foreground">Role</label>
                                        <select
                                            v-model="form.role"
                                            id="role"
                                            class="mt-1 block w-full bg-background border border-input text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm"
                                            :class="{ 'border-red-500': form.errors.role }"
                                        >
                                            <option value="">Select a role</option>
                                            <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.role" />
                                    </div>
                                </div>
                            </div>

                            <div class="px-4 py-3 bg-muted text-right sm:px-6">
                                <Link
                                    :href="route('users.index')"
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md bg-secondary text-secondary-foreground hover:bg-secondary/80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary mr-4"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                                >
                                    Update
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
