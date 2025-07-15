<script setup>
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { watch, ref } from "vue";
import InputError from "@/components/InputError.vue";

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

  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <div class="space-y-6 sm:px-6 lg:px-0 lg:col-span-12">
          <form @submit.prevent="submit">
            <div class="shadow-sm sm:rounded-md sm:overflow-hidden transition-colors">
              <div class="bg-card text-foreground py-6 px-4 space-y-6 sm:p-6">
                <div>
                  <h3 class="text-lg leading-6 font-medium text-foreground">User Information</h3>
                  <p class="mt-1 text-sm text-muted-foreground">
                    Use this form to create a new user.
                  </p>
                </div>

                <div class="grid grid-cols-6 gap-6">
                  <!-- Name -->
                  <div class="col-span-4 sm:col-span-2">
                    <label for="name" class="block text-sm font-medium text-foreground">Name</label>
                    <input
                      v-model="form.name"
                      type="text"
                      id="name"
                      class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                      :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.name }"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                  </div>

                  <!-- Email -->
                  <div class="col-span-4 sm:col-span-2">
                    <label for="email" class="block text-sm font-medium text-foreground">Email Address</label>
                    <input
                      v-model="form.email"
                      type="email"
                      id="email"
                      autocomplete="email"
                      class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                      :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.email }"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                  </div>

                  <!-- Password -->
                  <div class="col-span-4 sm:col-span-2">
                    <label for="password" class="block text-sm font-medium text-foreground">Password</label>
                    <input
                      v-model="form.password"
                      type="password"
                      id="password"
                      autocomplete="password"
                      class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                      :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.password }"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                  </div>

                  <!-- Role -->
                  <div class="col-span-4 sm:col-span-2">
                    <label for="role" class="block text-sm font-medium text-foreground">Role</label>
                    <select
                      v-model="form.role"
                      id="role"
                      class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                      :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.role }"
                    >
                      <option value="">Select a role</option>
                      <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.role" />
                  </div>
                </div>
              </div>

              <!-- Buttons -->
              <div class="px-4 py-3 bg-muted text-right sm:px-6">
                <Link
                  :href="route('users.index')"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-primary bg-muted hover:bg-muted/80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary mr-4 transition-colors"
                >
                  Cancel
                </Link>

                <button
                    type="submit"
                    class="bg-primary text-primary-foreground hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors px-4 py-2 rounded-md text-sm font-medium"
                >
                    Save
                </button>

              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
