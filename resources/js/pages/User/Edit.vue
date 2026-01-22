<script setup>
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import { watch, ref, onMounted } from "vue";
import InputError from "@/components/InputError.vue";
import { Switch } from '@/components/ui/switch'
const breadcrumbs = [
  {
    title: 'Users',
    href: '/users',
  },
  {
    title: 'Editar usuario',
    href: '/users/edit',
  },
];

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
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full py-10 px-6">
        <div class="grid grid-cols-12 gap-8">

            <!-- FORM -->
            <div class="col-span-12 lg:col-span-7">
            <form @submit.prevent="submit">
                <div class="shadow-sm rounded-md overflow-hidden transition-colors">

                <!-- CONTENT -->
                <div class="bg-card text-foreground py-6 px-6 space-y-6">

                    <!-- HEADER -->
                    <div class="flex items-center justify-between border-b border-border pb-4">
                    <div>
                        <h3 class="text-xl font-semibold text-foreground">
                        User Information
                        </h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                        Update user data and permissions.
                        </p>
                    </div>

                    <!-- ESTADO -->
                    <label class="inline-flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="form.estado" class="sr-only peer" />
                        <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:bg-green-500 relative transition-colors">
                        <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                        </div>
                        <span
                        class="text-sm font-medium"
                        :class="form.estado ? 'text-green-600' : 'text-red-600'"
                        >
                        {{ form.estado ? 'Activo' : 'Inactivo' }}
                        </span>
                    </label>
                    </div>

                    <!-- FIELDS -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-foreground">Name</label>
                        <input
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full rounded-md border border-border bg-background px-3 py-2"
                        :class="{ 'border-red-500': form.errors.name }"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-foreground">Email</label>
                        <input
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full rounded-md border border-border bg-background px-3 py-2"
                        :class="{ 'border-red-500': form.errors.email }"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-medium text-foreground">Password</label>
                        <input
                        v-model="form.password"
                        type="password"
                        placeholder="••••••••"
                        class="mt-1 block w-full rounded-md border border-border bg-background px-3 py-2"
                        :class="{ 'border-red-500': form.errors.password }"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <!-- Role -->
                    <div>
                        <label class="block text-sm font-medium text-foreground">Role</label>
                        <select
                        v-model="form.role"
                        class="mt-1 block w-full rounded-md border border-border bg-background px-3 py-2"
                        :class="{ 'border-red-500': form.errors.role }"
                        >
                        <option value="">Select a role</option>
                        <option v-for="role in roles" :key="role" :value="role">
                            {{ role }}
                        </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.role" />
                    </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="flex justify-end gap-3 border-t border-border bg-muted px-6 py-4">
                    <Link
                    :href="route('users.index')"
                    class="px-4 py-2 text-sm rounded-md border border-border text-foreground hover:bg-muted/80"
                    >
                    Cancel
                    </Link>

                    <button
                    type="submit"
                    class="px-4 py-2 text-sm rounded-md bg-primary text-primary-foreground hover:bg-primary/90"
                    >
                    Update
                    </button>
                </div>

                </div>
            </form>
            </div>

            <!-- COLUMNA FANTASMA -->
            <div class="hidden lg:block lg:col-span-5"></div>

        </div>
        </div>
  </AppLayout>
</template>