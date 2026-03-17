<script setup lang="ts">
import MagnifyingGlass from "@/components/Icons/MagnifyingGlass.vue";
import Pagination from "@/components/Pagination.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { onBeforeUnmount, onMounted, ref, watch, computed } from "vue";
import useAuth from "@/composables/useAuth";
import { SquarePlus, Edit, Trash2, DoorOpen, Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useToast } from '@/components/ui/toast'
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogCancel,
  AlertDialogAction,
} from '@/components/ui/alert-dialog'

const breadcrumbs = [
  {
    title: 'Users',
    href: '/users',
  }
];

const { hasPermission } = useAuth();
const props = defineProps<{ users: { data: any[] } }>()
const page = usePage() as any
const { toast } = useToast()

onMounted(() => {
  deleteDialogOpen.value = false
  deletingUserId.value = null

  const msg = page.props.flash?.success
  if (msg) {
    toast({ title: msg, variant: 'success' })
  }
});

const pageNumber = ref<number>((page.props.users as any)?.meta?.current_page ?? 1)
const searchTerm = ref<string>(page.props.search ?? "")
const debouncedSearchTerm = ref<string>(String(page.props.search ?? ""))
const sortDirection = ref<string>(String(page.props.direction ?? 'asc'))
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null

type PaginationLink = { url?: string | null } | null

const pageNumberUpdated = (link: PaginationLink) => {
  const urlStr = typeof link === 'object' ? link?.url ?? '' : ''
  if (!urlStr) return

  try {
    const parsed = new URL(urlStr, window.location.origin)
    const pageValue = parsed.searchParams.get('page')
    pageNumber.value = Number(pageValue ?? '1') || 1
  } catch {
    pageNumber.value = 1
  }
};

const usersUrl = computed(() => {
    const url = new URL(route("users.index"));
    url.searchParams.set("page", String(pageNumber.value));

    if (debouncedSearchTerm.value) {
      url.searchParams.set("search", String(debouncedSearchTerm.value));
    }

    url.searchParams.set("sort", "id");
    url.searchParams.set("direction", String(sortDirection.value));
    return url;
});

const toggleSort = () => {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    pageNumber.value = 1
}

watch(
    () => usersUrl.value,
    (newValue) => {
    deleteDialogOpen.value = false
    deletingUserId.value = null

        router.visit(newValue, {
            replace: true,
      preserveState: false,
            preserveScroll: true,
        });
    }
);

watch(
    () => searchTerm.value,
    (value) => {
        if (searchDebounceTimer) {
          clearTimeout(searchDebounceTimer)
        }

        searchDebounceTimer = setTimeout(() => {
          debouncedSearchTerm.value = value.trim()
          pageNumber.value = 1
        }, 350)
    }
);

onBeforeUnmount(() => {
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }
})

const deleteForm = useForm({});
const deleteDialogOpen = ref(false)
const deletingUserId = ref<number | null>(null)
const roomTimelineOpen = ref(false)
const roomTimelineLoading = ref(false)
const roomTimelineRows = ref<Array<{ company_name: string | null; channel_name: string | null; room: string | null; asignacion: 'SI' | 'NO' }>>([])
const roomTimelineUserName = ref('')
const roomTimelineRowsWithRoom = computed(() =>
  roomTimelineRows.value.filter((row) => String(row.room ?? '').trim() !== '')
)

const openDeleteDialog = (id: number) => {
  deletingUserId.value = id
  deleteDialogOpen.value = true
}

const confirmDelete = async () => {
  const id = deletingUserId.value
  if (!id) return
  try {
    await deleteForm.delete(route('users.destroy', id), { preserveScroll: true })
    toast({ title: 'Usuario eliminado', variant: 'success' })
  } catch (e) {
    toast({ title: 'Error al eliminar', description: String(e || 'Error'), variant: 'destructive' })
  } finally {
    deleteDialogOpen.value = false
    deletingUserId.value = null
  }
}

const openRoomTimeline = async (user: { id: number; name: string }) => {
  roomTimelineUserName.value = user.name
  roomTimelineOpen.value = true
  roomTimelineLoading.value = true

  try {
    const res = await fetch(route('users.rooms-timeline', user.id), {
      headers: { Accept: 'application/json' },
    })

    if (!res.ok) {
      roomTimelineRows.value = []
      return
    }

    roomTimelineRows.value = (await res.json()) ?? []
  } catch {
    roomTimelineRows.value = []
  } finally {
    roomTimelineLoading.value = false
  }
}
</script>

