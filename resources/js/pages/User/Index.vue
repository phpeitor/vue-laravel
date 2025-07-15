<script setup>
import MagnifyingGlass from "@/components/Icons/MagnifyingGlass.vue";
import Pagination from "@/components/Pagination.vue";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { onMounted, ref, watch, computed } from "vue";
import useAuth from "@/composables/useAuth";

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

let pageNumber = ref(1),
    searchTerm = ref(usePage().props.search ?? "");

const pageNumberUpdated = (link) => {
    pageNumber.value = link.url.split("=")[1];
};

let usersUrl = computed(() => {
    const url = new URL(route("users.index"));

    url.searchParams.set("page", pageNumber.value);

    if (searchTerm.value) {
        url.searchParams.set("search", searchTerm.value);
    }

    return url;
});

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

  <AuthenticatedLayout>
    <div class="bg-background text-foreground py-10 transition-colors duration-300">
      <div class="mx-auto max-w-7xl">
        <div class="px-4 sm:px-6 lg:px-8">
          <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
              <h1 class="text-xl font-semibold text-foreground">Lista de Usuarios</h1>
            </div>

            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
              <transition name="fade">
                <div
                  v-if="flashMessage"
                  class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300 text-sm p-2 rounded-md mb-2 transition"
                >
                  <span class="mr-2">🙎‍♂️</span>
                  <span>{{ flashMessage }}</span>
                  <button @click="flashMessage = ''" class="ml-2 text-green-600 hover:text-green-800">ㄨ</button>
                </div>
              </transition>
            </div>

            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none" v-if="hasPermission('add user')">
              <Link
                :href="route('users.create')"
                class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
              >
                Registrar
              </Link>
            </div>
          </div>

          <div class="flex flex-col justify-start sm:flex-row mt-6">
            <div class="relative text-sm text-foreground col-span-3">
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

          <div class="mt-8 flex flex-col">
            <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
              <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                <div class="overflow-hidden shadow ring-1 ring-border md:rounded-lg relative">
                  <table class="min-w-full divide-y divide-border">
                    <thead class="bg-muted">
                      <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-foreground sm:pl-6">ID</th>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-foreground sm:pl-6">Nombre Completo</th>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-foreground sm:pl-6">Email</th>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-foreground sm:pl-6">Password</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-foreground">Creado</th>
                        <th class="relative py-3.5 pl-3 pr-4 sm:pr-6" />
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-card">
                      <tr v-for="user in users.data" :key="user.id">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-foreground sm:pl-6">
                          {{ user.id }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-foreground sm:pl-6">
                          {{ user.name }}
                          <span
                            class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="user.estado == 1 ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300'"
                          >
                            {{ user.estado == 1 ? 'Activo' : 'Inactivo' }}
                          </span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-muted-foreground">{{ user.email }}</td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-muted-foreground">{{ user.password }}</td>
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
    </div>
  </AuthenticatedLayout>
</template>
