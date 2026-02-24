<script setup >
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from "@inertiajs/vue3";
import { Bell, Check, PhoneOutgoing, Link2, SquarePlus, Trash2 } from 'lucide-vue-next';
import InputError from "@/components/InputError.vue";
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { AspectRatio } from '@/components/ui/aspect-ratio'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger
} from '@/components/ui/tooltip'
import { Switch } from '@/components/ui/switch'
import EmojiPicker from 'vue3-emoji-picker'
import { ref, nextTick, watch, computed  } from 'vue'
import { useWhatsappFormatter } from '@/composables/useWhatsappFormatter'

const breadcrumbs = [
  {
    title: 'Templates',
    href: '/templates',
  },
  {
    title: 'Nuevo template',
    href: '/templates/create',
  },
]

const { formatWhatsappText } = useWhatsappFormatter()
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
  pie_pagina: "",
  header_file: null,
  botones: [],
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

const inputFile = ref(null)

watch(() => form.tipo_multimedia, () => {
  headerFile.value = null
  headerPreviewUrl.value = ''

  nextTick(() => {
    setTimeout(() => {
      const input = inputFile.value?.input || inputFile.value?.$el || inputFile.value

      if (input && input instanceof HTMLInputElement) {
        input.value = ''
      } else if (input?.querySelector) {
        const fileInput = input.querySelector('input[type="file"]')
        if (fileInput) fileInput.value = ''
      }
    }, 10) 
  })
})

watch(() => form.tipo_cabecera, (value) => {
  if (value === 'texto') {
    form.tipo_multimedia = ''
    headerFile.value = null
    headerPreviewUrl.value = ''
  } else if (value === 'multimedia') {
    form.texto_encabezado = ''
  } else {
    form.texto_encabezado = ''
    form.tipo_multimedia = ''
    headerFile.value = null
    headerPreviewUrl.value = ''
  }
})

watch(() => form.cuerpo, (text) => {
   normalizeVariables()
})

const submit = () => {
  form.post(route("templates.store") + `?companyId=${companyId}&communicationChannelId=${communicationChannelId}`, {
    preserveScroll: true,
    forceFormData: true,
    onBefore: () => {
      if (form.tipo_cabecera === 'multimedia' && !form.header_file) {
        form.errors.header_file = 'Debes seleccionar un archivo multimedia'
        return false
      }
      if (!validateButtonsRequired()) {
        return false
      }
      headerPreviewUrl.value = ''
    }
  })
}

const horaActual = ref('')

