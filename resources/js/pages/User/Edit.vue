<script setup>
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import InputError from "@/components/InputError.vue";

import { ref, computed, reactive } from "vue";

import { ScrollArea } from "@/components/ui/scroll-area";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";

const breadcrumbs = [
  { title: "Users", href: "/users" },
  { title: "Editar usuario", href: "/users/edit" },
];

const props = defineProps({
  roles: { type: Array, default: () => [] },
  currentRole: { type: String, default: "" },

  channels: { type: Array, default: () => [] },           // <- todos los canales
  selectedChannels: { type: Array, default: () => [] },   // <- ids asignados
});

const user = usePage().props.user;

const form = useForm({
  name: user.data.name,
  username: user.data.username,
  email: user.data.email,
  password: "",
  estado: user.data.estado == 1,
  role: props.currentRole,

  channels: [], // lo llenamos antes del submit
});

const q = ref("");

// mapa id => boolean
const selectedMap = reactive({});

// init keys + seleccionados
props.channels.forEach((ch) => {
  const id = String(ch.id);
  if (selectedMap[id] === undefined) selectedMap[id] = false;
});
props.selectedChannels.forEach((id) => {
  selectedMap[String(id)] = true;
});

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
    `${c.company_name ?? ""} ${c.channel_name ?? ""}`.toLowerCase().includes(term)
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

const setChecked = (id, v) => {
  selectedMap[String(id)] = v === true;
};

const selectAll = () => {
  props.channels.forEach((ch) => (selectedMap[String(ch.id)] = true));
};

const deselectAll = () => {
  Object.keys(selectedMap).forEach((id) => (selectedMap[id] = false));
};

const submit = () => {
  form.estado = form.estado ? 1 : 0;
  form.channels = selectedIds.value; // ✅ manda canales

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
              <div class="bg-card text-foreground py-6 px-6 space-y-6">
                <div class="flex items-center justify-between border-b border-border pb-4">
                  <div>
                    <h3 class="text-xl font-semibold text-foreground">User Information</h3>
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
                    <span class="text-sm font-medium" :class="form.estado ? 'text-green-600' : 'text-red-600'">
                      {{ form.estado ? "Activo" : "Inactivo" }}
                    </span>
                  </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

                  <div>
                    <label class="block text-sm font-medium text-foreground">Username</label>
                    <input
                      v-model="form.username"
                      type="text"
                      class="mt-1 block w-full rounded-md border border-border bg-background px-3 py-2"
                      :class="{ 'border-red-500': form.errors.username }"
                    />
                    <InputError class="mt-2" :message="form.errors.username" />
                  </div>

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

                <InputError class="mt-2" :message="form.errors.channels" />
              </div>

              <div class="flex justify-end gap-3 border-t border-border bg-muted px-6 py-4">
                <Link
                  :href="route('users.index')"
                  class="px-4 py-2 text-sm rounded-md border border-border text-foreground hover:bg-muted/80"
                >
                  Cancel
                </Link>

                <button type="submit" class="px-4 py-2 text-sm rounded-md bg-primary text-primary-foreground hover:bg-primary/90">
                  Update
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
                      <div class="flex items-center gap-3 rounded-md px-2 py-2 hover:bg-muted">
                        <Checkbox
                          :id="`ch-${ch.id}`"
                          :checked="selectedMap[String(ch.id)] === true"
                          :model-value="selectedMap[String(ch.id)] === true"
                          @update:checked="(v) => setChecked(ch.id, v)"
                          @update:model-value="(v) => setChecked(ch.id, v)"
                        />
                        <label :for="`ch-${ch.id}`" class="text-sm leading-tight cursor-pointer select-none">
                          {{ ch.channel_name }}
                        </label>
                      </div>

                      <Separator v-if="idx !== group.items.length - 1" class="my-2" />
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
  </AppLayout>
</template>