<script setup>
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { Bell, Check } from 'lucide-vue-next';
import InputError from "@/components/InputError.vue";
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { AspectRatio } from '@/components/ui/aspect-ratio'
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
import EmojiPicker from 'vue3-emoji-picker'
import 'vue3-emoji-picker/css'
import { ref, nextTick, watch, computed  } from 'vue'

const queryParams = new URLSearchParams(window.location.search)
const companyId = Number(queryParams.get('companyId'))
const communicationChannelId = Number(queryParams.get('communicationChannelId'))

const showEmojiPicker = ref(false)
const textareaEl = ref(null)

const toggleEmojiPicker = () => {
  showEmojiPicker.value = !showEmojiPicker.value
}

const addEmoji = (emoji) => {
  const emojiChar = emoji.i

  const el = textareaEl.value?.textareaRef

  if (!el) return

  const pos = el.selectionStart ?? form.cuerpo.length
  const start = form.cuerpo.slice(0, pos)
  const end = form.cuerpo.slice(pos)

  form.cuerpo = `${start}${emojiChar}${end}`

  nextTick(() => {
    el.focus()
    el.selectionStart = el.selectionEnd = pos + emojiChar.length
  })

  showEmojiPicker.value = false
}

const showHeader = ref(false)

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
  nombre: "",
  idioma: "",
  tipo: "",
  categoria: "",
  tipo_cabecera: "",
  texto_encabezado: "",
  tipo_multimedia: "", 
  cuerpo: "", 
  pie_pagina: ""
})

const acceptFileType = computed(() => {
  switch (form.tipo_multimedia) {
    case 'imagen':
      return 'image/*'
    case 'video':
      return 'video/mp4'
    case 'documento':
      return 'application/pdf'
    default:
      return ''
  }
})

watch(() => form.tipo_cabecera, (value) => {
  if (value === 'texto') {
    form.tipo_multimedia = ''
  } else if (value === 'multimedia') {
    form.texto_encabezado = ''
  } else {
    form.texto_encabezado = ''
    form.tipo_multimedia = ''
  }
})

watch(() => form.cuerpo, (text) => {
   normalizeVariables()
})

const submit = () => {
  form.post(route("templates.store") + `?companyId=${companyId}&communicationChannelId=${communicationChannelId}`, {
    preserveScroll: true,
  })
};

const horaActual = ref('')

const actualizarHora = () => {
  const ahora = new Date()
  const horas = ahora.getHours().toString().padStart(2, '0')
  const minutos = ahora.getMinutes().toString().padStart(2, '0')
  horaActual.value = `${horas}:${minutos}`
}

const formatWhatsappText = (text) => {
  if (!text) return '';

  return text
    .replace(/\*(.*?)\*/g, '<strong>$1</strong>')     
    .replace(/_(.*?)_/g, '<em>$1</em>')                
    .replace(/~(.*?)~/g, '<s>$1</s>');                 
}

const normalizeVariables = () => {
  const el = textareaEl.value?.textareaRef
  if (!el) return

  const text = form.cuerpo
  const matches = [...text.matchAll(/{{\d+}}/g)]
  if (!matches.length) return

  const cursorStart = el.selectionStart
  const cursorEnd = el.selectionEnd

  let index = 1
  let replaced = text
  const seen = new Set()

  for (const match of matches) {
    const original = match[0]
    if (seen.has(original)) continue
    replaced = replaced.replaceAll(original, `{{${index}}}`)
    seen.add(original)
    index++
  }

  if (replaced !== text) {
    form.cuerpo = replaced

    nextTick(() => {
      if (el) {
        el.focus()
        el.selectionStart = cursorStart
        el.selectionEnd = cursorEnd
      }
    })
  }
}

const addVariable = () => {
  const el = textareaEl.value?.textareaRef
  if (!el) return

  const matches = [...form.cuerpo.matchAll(/{{(\d+)}}/g)]
  const used = matches.map(m => parseInt(m[1]))
  const sorted = used.sort((a, b) => a - b)

  let next = 1
  for (const num of sorted) {
    if (num !== next) break
    next++
  }

  const token = `{{${next}}}`
  const pos = el.selectionStart ?? form.cuerpo.length
  const start = form.cuerpo.slice(0, pos)
  const end = form.cuerpo.slice(pos)

  form.cuerpo = `${start}${token}${end}`

  nextTick(() => {
    el.focus()
    el.selectionStart = el.selectionEnd = pos + token.length
  })
}

const onNombreInput = (e) => {
  form.nombre = e.target.value
    .toLowerCase()             
    .replace(/[^a-z0-9_]/g, '')
}

