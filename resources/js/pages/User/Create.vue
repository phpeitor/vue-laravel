<script setup>
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import InputError from "@/components/InputError.vue";
import { Loader2, Trash2 } from "lucide-vue-next";
import { ref, computed, reactive, watch, onBeforeUnmount } from "vue";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

const breadcrumbs = [
  {
    title: 'Users',
    href: '/users',
  },
  {
    title: 'Crear usuario',
    href: '/users/create',
  },
];

const props = defineProps({
  roles: { type: Array, default: () => [] },
  channels: { type: Array, default: () => [] },
  rooms: { type: Array, default: () => [] },
});

const form = useForm({
  name: "",
  username: "",
  email: "",
  password: "",
  role: "",
  channels: [],
  room_assignments: {},
});

const dniLookupLoading = ref(false);
let dniLookupTimer = null;
let lastLookedUpDni = "";

const q = ref("");
const selectedMap = reactive({});

const ensureKeys = () => {
  props.channels.forEach((ch) => {
    const id = String(ch.id);
    if (selectedMap[id] === undefined) selectedMap[id] = false;
  });
};
ensureKeys();

const selectedIds = computed(() =>
  Object.keys(selectedMap)
    .filter((id) => selectedMap[id] === true)
    .map((id) => Number(id))
);

const selectedCount = computed(() => selectedIds.value.length);

const filteredChannels = computed(() => {
  const term = String(q.value ?? "").trim().toLowerCase();
  if (!term) return props.channels;

  return props.channels.filter((c) =>
    `${c.company_name ?? ""} ${c.channel_name ?? ""}`
      .toLowerCase()
      .includes(term)
  );
});

const groupedChannels = computed(() => {
  const map = new Map();
  for (const ch of filteredChannels.value) {
    const key = ch.company_name || "SIN EMPRESA";
    if (!map.has(key)) map.set(key, []);
    map.get(key).push(ch);
  }
  return Array.from(map.entries()).map(([company, items]) => ({ company, items }));
});

const roomPickerOpen = ref(false);
const activeChannel = ref(null);
const roomAssignments = reactive({});

const getChannelRooms = (channel) => {
  return props.rooms.filter(
    (room) =>
      Number(room.company_id) === Number(channel.company_id)
      && Number(room.communication_channel_id) === Number(channel.id)
  );
};

const hasRoomsForChannel = (channel) => getChannelRooms(channel).length > 0;

const openRoomPicker = (channel) => {
  activeChannel.value = channel;
  roomPickerOpen.value = true;
};

const selectRoomForActiveChannel = (room) => {
  if (!activeChannel.value) return;
  roomAssignments[String(activeChannel.value.id)] = Number(room.id);
  roomPickerOpen.value = false;
};

const clearRoomAssignment = (channelId) => {
  delete roomAssignments[String(channelId)];
};

const clearActiveChannelRoomAssignment = () => {
  if (!activeChannel.value) return;
  clearRoomAssignment(activeChannel.value.id);
  roomPickerOpen.value = false;
};

const getAssignedRoomName = (channel) => {
  const roomId = Number(roomAssignments[String(channel.id)] ?? 0);
  if (!roomId) return "";
  const room = getChannelRooms(channel).find((r) => Number(r.id) === roomId);
  return room?.nombre ?? "";
};

const setChecked = (id, v) => {
  const checked = v === true;
  selectedMap[String(id)] = checked;
  if (!checked) {
    clearRoomAssignment(id);
  }
};

const selectAll = () => {
  ensureKeys();
  props.channels.forEach((ch) => (selectedMap[String(ch.id)] = true));
};

const deselectAll = () => {
  Object.keys(selectedMap).forEach((id) => {
    selectedMap[id] = false;
    clearRoomAssignment(id);
  });
};

const submit = () => {
  form.channels = selectedIds.value;

  const payload = {};
  selectedIds.value.forEach((channelId) => {
    const assignedRoomId = roomAssignments[String(channelId)];
    if (assignedRoomId) {
      payload[String(channelId)] = Number(assignedRoomId);
    }
  });

  form.room_assignments = payload;
  form.post(route("users.store"), { preserveScroll: true });
};

