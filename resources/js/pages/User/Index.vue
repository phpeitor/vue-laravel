<script setup>
import MagnifyingGlass from "@/components/Icons/MagnifyingGlass.vue";
import Pagination from "@/components/Pagination.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { onMounted, ref, watch, computed } from "vue";
import useAuth from "@/composables/useAuth";
import { SquarePlus } from 'lucide-vue-next'

const breadcrumbs = [
  {
    title: 'Users',
    href: '/users',
  }
];

const { hasPermission } = useAuth();
const props = defineProps({

    users: {
        type: Object,
    }

});

const flashMessage = ref('');

onMounted(() => {
    flashMessage.value = usePage().props.flash?.success || '';

    if (flashMessage.value) {
        setTimeout(() => {
            flashMessage.value = '';
        }, 3000); 
    }
    //console.log("Usuarios:", props.users.data);
});

const pageNumber = ref(1),
    searchTerm = ref(usePage().props.search ?? "");
const sortDirection = ref('asc')

const pageNumberUpdated = (link) => {
    pageNumber.value = link.url.split("=")[1];
};

const usersUrl = computed(() => {
    const url = new URL(route("users.index"));
    url.searchParams.set("page", pageNumber.value);

    if (searchTerm.value) {
        url.searchParams.set("search", searchTerm.value);
    }

    url.searchParams.set("sort", "id");
    url.searchParams.set("direction", sortDirection.value);
    return url;
});

const toggleSort = () => {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    pageNumber.value = 1
}

watch(
    () => usersUrl.value,
    (newValue) => {
        router.visit(newValue, {
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    }
);

watch(
    () => searchTerm.value,
    (value) => {
        if (value) {
            pageNumber.value = 1;
        }
    }
);

const deleteForm = useForm({});
const deleteUser = (id) => {
    if (confirm("¿Desea eliminar este usuario?")) {
        deleteForm.delete(route("users.destroy", id), {
            preserveScroll: true,
        });
    }
};
</script>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.5s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
.fade-enter-to, .fade-leave-from {
  opacity: 1;
}
</style>

<template>
  <Head title="Users" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="bg-background text-foreground py-10 transition-colors duration-300">
      <div class="mx-auto max-w-7xl">
        <div class="px-4 sm:px-6 lg:px-8">
          <div class="sm:flex sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
              <h1 class="text-2xl font-semibold text-foreground">Usuarios</h1>

              <Link
                v-if="hasPermission('add user')"
                :href="route('users.create')"
                class="inline-flex items-center justify-center rounded-md bg-indigo-600 p-2 text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                title="Registrar usuario"
              >
                <SquarePlus class="w-5 h-5" />
              </Link>
            </div>

            <div class="flex items-center gap-4">
              <div class="relative text-sm text-foreground flex-1 sm:flex-none sm:w-64">
                <div class="absolute pl-2 left-0 top-0 bottom-0 flex items-center pointer-events-none text-muted-foreground">
                  <MagnifyingGlass />
                </div>

                <input
                  type="text"
                  v-model="searchTerm"
                  placeholder="Buscar"
                  id="search"
                  class="block w-full rounded-lg border-0 py-2 pl-10 bg-card text-foreground ring-1 ring-inset ring-border placeholder:text-muted-foreground focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-colors"
                />
              </div>
            </div>
          </div>

          <transition name="fade">
            <div
              v-if="flashMessage"
              class="mt-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300 text-sm p-2 rounded-md transition"
            >
              <span class="mr-2">🙎‍♂️</span>
              <span>{{ flashMessage }}</span>
              <button @click="flashMessage = ''" class="ml-2 text-green-600 hover:text-green-800">ㄨ</button>
            </div>
          </transition>

          <div class="mt-8 flex flex-col w-full">
            <div class="rounded-md border shadow overflow-hidden">
              <div class="w-full overflow-x-auto">
                <table class="w-full divide-y divide-border">
                    <thead class="bg-muted">
                      <tr>
                       <th
                          @click="toggleSort"
                          class="cursor-pointer py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-foreground sm:pl-6 select-none"
                        >
                          ID
                          <span class="ml-1 text-xs">
                            {{ sortDirection === 'asc' ? '▲' : '▼' }}
                          </span>
                        </th>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-foreground sm:pl-6">Nombre Completo</th>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-foreground sm:pl-6">Email</th>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-foreground sm:pl-6">Password</th>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-foreground sm:pl-6">Estado</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-foreground">Creado</th>
                        <th class="relative py-3.5 pl-3 pr-4 sm:pr-6"> Acciones </th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-card">
                      <tr v-for="user in users.data" :key="user.id">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-foreground sm:pl-6">
                          {{ user.id }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-foreground sm:pl-6">
                          {{ user.name }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-muted-foreground">{{ user.email }}</td>
                        <td class="px-3 py-4 text-sm text-muted-foreground max-w-xs truncate" :title="user.password">{{ user.password.slice(-8) }}***</td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                          <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="user.estado == 1
                              ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300'
                              : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300'"
                          >
                            {{ user.estado == 1 ? 'ACTIVE' : 'INACTIVE' }}
                          </span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-muted-foreground">
                          {{ user.created_at_formatted }}
                        </td>
                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                          <Link
                            v-if="hasPermission('edit user')"
                            :href="route('users.edit', user.id)"
                            class="text-indigo-600 hover:text-indigo-900"
                          >
                            Edit
                          </Link>

                          <button
                            v-if="user.estado == 1 && hasPermission('delete user')"
                            @click="deleteUser(user.id)"
                            class="ml-2 text-indigo-600 hover:text-indigo-900"
                          >
                            Delete
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              <Pagination :data="users" :pageNumberUpdated="pageNumberUpdated" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
