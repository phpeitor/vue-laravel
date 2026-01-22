<script setup>
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { watch, ref } from "vue";
import InputError from "@/components/InputError.vue";

const breadcrumbs = [
  {
    title: 'Users',
    href: '/users',
  },
  {
    title: 'Nuevo usuario',
    href: '/users/create',
  },
];

defineProps({
    classes: {
        type: Object,
    },
    roles: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: "",
    email: "",
    password: "",
    role: "",
});

const submit = () => {
    form.post(route("users.store"), {
        preserveScroll: true,
    });
};
</script>

<template>
  <Head title="Users" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="w-full py-10 px-6">
        <div class="grid grid-cols-12 gap-8">
          <div class="col-span-12 lg:col-span-7">
            <form @submit.prevent="submit">
              <div class="shadow-sm sm:rounded-md sm:overflow-hidden transition-colors">
                <div class="bg-card text-foreground py-6 px-4 space-y-6 sm:p-6">
                  <div class="border-b border-border pb-4">
                    <h3 class="text-xl font-semibold text-foreground">
                      User Information
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                      Create a new user and assign a role.
                    </p>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div>
                      <label for="name" class="block text-sm font-medium text-foreground">Name</label>
                      <input
                        v-model="form.name"
                        type="text"
                        id="name"
                        class="mt-1 block w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:ring-primary focus:border-primary"
                        :class="{ 'border-red-500': form.errors.name }"
                      />
                      <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <!-- Email -->
                    <div>
                      <label for="email" class="block text-sm font-medium text-foreground">Email</label>
                      <input
                        v-model="form.email"
                        type="email"
                        id="email"
                        class="mt-1 block w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:ring-primary focus:border-primary"
                        :class="{ 'border-red-500': form.errors.email }"
                      />
                      <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <!-- Password -->
                    <div>
                      <label for="password" class="block text-sm font-medium text-foreground">Password</label>
                      <input
                        v-model="form.password"
                        type="password"
                        id="password"
                        class="mt-1 block w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:ring-primary focus:border-primary"
                        :class="{ 'border-red-500': form.errors.password }"
                      />
                      <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <!-- Role -->
                    <div>
                      <label for="role" class="block text-sm font-medium text-foreground">Role</label>
                      <select
                        v-model="form.role"
                        id="role"
                        class="mt-1 block w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:ring-primary focus:border-primary"
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
                <!-- Buttons -->
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
                      Save
                    </button>
                </div>

              </div>
            </form>
          </div>
          <div class="hidden lg:block lg:col-span-5"></div>
        </div>
    </div>
  </AppLayout>
</template>