watch(
  () => form.username,
  (value) => {
    if (dniLookupTimer) {
      clearTimeout(dniLookupTimer);
      dniLookupTimer = null;
    }

    const dni = String(value ?? "").trim();
    if (!/^\d{8}$/.test(dni)) return;
    if (dni === lastLookedUpDni) return;

    dniLookupTimer = setTimeout(async () => {
      try {
        dniLookupLoading.value = true;
        const response = await fetch(`${route("users.lookup-dni")}?dni=${encodeURIComponent(dni)}`, {
          headers: {
            Accept: "application/json",
          },
        });

        if (!response.ok) return;

        const data = await response.json();
        const firstName = String(data?.found_patient?.name ?? "").trim();
        const lastName = String(data?.found_patient?.last_name ?? "").trim();
        const fullName = `${firstName} ${lastName}`.trim();

        if (fullName) {
          form.name = fullName;
          lastLookedUpDni = dni;
        }
      } catch {
        // silent fail
      } finally {
        dniLookupLoading.value = false;
      }
    }, 450);
  }
);

onBeforeUnmount(() => {
  if (dniLookupTimer) {
    clearTimeout(dniLookupTimer);
  }
});
</script>

<template>
  <Head title="Users" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <TooltipProvider :delay-duration="100">
    <div class="w-full py-10 px-6">
      <div class="grid grid-cols-12 gap-8">
        <!-- FORM -->
        <div class="col-span-12 lg:col-span-7">
          <form @submit.prevent="submit">
            <div class="shadow-sm sm:rounded-md sm:overflow-hidden transition-colors">
              <div class="bg-card text-foreground py-6 px-4 space-y-6 sm:p-6">
                <div class="border-b border-border pb-4">
                  <h3 class="text-xl font-semibold text-foreground">User Information</h3>
                  <p class="mt-1 text-sm text-muted-foreground">
                    Create a new user, assign a role and channels.
                  </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

                  <div>
                    <label for="username" class="block text-sm font-medium text-foreground">Username</label>
                    <input
                      v-model="form.username"
                      type="text"
                      id="username"
                      class="mt-1 block w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:ring-primary focus:border-primary"
                      :class="{ 'border-red-500': form.errors.username }"
                    />
                    <p v-if="dniLookupLoading" class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
                      <Loader2 class="h-3.5 w-3.5 animate-spin" />
                      Consultando DNI...
                    </p>
                    <InputError class="mt-2" :message="form.errors.username" />
                  </div>

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

                  <div>
                    <label for="role" class="block text-sm font-medium text-foreground">Role</label>
                    <select
                      v-model="form.role"
                      id="role"
                      class="mt-1 block w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:ring-primary focus:border-primary"
                      :class="{ 'border-red-500': form.errors.role }"
                    >
                      <option value="">Seleccione</option>
                      <option v-for="role in roles" :key="role" :value="role">
                        {{ role }}
                      </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.role" />
                  </div>
                </div>

                <!-- errores de selección -->
                <InputError class="mt-2" :message="form.errors.channels" />
                <InputError class="mt-2" :message="form.errors.room_assignments" />
              </div>

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

        <!-- CHANNELS -->
        <div class="col-span-12 lg:col-span-5">
          <div class="shadow-sm sm:rounded-md sm:overflow-hidden">
            <div class="bg-card text-foreground py-6 px-4 space-y-4 sm:p-6">
              <div class="border-b border-border pb-4">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <h3 class="text-lg font-semibold">Channels</h3>
                    <p class="text-xs text-muted-foreground mt-1">
                      Seleccionados: <span class="font-medium">{{ selectedCount }}</span>
                    </p>
                  </div>

                  <div class="flex gap-2">
                    <Button type="button" variant="outline" size="sm" @click="selectAll">
                      Seleccionar todos
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="deselectAll">
                      Deseleccionar
                    </Button>
                  </div>
                </div>

                <div class="mt-3">
                  <Input
                    :value="q"
                    :model-value="q"
                    placeholder="Buscar"
                    @input="q = $event.target.value"
                    @update:model-value="q = $event"
                  />
                </div>
              </div>

              <ScrollArea class="h-[420px] rounded-md border">
                <div class="p-4">
                  <div v-if="filteredChannels.length === 0" class="text-sm text-muted-foreground">
                    No hay resultados.
                  </div>

                  <template v-for="(group, gIndex) in groupedChannels" :key="group.company">
                    <div class="text-xs font-semibold text-muted-foreground uppercase mb-2">
                      {{ group.company }}
                    </div>

                    <template v-for="(ch, idx) in group.items" :key="ch.id">
                      <!-- item -->
                      <div class="rounded-md px-2 py-2 hover:bg-muted">
                        <div class="flex items-center gap-3">
                          <Checkbox
                            :id="`ch-${ch.id}`"
                            :checked="selectedMap[String(ch.id)] === true"
                            :model-value="selectedMap[String(ch.id)] === true"
                            @update:checked="(v) => setChecked(ch.id, v)"
                            @update:model-value="(v) => setChecked(ch.id, v)"
                          />
                          <label :for="`ch-${ch.id}`" class="text-sm leading-tight cursor-pointer select-none flex-1">
                            {{ ch.channel_name }}
                          </label>

                          <Button
                            v-if="selectedMap[String(ch.id)] === true && hasRoomsForChannel(ch)"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="openRoomPicker(ch)"
                          >
                            {{ getAssignedRoomName(ch) ? 'Cambiar room' : 'Asignar room' }}
                          </Button>
                        </div>

                        <div
                          v-if="selectedMap[String(ch.id)] === true && hasRoomsForChannel(ch) && getAssignedRoomName(ch)"
                          class="pl-8 pt-1 text-xs text-muted-foreground flex items-center gap-2"
                        >
                          <span>
                            Room: <span class="font-medium text-foreground">{{ getAssignedRoomName(ch) }}</span>
                          </span>
                          <Tooltip>
                            <TooltipTrigger as-child>
                              <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-md p-1 hover:bg-muted-foreground/10"
                                aria-label="Eliminar"
                                @click="clearRoomAssignment(ch.id)"
                              >
                                <Trash2 class="h-4 w-4 text-destructive" />
                              </button>
                            </TooltipTrigger>
                            <TooltipContent>
                              <p>Eliminar</p>
                            </TooltipContent>
                          </Tooltip>
                        </div>
                      </div>
                    </template>

                    <Separator v-if="gIndex !== groupedChannels.length - 1" class="my-4" />
                  </template>

                </div>
              </ScrollArea>
            </div>
          </div>
        </div>

      </div>
    </div>
    <Dialog v-model:open="roomPickerOpen">
      <DialogContent class="sm:max-w-[460px]">
        <DialogHeader>
          <DialogTitle>Seleccionar room</DialogTitle>
          <DialogDescription>
            Elija 1 room para el channel {{ activeChannel?.channel_name ?? '' }}.
          </DialogDescription>
        </DialogHeader>

        <div class="max-h-80 overflow-y-auto space-y-2 py-1">
          <button
            v-for="room in (activeChannel ? getChannelRooms(activeChannel) : [])"
            :key="room.id"
            type="button"
            class="w-full rounded-md border px-3 py-2 text-left text-sm hover:bg-muted"
            :class="Number(roomAssignments[String(activeChannel?.id ?? '')] ?? 0) === Number(room.id) ? 'border-primary bg-muted' : 'border-border'"
            @click="selectRoomForActiveChannel(room)"
          >
            {{ room.nombre }}
          </button>

          <div v-if="activeChannel && getChannelRooms(activeChannel).length === 0" class="text-sm text-muted-foreground">
            No hay rooms disponibles para este channel.
          </div>
        </div>

        <DialogFooter>
          <Tooltip v-if="activeChannel && Number(roomAssignments[String(activeChannel.id)] ?? 0) > 0">
            <TooltipTrigger as-child>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Eliminar"
                @click="clearActiveChannelRoomAssignment"
              >
                <Trash2 class="h-4 w-4 text-destructive" />
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              <p>Eliminar</p>
            </TooltipContent>
          </Tooltip>
          <Button type="button" variant="outline" @click="roomPickerOpen = false">Cancelar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
    </TooltipProvider>
  </AppLayout>
</template>