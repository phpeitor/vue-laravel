<script setup>
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { Bell, Check } from 'lucide-vue-next';
import InputError from "@/components/InputError.vue";
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'

const notifications = [
  {
    title: 'Your call has been confirmed.',
    description: '1 hour ago',
  },
  {
    title: 'You have a new message!',
    description: '1 hour ago',
  },
  {
    title: 'Your subscription is expiring soon!',
    description: '2 hours ago',
  },
]

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
    form.post(route("templates.store"), {
        preserveScroll: true,
    });
};
</script>

<template>
  <Head title="Templates" />

  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <div class="space-y-6 sm:px-6 lg:px-0 lg:col-span-12">
          <form @submit.prevent="submit">
            <div class="shadow-sm sm:rounded-md sm:overflow-hidden transition-colors">
              <div class="bg-card text-foreground py-6 px-4 space-y-6 sm:p-6">
                <div>
                  <h3 class="text-lg leading-6 font-medium text-foreground">Template Information</h3>
                  <p class="mt-1 text-sm text-muted-foreground">
                    Use this form to create a new template.
                  </p>
                </div>

                <div class="grid grid-cols-6 gap-6">

                  <div class="col-span-4 sm:col-span-3">
                    <label for="categoria" class="block text-sm font-medium text-foreground">Categoría</label>
                    <select
                      v-model="form.categoria"
                      id="categoria"
                      class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                      :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.categoria }"
                    >
                      <option value="">Seleccione categoría</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.categoria" />
                  </div>

                  <div class="col-span-4 sm:col-span-3">
                    <label for="nombre" class="block text-sm font-medium text-foreground">Nombre</label>
                    <input
                      v-model="form.nombre"
                      type="text"
                      id="nombre"
                      class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                      :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.nombre }"
                    />
                    <InputError class="mt-2" :message="form.errors.nombre" />
                  </div>

                  <div class="col-span-4 sm:col-span-3">
                    <label for="idioma" class="block text-sm font-medium text-foreground">Idioma</label>
                    <select
                      v-model="form.idioma"
                      id="idioma"
                      class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                      :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.idioma }"
                    >
                      <option value="">Seleccione idioma</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.idioma" />
                  </div>

                  <div class="col-span-4 sm:col-span-3">
                    <label for="tipo" class="block text-sm font-medium text-foreground">Tipo</label>
                    <select
                      v-model="form.tipo"
                      id="tipo"
                      class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                      :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.tipo }"
                    >
                      <option value="">Seleccione tipo</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.tipo" />
                  </div>

                  <div class="col-span-4 sm:col-span-3">
                    <Card>
                        <CardHeader>
                          <CardTitle>Notifications</CardTitle>
                          <CardDescription>You have 3 unread messages.</CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-4">
                          <div class=" flex items-center space-x-4 rounded-md border p-4">
                            <Bell />
                            <div class="flex-1 space-y-1">
                              <p class="text-sm font-medium leading-none">
                                Push Notifications
                              </p>
                              <p class="text-sm text-muted-foreground">
                                Send notifications to device.
                              </p>
                            </div>
                            <Switch />
                          </div>
                          <div>
                            <div
                              v-for="(notification, index) in notifications" :key="index"
                              class="mb-4 grid grid-cols-[25px_minmax(0,1fr)] items-start pb-4 last:mb-0 last:pb-0"
                            >
                              <span class="flex h-2 w-2 translate-y-1 rounded-full bg-sky-500" />
                              <div class="space-y-1">
                                <p class="text-sm font-medium leading-none">
                                  {{ notification.title }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                  {{ notification.description }}
                                </p>
                              </div>
                            </div>
                          </div>
                        </CardContent>
                        <CardFooter>
                          <Button class="w-full">
                            <Check class="mr-2 h-4 w-4" /> Mark all as read
                          </Button>
                        </CardFooter>
                    </Card>
                  </div>

                </div>
              </div>

              <div class="px-4 py-3 bg-muted text-right sm:px-6">
                <Link
                  :href="route('templates.index')"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-primary bg-muted hover:bg-muted/80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary mr-4 transition-colors">Cancel </Link>

                <button type="submit" class="bg-primary text-primary-foreground hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors px-4 py-2 rounded-md text-sm font-medium"
                > Enviar </button>

              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
