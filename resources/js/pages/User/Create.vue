<script setup>
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import InputError from "@/components/InputError.vue";

import { ref, computed, reactive, watchEffect } from "vue";

import { ScrollArea } from "@/components/ui/scroll-area";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";

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
});

const form = useForm({
  name: "",
  username: "",
  email: "",
  password: "",
  role: "",
  channels: [],
});

const q = ref("");
// Mapa reactivo id->boolean
const selectedMap = reactive({});

// Inicializa keys (una vez que llegan channels)
const ensureKeys = () => {
  props.channels.forEach((ch) => {
    const id = String(ch.id);
    if (selectedMap[id] === undefined) selectedMap[id] = false;
  });
};
ensureKeys();

// ids seleccionados (siempre derivado del mapa)
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

const setChecked = (id, v) => {
  selectedMap[String(id)] = v === true;
};

const selectAll = () => {
  ensureKeys();
  props.channels.forEach((ch) => (selectedMap[String(ch.id)] = true));
};

const deselectAll = () => {
  Object.keys(selectedMap).forEach((id) => (selectedMap[id] = false));
};

// IMPORTANTE: antes de enviar, copia selectedIds a form.channels
const submit = () => {
  form.channels = selectedIds.value;
  form.post(route("users.store"), { preserveScroll: true });
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

                <!-- error general de channels si lo fuerzas a required -->
                <InputError class="mt-2" :message="form.errors.channels" />
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
                  <!-- ✅ NO v-model: usamos value + input -->
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