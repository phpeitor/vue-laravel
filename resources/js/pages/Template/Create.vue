<script setup>
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { Bell, Check } from 'lucide-vue-next';
import InputError from "@/components/InputError.vue";
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'

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
                    Nueva plantilla de comunicación
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
                      <option value="marketing">Marketing</option>
                      <option value="utilidad">Utilidad</option>
                      <option value="autenticacion">Autenticación</option>
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
                      <option value="es">Español</option>
                      <option value="en">Ingles</option>
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
                      <option value="multimedia">Multimedia</option>
                      <option value="carrusel">Carrusel</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.tipo" />
                  </div>

                  <div class="col-span-4 sm:col-span-3">
                    <Card>
                        <CardHeader>
                          <CardTitle>Edición de Plantilla</CardTitle>
                        </CardHeader>
                        <CardContent class="grid gap-4">
                          <div class=" flex items-center space-x-4 rounded-md border p-4">
                            <Bell />
                            <div class="flex-1 space-y-1">
                              <p class="text-sm font-medium leading-none">
                                Encabezado
                              </p>
                              <p class="text-sm text-muted-foreground">
                                Añade un título o elige qué tipo de contenido usarás para este encabezado
                              </p>
                            </div>
                            <Switch />
                          </div>

                          <div class="flex gap-2">
                            <select
                              v-model="form.tipo_cabecera"
                              id="tipo_cabecera"
                              class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                              :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.tipo_cabecera }"
                            >
                              <option value="">Ninguno</option>
                              <option value="texto">Texto</option>
                              <option value="multimedia">Multimedia</option>
                            </select>

                            <input
                                v-model="form.texto_encabezado"
                                type="text"
                                id="texto_encabezado"
                                class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                                :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.texto_encabezado }"
                              />
                          </div>

                          <div class="flex">
                              <RadioGroup default-value="opt-imagen" class="flex gap-4">
                                <div class="flex items-center space-x-2">
                                  <RadioGroupItem id="opt-imagen" value="opt-imagen" />
                                  <Label for="opt-imagen">Imágen</Label>
                                </div>
                                <div class="flex items-center space-x-2">
                                  <RadioGroupItem id="option-video" value="option-video" />
                                  <Label for="option-video">Video</Label>
                                </div>
                                <div class="flex items-center space-x-2">
                                  <RadioGroupItem id="option-documento" value="option-documento" />
                                  <Label for="option-documento">Documento</Label>
                                </div>
                              </RadioGroup>
                          </div>

                          <div class="flex-1 space-y-1">
                            <Input id="picture" type="file" />
                          </div>

                          <div class="flex-1 space-y-1">
                              <p class="text-sm font-medium leading-none">
                                Cuerpo
                              </p>
                              <p class="text-sm text-muted-foreground">
                                Introduce el texto de tu mensaje en el idioma que has seleccionado
                              </p>
                              <Textarea
                                class="resize-none"
                                v-bind="componentField"
                              />
                          </div>
                        </CardContent>
                        <CardFooter>
                            <div class="flex-1 space-y-1">
                              <p class="text-sm font-medium leading-none">
                                Pié de página
                              </p>
                              <p class="text-sm text-muted-foreground">
                                Añade una breve línea de texto en la parte inferior de tu plantilla de mensaje
                              </p>

                              <input
                                v-model="form.pie_pagina"
                                type="text"
                                id="pie_pagina"
                                class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                                :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.pie_pagina }"
                              />
                              <InputError class="mt-2" :message="form.errors.pie_pagina" />
                            </div>
                        </CardFooter>
                    </Card>
                  </div>


                   <div class="col-span-4 sm:col-span-3">
                    <Card>
                        <CardHeader>
                          <CardTitle>Previsualización del Mensaje</CardTitle>
                          <CardDescription>
                            Vista previa del mensaje configurado a enviar
                          </CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-4">


                        </CardContent>
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