actualizarHora()
setInterval(actualizarHora, 60000)
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
                      <option value="MARKETING">Marketing</option>
                      <option value="UTILITY">Utilidad</option>
                      <option value="AUTHENTICATION">Autenticación</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.categoria" />
                  </div>

                  <div class="col-span-4 sm:col-span-3">
                    <label for="nombre" class="block text-sm font-medium text-foreground">Nombre</label>
                    <input
                      v-model="form.nombre"
                      @input="onNombreInput"
                      type="text"
                      id="nombre"
                      :maxlength="100"
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
                            <Switch v-model="showHeader" />
                          </div>

                          <div v-if="showHeader" class="flex gap-2">
                            <select
                              v-model="form.tipo_cabecera"
                              id="tipo_cabecera"
                              class="block border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                              :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.tipo_cabecera }"
                            >
                              <option value="">Ninguno</option>
                              <option value="texto">Texto</option>
                              <option value="multimedia">Multimedia</option>
                            </select>

                            <input
                              v-if="form.tipo_cabecera === 'texto'"
                              v-model="form.texto_encabezado"
                              type="text"
                              :maxlength="60"
                              id="texto_encabezado"
                              class="block border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors flex-1"
                              :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.texto_encabezado }"
                            />
                          </div>

                          <div v-if="showHeader && form.tipo_cabecera === 'multimedia'" class="mt-2 space-y-2">
                              <RadioGroup v-model="form.tipo_multimedia" class="flex gap-4">
                                <div class="flex items-center space-x-2">
                                  <RadioGroupItem id="option-imagen" value="imagen" />
                                  <Label for="option-imagen">Imágen</Label>
                                </div>
                                <div class="flex items-center space-x-2">
                                  <RadioGroupItem id="option-video" value="video" />
                                  <Label for="option-video">Video</Label>
                                </div>
                                <div class="flex items-center space-x-2">
                                  <RadioGroupItem id="option-documento" value="documento" />
                                  <Label for="option-documento">Documento</Label>
                                </div>
                              </RadioGroup>

                              <Input
                                id="fichero"
                                type="file"
                                :accept="acceptFileType"
                                :disabled="!form.tipo_multimedia"
                                class="w-full disabled:opacity-50 disabled:cursor-not-allowed"
                              />
                          </div>

                          <div class="flex-1 space-y-1">
                              <p class="text-sm font-medium leading-none">
                                Cuerpo
                              </p>
                              <p class="text-sm text-muted-foreground">
                                Introduce el texto de tu mensaje en el idioma que has seleccionado
                              </p>

                              <div class="flex flex-col gap-2 relative">
                                <Textarea
                                  v-model="form.cuerpo"
                                  :maxlength="1024"
                                  ref="textareaEl"
                                  class="min-h-[10rem] resize-none"
                                  placeholder="Escribe tu mensaje aquí"
                                />
                                <InputError class="mt-2" :message="form.errors.cuerpo" />

                                <button
                                  type="button"
                                  @click="addVariable"
                                  class="absolute bottom-12 right-2 bg-muted rounded px-2 py-1 text-xs hover:bg-muted/70"
                                  title="Añadir variable"
                                >
                                  + variable
                                </button>

                                <button
                                  type="button"
                                  @click="toggleEmojiPicker"
                                  class="absolute bottom-2 right-2 bg-muted rounded-full p-1 hover:bg-muted/70"
                                  title="Insertar emoji"
                                >
                                  😊
                                </button>

                                <div
                                  v-if="showEmojiPicker"
                                  class="absolute bottom-14 right-0 z-50"
                                >
                                  
                                  <EmojiPicker @select="addEmoji" :native="true" />
                                </div>

                              </div>
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
                                :maxlength="60"
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
                        
                        <CardContent class="flex justify-start">
                          <div class="relative w-[450px]">
                            <div
                              class="rounded-md bg-cover bg-center w-full min-h-[200px] bg-no-repeat p-4"
                              :style="{ backgroundImage: 'url(/img/template.jpeg)' }"
                            >
                              <div
                                class="bg-white rounded-xl shadow-md p-4 w-[90%] sm:w-[75%] break-words text-sm relative"
                                style="color: black;"
                              >
                                <div v-if="showHeader && form.tipo_cabecera === 'texto'" class="font-bold mb-1">
                                  {{ form.texto_encabezado }}
                                </div>

                                <div class="whitespace-pre-wrap" v-html="formatWhatsappText(form.cuerpo)">
                                 
                                </div>

                                <div v-if="form.pie_pagina" class="mt-2 text-xs text-gray-500">
                                  {{ form.pie_pagina }}
                                </div>

                                <div class="mt-2 text-xs text-gray-400 text-right">
                                  {{ horaActual }}
                                </div>
                              </div>
                            </div>
                          </div>
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