<template>
  <Head title="Users" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <TooltipProvider :delay-duration="100">
      <div class="bg-background text-foreground py-10 transition-colors duration-300">
      <div class="mx-auto max-w-7xl">
        <div class="px-4 sm:px-6 lg:px-8">
          <div class="sm:flex sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
              <h1 class="text-2xl font-semibold text-foreground">Usuarios</h1>

              <Tooltip v-if="hasPermission('add user')">
                <TooltipTrigger as-child>
                  <Link
                    :href="route('users.create')"
                    class="inline-flex items-center justify-center rounded-md bg-indigo-600 p-2 text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <SquarePlus class="w-5 h-5" />
                  </Link>
                </TooltipTrigger>
                <TooltipContent>
                  <p>Registrar</p>
                </TooltipContent>
              </Tooltip>
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
                          <div class="flex items-center gap-2">
                            <span
                              v-if="user.is_online"
                              class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse"
                              aria-label="Conectado"
                              title="Conectado"
                            />
                            <span>{{ user.name }}</span>
                          </div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-muted-foreground">{{ user.email }}</td>
                        <td class="px-3 py-4 text-sm text-muted-foreground max-w-xs truncate">
                          <Tooltip>
                            <TooltipTrigger as-child>
                              <span class="inline-block max-w-xs truncate cursor-help align-middle">{{ user.password.slice(-8) }}***</span>
                            </TooltipTrigger>
                            <TooltipContent>
                              <p class="max-w-xs break-all">{{ user.password }}</p>
                            </TooltipContent>
                          </Tooltip>
                        </td>
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
                          <div class="flex items-center justify-end gap-2">
                            <Tooltip>
                              <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="openRoomTimeline(user)">
                                  <DoorOpen class="w-4 h-4 text-muted-foreground" />
                                </Button>
                              </TooltipTrigger>
                              <TooltipContent>
                                <p>Rooms</p>
                              </TooltipContent>
                            </Tooltip>

                            <Tooltip v-if="hasPermission('edit user')">
                              <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" as-child>
                                  <Link :href="route('users.edit', user.id)" class="p-2">
                                    <Edit class="w-4 h-4 text-muted-foreground" />
                                  </Link>
                                </Button>
                              </TooltipTrigger>
                              <TooltipContent>
                                <p>Editar</p>
                              </TooltipContent>
                            </Tooltip>

                            <Tooltip v-if="user.estado == 1 && hasPermission('delete user')">
                              <TooltipTrigger as-child>
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  @click="openDeleteDialog(user.id)"
                                >
                                  <Trash2 class="w-4 h-4 text-destructive" />
                                </Button>
                              </TooltipTrigger>
                              <TooltipContent>
                                <p>Eliminar</p>
                              </TooltipContent>
                            </Tooltip>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              <Pagination :data="users" :pageNumberUpdated="pageNumberUpdated" />
            </div>

            <!-- Delete confirmation dialog -->
            <AlertDialog v-model:open="deleteDialogOpen">
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Eliminar usuario</AlertDialogTitle>
                  <AlertDialogDescription>
                    Esta acción es irreversible. ¿Desea eliminar el usuario seleccionado?
                  </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                  <AlertDialogCancel as-child>
                    <button class="inline-flex items-center px-3 py-2 rounded-md border">Cancelar</button>
                  </AlertDialogCancel>

                  <AlertDialogAction as-child>
                    <button @click="confirmDelete" class="inline-flex items-center px-3 py-2 rounded-md bg-destructive text-white">Eliminar</button>
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>

            <Dialog v-model:open="roomTimelineOpen">
              <DialogContent class="sm:max-w-[640px]">
                <DialogHeader>
                  <DialogTitle>Rooms del usuario</DialogTitle>
                  <DialogDescription>
                    {{ roomTimelineUserName }}
                  </DialogDescription>
                </DialogHeader>

                <div v-if="roomTimelineLoading" class="flex items-center gap-2 py-6 text-sm text-muted-foreground">
                  <Loader2 class="h-4 w-4 animate-spin" />
                  Cargando rooms...
                </div>

                <div v-else class="max-h-96 overflow-y-auto pr-1">
                  <div v-if="!roomTimelineRowsWithRoom.length" class="text-sm text-muted-foreground py-2">
                    No hay rooms para mostrar.
                  </div>

                  <div v-for="(row, idx) in roomTimelineRowsWithRoom" :key="`${idx}-${row.company_name}-${row.channel_name}-${row.room}`" class="relative pl-6 pb-5">
                    <span class="absolute left-0 top-1.5 h-2.5 w-2.5 rounded-full" :class="row.asignacion === 'SI' ? 'bg-green-500' : 'bg-muted-foreground/40'" />
                    <span v-if="idx !== roomTimelineRowsWithRoom.length - 1" class="absolute left-[4px] top-4 h-full w-px bg-border" />

                    <div class="text-sm font-medium">
                      {{ row.company_name || 'Sin compañía' }} - {{ row.channel_name || 'Sin canal' }}
                    </div>
                    <div class="text-sm text-muted-foreground">
                      Room: {{ row.room || 'Sin room' }}
                    </div>
                    <div class="mt-1">
                      <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="row.asignacion === 'SI' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-muted text-muted-foreground'">
                        Asignado: {{ row.asignacion }}
                      </span>
                    </div>
                  </div>
                </div>

                <DialogFooter>
                  <Button type="button" variant="outline" @click="roomTimelineOpen = false">Cerrar</Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          </div>
        </div>
      </div>
      </div>
    </TooltipProvider>
  </AppLayout>
</template>