const actualizarHora = () => {
  const ahora = new Date()
  const horas = ahora.getHours().toString().padStart(2, '0')
  const minutos = ahora.getMinutes().toString().padStart(2, '0')
  horaActual.value = `${horas}:${minutos}`
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

const headerFile = ref(null)
const headerPreviewUrl = ref('')

const onFileChange = (e) => {
  const file = e.target.files[0]
  if (!file) {
    headerFile.value = null
    headerPreviewUrl.value = ''
    form.header_file = null
    return
  }

  headerFile.value = file
  form.header_file = file 
  headerPreviewUrl.value = URL.createObjectURL(file)
}

const MAX_TEXT = 5
const MAX_URL = 2
const MAX_PHONE = 1
let _btnUid = 1

const countText = computed(() => form.botones.filter(b => b.kind === 'BOTON').length)
const countUrl  = computed(() => form.botones.filter(b => b.kind === 'URL').length)
const countPhone = computed(() => form.botones.filter(b => b.kind === 'TELEFONO').length)

function setButtonsError(msg) {
  form.errors = { ...form.errors, botones: msg }
  setTimeout(() => { if (form.errors.botones === msg) form.errors.botones = '' }, 2000)
}

function addButton(kind) {
  if (kind === 'BOTON' && countText.value >= MAX_TEXT) {
    setButtonsError('Máximo 5 botones de texto')
    return
  }
  if (kind === 'URL' && countUrl.value >= MAX_URL) {
    setButtonsError('Máximo 2 botones con URL')
    return
  }
  if (kind === 'TELEFONO' && countPhone.value >= MAX_PHONE) {
    setButtonsError('Máximo 1 botón de teléfono')
    return
  }

  const base = { id: _btnUid++, kind, text: '', _errors: { text: '', url: '', phone: '' } }
  if (kind === 'URL') {
    form.botones.push({ ...base, url: '' })
  } else if (kind === 'TELEFONO') {
    form.botones.push({ ...base, phone: '' })
  } else {
    form.botones.push(base)
  }
}

function removeButton(index) {
  form.botones.splice(index, 1)
}

function onPhoneInput(e, btn) {
  const raw = e.target.value || ''
  btn.phone = raw.replace(/\D+/g, '').slice(0, 11)
  if (btn._errors?.phone) btn._errors.phone = ''
}

function onTextInput(btn) {
   if (btn._errors?.text) btn._errors.text = ''
}

function onUrlInput(btn) {
   if (btn._errors?.url) btn._errors.url = ''
}

function isValidUrl(u) {
   try {
     const x = new URL(u)
     return x.protocol === 'http:' || x.protocol === 'https:'
   } catch { return false }
}

function validateButtonsRequired() {
   let ok = true
   // limpiar errores previos
   form.botones.forEach(b => b._errors = { text: '', url: '', phone: '' })

   for (const b of form.botones) {
     // texto requerido
     if (!b.text?.trim()) {
       b._errors.text = 'Texto requerido'
       ok = false
     }
     if (b.kind === 'URL') {
       if (!b.url?.trim()) {
         b._errors.url = 'URL requerida'
         ok = false
       } else if (b.url.length > 255 || !isValidUrl(b.url)) {
         b._errors.url = 'URL inválida (http/https, máx. 255)'
         ok = false
       }
     }
     if (b.kind === 'TELEFONO') {
       if (!b.phone?.trim()) {
         b._errors.phone = 'Teléfono requerido'
         ok = false
       } else if (b.phone.length !== 11) {
         b._errors.phone = 'Debe tener 11 dígitos'
         ok = false
       }
     }
   }
   if (!ok) {
     setButtonsError('Completa los campos requeridos de los botones.')
   }
   return ok
}

actualizarHora()
setInterval(actualizarHora, 60000)
</script>

<template>
  <Head title="Templates" />

<AppLayout :breadcrumbs="breadcrumbs">
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
                                ref="inputFile"
                                type="file"
                                :accept="acceptFileType"
                                :disabled="!form.tipo_multimedia"
                                :key="form.tipo_multimedia"
                                @change="onFileChange"
                              />
                              <InputError class="mt-2" :message="form.errors.header_file" />
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

                                <TooltipProvider :delayDuration="200">
                                  <Tooltip>
                                    <TooltipTrigger as-child>
                                      <button
                                        type="button"
                                        @click="addVariable"
                                        class="absolute bottom-12 right-2 bg-muted rounded px-2 py-1 text-xs hover:bg-muted/70"
                                        aria-label="Añadir variable"
                                      >
                                        + variable
                                      </button>
                                    </TooltipTrigger>
                                    <TooltipContent side="top" align="end" :sideOffset="6">
                                      Añadir variable
                                    </TooltipContent>
                                  </Tooltip>
                                </TooltipProvider>

                                <TooltipProvider :delayDuration="200">
                                  <Tooltip>
                                    <TooltipTrigger as-child>
                                      <button
                                        type="button"
                                        @click="toggleEmojiPicker"
                                        class="absolute bottom-2 right-2 bg-muted rounded-full p-1 hover:bg-muted/70"
                                        aria-label="Insertar emoji"
                                      >
                                        😊
                                      </button>
                                    </TooltipTrigger>
                                    <TooltipContent side="top" align="end" :sideOffset="6">
                                      Insertar emoji
                                    </TooltipContent>
                                  </Tooltip>
                                </TooltipProvider>


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
                          <div class="flex flex-col w-full space-y-4">
                            <!-- Sección pie de página -->
                            <div class="space-y-1">
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

                            <div class="space-y-1">
                              <p class="text-sm font-medium leading-none">Botones</p>
                              <p class="text-sm text-muted-foreground">Añade uno o más botones a tu plantilla</p>

                              <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                  <TooltipProvider :delayDuration="200">
                                    <ToggleGroup type="multiple" variant="outline">
                                      <!-- Texto -->
                                      <Tooltip>
                                        <TooltipTrigger as-child>
                                          <ToggleGroupItem value="toggle_boton" aria-label="Agregar botón de texto" @click="addButton('BOTON')">
                                            <SquarePlus class="h-4 w-4" />
                                          </ToggleGroupItem>
                                        </TooltipTrigger>
                                        <TooltipContent side="top" align="center">
                                          Agregar botón texto
                                        </TooltipContent>
                                      </Tooltip>

                                      <!-- URL -->
                                      <Tooltip>
                                        <TooltipTrigger as-child>
                                          <ToggleGroupItem value="toggle_url" aria-label="Agregar botón con URL" @click="addButton('URL')">
                                            <Link2 class="h-4 w-4" />
                                          </ToggleGroupItem>
                                        </TooltipTrigger>
                                        <TooltipContent side="top" align="center">
                                          Agregar botón URL
                                        </TooltipContent>
                                      </Tooltip>

                                      <!-- Teléfono -->
                                      <Tooltip>
                                        <TooltipTrigger as-child>
                                          <ToggleGroupItem value="toggle_telefono" aria-label="Agregar botón de teléfono" @click="addButton('TELEFONO')">
                                            <PhoneOutgoing class="h-4 w-4" />
                                          </ToggleGroupItem>
                                        </TooltipTrigger>
                                        <TooltipContent side="top" align="center">
                                          Agregar botón teléfono
                                        </TooltipContent>
                                      </Tooltip>
                                    </ToggleGroup>
                                  </TooltipProvider>

                                  <!-- Contadores / límites -->
                                  <span class="text-xs text-muted-foreground">
                                    Texto: {{ countText }}/5 · URL: {{ countUrl }}/2 · Tel: {{ countPhone }}/1
                                  </span>
                                </div>

                                <!-- Lista de botones agregados -->
                                <div class="space-y-3">
                                  <div
                                    v-for="(btn, idx) in form.botones"
                                    :key="btn.id"
                                    class="rounded-lg border border-border p-3 space-y-2"
                                  >
                                    <div class="flex items-center justify-between">
                                      <span class="text-xs uppercase tracking-wide text-muted-foreground">
                                        {{ btn.kind }}
                                      </span>
                                      <button
                                        type="button"
                                        class="p-1 rounded hover:bg-muted"
                                        @click="removeButton(idx)"
                                        :aria-label="`Eliminar botón ${idx+1}`"
                                        title="Eliminar"
                                      >
                                        <Trash2 class="h-4 w-4 text-red-600" />
                                      </button>
                                    </div>

                                    <!-- Campo: Texto (todas las variantes) -->
                                    <input
                                      v-model="btn.text"
                                      @input="onTextInput(btn)"
                                      type="text"
                                      :maxlength="25"
                                      placeholder="Texto (máx. 25)"
                                      required
                                      class="block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                                    />
                                    <p v-if="btn._errors?.text" class="text-xs text-red-600">{{ btn._errors.text }}</p>

                                    <!-- Campos adicionales según tipo -->
                                    <template v-if="btn.kind === 'URL'">
                                      <input
                                        v-model="btn.url"
                                        @input="onUrlInput(btn)"
                                        type="url"
                                        :maxlength="255"
                                        placeholder="https://talina.xyz"
                                        required
                                        class="block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                                      />
                                      <p v-if="btn._errors?.url" class="text-xs text-red-600">{{ btn._errors.url }}</p>
                                    </template>

                                    <template v-else-if="btn.kind === 'TELEFONO'">
                                      <input
                                        :value="btn.phone"
                                        @input="onPhoneInput($event, btn)"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        placeholder="Número (11 dígitos)"
                                        required
                                        class="block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                                      />
                                      <p v-if="btn._errors?.phone" class="text-xs text-red-600">{{ btn._errors.phone }}</p>
                                    </template>
                                  </div>
                                </div>
                              </div>

                              <InputError class="mt-2" :message="form.errors?.botones" />
                            </div>

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
                                <!-- CABECERA: Texto -->
                                <div v-if="showHeader && form.tipo_cabecera === 'texto'" class="font-bold mb-2">
                                  {{ form.texto_encabezado }}
                                </div>

                                <!-- CABECERA: Multimedia -->
                                <div v-if="showHeader && form.tipo_cabecera === 'multimedia'" class="mb-2">
                                  <div v-if="form.tipo_multimedia === 'imagen' && headerPreviewUrl">
                                    <img :src="headerPreviewUrl" alt="Imagen de encabezado" class="w-full rounded-md mb-2" />
                                  </div>
                                  <div v-else-if="form.tipo_multimedia === 'video' && headerPreviewUrl">
                                    <video :src="headerPreviewUrl" controls class="w-full rounded-md mb-2"></video>
                                  </div>
                                  <div v-else-if="form.tipo_multimedia === 'documento' && headerPreviewUrl" class="flex items-center gap-2 text-sm text-gray-600">
                                    📄 Documento cargado: {{ headerFile?.name }}
                                  </div>
                                </div>

                                <!-- CUERPO -->
                                <div class="whitespace-pre-wrap" v-html="formatWhatsappText(form.cuerpo)"></div>

                                <!-- BOTONES -->
                                <div v-if="form.botones.length" class="mt-3 space-y-2">
                                  <div
                                    v-for="btn in form.botones"
                                    :key="'pv-'+btn.id"
                                    class="space-y-1"
                                  >
                                    <button
                                      type="button"
                                      disabled
                                      class="w-full rounded-md border py-2 px-3 text-sm flex items-center justify-center gap-2"
                                      :class="{
                                        'bg-gray-100 border-gray-300 text-gray-800': btn.kind === 'BOTON',
                                        'bg-blue-50 border-blue-200 text-blue-700': btn.kind === 'URL',
                                        'bg-green-50 border-green-200 text-green-700': btn.kind === 'TELEFONO'
                                      }"
                                      title="Vista previa del botón"
                                    >
                                      <template v-if="btn.kind === 'URL'">
                                        <Link2 class="h-4 w-4" />
                                      </template>
                                      <template v-else-if="btn.kind === 'TELEFONO'">
                                        <PhoneOutgoing class="h-4 w-4" />
                                      </template>
                                      <template v-else>
                                        <SquarePlus class="h-4 w-4" />
                                      </template>

                                      <span class="truncate max-w-[85%]">
                                        {{ btn.text || (btn.kind === 'URL' ? 'Abrir enlace' : btn.kind === 'TELEFONO' ? 'Llamar' : 'Botón') }}
                                      </span>
                                    </button>

                                    <!-- Subtítulo informativo (opcional) -->
                                    <div v-if="btn.kind === 'URL' && btn.url" class="text-[11px] text-gray-500 text-center truncate">
                                      {{ btn.url }}
                                    </div>
                                    <div v-else-if="btn.kind === 'TELEFONO' && btn.phone" class="text-[11px] text-gray-500 text-center">
                                      {{ btn.phone }}
                                    </div>
                                  </div>
                                </div>

                                <!-- FOOTER -->
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
                  :href="route('templates.index', { companyId, communicationChannelId })"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-primary bg-muted hover:bg-muted/80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary mr-4 transition-colors">Cancel </Link>

                <button type="submit" class="bg-primary text-primary-foreground hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors px-4 py-2 rounded-md text-sm font-medium"
                > Enviar </button>

                <div v-if="form.errors.api" class="text-red-600 mt-2">
                  {{ form.errors.api }}
                </div>

              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
</AppLayout>
</template>
