<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, Link, useForm } from "@inertiajs/vue3";
import { Bell, Check, PhoneOutgoing, Link2, SquarePlus, CalendarIcon } from 'lucide-vue-next';
import InputError from "@/components/InputError.vue";
import { Label } from '@/components/ui/label'
import { RangeCalendar } from '@/components/ui/range-calendar'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
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

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Campaigns',
    href: '/campaigns',
  },
  {
    title: 'Nueva campaña',
    href: '/campaigns/create',
  },
]

import { useWhatsappFormatter } from '@/composables/useWhatsappFormatter'
const { formatWhatsappText } = useWhatsappFormatter()
import { ref, watch, computed } from 'vue'
import { today, getLocalTimeZone } from '@internationalized/date'

const timeZone = getLocalTimeZone()
const minDate = today(timeZone)
const maxDate = minDate.add({ days: 7 })

import { format } from 'date-fns'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const companies = page.props.companies as {
  id: number
  company_name: string
}[]
const form = useForm({
  nombre: '',
  descripcion: '',
  tipo: '',
  fecha_inicio: '',
  fecha_fin: '',
  compania: '',
  canal: '',
  template: '',
  file: null as File | null,
})

const dateRange = ref<any>(undefined)

watch(dateRange, (range) => {
  if (!range?.start || !range?.end) {
    form.fecha_inicio = ''
    form.fecha_fin = ''
    return
  }

  const diff =
    range.end.toDate(timeZone).getTime() -
    range.start.toDate(timeZone).getTime()

  const days = diff / (1000 * 60 * 60 * 24)

  if (days > 7) {
    dateRange.value = undefined
    return
  }

  form.fecha_inicio = format(range.start.toDate(timeZone), 'yyyy-MM-dd')
  form.fecha_fin = format(range.end.toDate(timeZone), 'yyyy-MM-dd')
})

const formattedRange = computed(() => {
  if (!dateRange.value?.start || !dateRange.value?.end) {
    return 'Selecciona rango de fechas'
  }

  const tz = getLocalTimeZone()

  return `${format(
    dateRange.value.start.toDate(tz),
    'yyyy-MM-dd'
  )} — ${format(
    dateRange.value.end.toDate(tz),
    'yyyy-MM-dd'
  )}`
})

const fileInputKey = ref(0)

watch(
  () => form.template,
  () => {
    form.file = null
    form.clearErrors('file')
    fileInputKey.value++ 
  }
)

const submit = () => {
  form.post(route('campaigns.store'), {
    forceFormData: true,
  })
}

import axios from 'axios'

const channels = ref<{ id: number; channel_name: string }[]>([])
watch(
  () => form.compania,
  async (companyId) => {
    form.canal = ''
    channels.value = []
    form.template = ''
    form.file = null
    form.clearErrors()

    if (!companyId) return

    const { data } = await axios.get(
      `/campaigns/companies/${companyId}/channels`
    )

    channels.value = data
  }
)

const templates = ref<{ id: number; name: string }[]>([])
watch(
  () => [form.compania, form.canal],
  async ([companyId, channelId]) => {
    form.template = ''
    templates.value = []
    form.file = null
    form.clearErrors()

    if (!companyId || !channelId) return

    const { data } = await axios.get(
      `/campaigns/companies/${companyId}/channels/${channelId}/templates`
    )

    templates.value = data
  }
)

const templatePreview = ref<any[]>([])
watch(
  () => form.template,
  async (templateId) => {
    templatePreview.value = []
    form.file = null
    form.clearErrors('file')

    if (!templateId) return

    const { data } = await axios.get(
      `/campaigns/templates/${templateId}`
    )

    templatePreview.value = data.components ?? []
  }
)

const header = computed(() =>
  templatePreview.value.find(c => c.type === 'HEADER')
)

const body = computed(() =>
  templatePreview.value.find(c => c.type === 'BODY')
)

const footer = computed(() =>
  templatePreview.value.find(c => c.type === 'FOOTER')
)

const buttons = computed(() =>
  templatePreview.value.find(c => c.type === 'BUTTONS')?.buttons ?? []
)

const variableCount = computed(() => {
  if (!body.value?.text) return 0

  const matches = body.value.text.match(/\{\{\s*(\d+)\s*\}\}/g)
  if (!matches) return 0

  const unique = new Set(
    matches.map((m: string) => m.replace(/\D/g, ''))
  )

  return unique.size
})

const onFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement
  const file = target.files?.[0]

  if (!file) return

  // Validar extensión
  if (!file.name.match(/\.(xlsx|xls)$/)) {
    form.setError('file', 'Solo se permiten archivos Excel (.xlsx, .xls)')
    target.value = ''
    return
  }

  form.file = file
}

const canUploadFile = computed(() => {
  return !!form.template && variableCount.value > 0
})

</script>

<template>
  <Head title="Campaigns" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <div class="lg:col-span-8">
            <Card>
                <CardHeader>
                  <CardTitle>Nueva Campaña</CardTitle>
                  <CardDescription>
                    Campaña detalle información
                  </CardDescription>
                </CardHeader>
                
                <CardContent class="flex justify-start">
                  <form id="campaign-form" @submit.prevent="submit">
                    <div class="shadow-sm sm:rounded-md sm:overflow-hidden transition-colors">

                        <div class="grid grid-cols-12 gap-6">
                          <div class="col-span-12 lg:col-span-4">
                            <label for="nombre" class="block text-sm font-medium text-foreground">Nombre</label>
                            <input
                              v-model="form.nombre"
                              type="text"
                              id="nombre"
                              :maxlength="50"
                              class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                              :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.nombre }"
                            />
                            <InputError class="mt-2" :message="form.errors.nombre" />
                          </div>

                          <div class="col-span-12 lg:col-span-4">
                            <label for="descripcion" class="block text-sm font-medium text-foreground">Descripción</label>
                            <input
                              v-model="form.descripcion"
                              type="text"
                              id="descripcion"
                              :maxlength="100"
                              class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                              :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.descripcion }"
                            />
                            <InputError class="mt-2" :message="form.errors.descripcion" />
                          </div>

                          <div class="col-span-12 lg:col-span-4">
                            <label for="tipo" class="block text-sm font-medium text-foreground">Tipo</label>
                            <select
                              v-model="form.tipo"
                              id="tipo"
                              class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                              :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.tipo }"
                            >
                              <option value="">Seleccione opción</option>
                              <option value="Manual">Manual</option>
                              <option value="Programada">Programada</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.tipo" />
                          </div>

                          <div class="col-span-12">
                            <Label>Vigencia de la campaña</Label>

                            <Popover>
                              <PopoverTrigger as-child>
                                <Button
                                  type="button"
                                  variant="outline"
                                  class="w-full justify-start text-left font-normal mt-1"
                                >
                                  <CalendarIcon class="mr-2 h-4 w-4" />
                                  <span>{{ formattedRange }}</span>
                                </Button>
                              </PopoverTrigger>

                              <PopoverContent
                                class="w-auto p-0"
                                align="start"
                                :side-offset="4"
                                :portalled="false"
                              >
                                <RangeCalendar
                                  v-model="dateRange"
                                  :number-of-months="1"
                                  :min-value="minDate"
                                  :max-value="maxDate"
                                />
                              </PopoverContent>
                            </Popover>

                            <InputError
                              class="mt-2"
                              :message="form.errors.fecha_inicio || form.errors.fecha_fin"
                            />
                          </div>

                          <div class="col-span-12 lg:col-span-4">
                            <label for="compania" class="block text-sm font-medium text-foreground">Compañia</label>
                            <select
                              v-model="form.compania"
                              id="compania"
                              class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                              :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.compania }"
                            >
                                <option value="">Seleccione compañía</option>
                                <option
                                  v-for="company in companies"
                                  :key="company.id"
                                  :value="company.id"
                                >
                                  {{ company.company_name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.compania" />
                          </div>

                          <div class="col-span-12 lg:col-span-4">
                            <label for="canal" class="block text-sm font-medium text-foreground">Canal</label>
                            <select
                              v-model="form.canal"
                              id="canal"
                              class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                              :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.canal }"
                              :disabled="!channels.length"
                            >
                                <option value="">
                                  {{ channels.length ? 'Seleccione canal' : 'Seleccione' }}
                                </option>

                                <option
                                  v-for="channel in channels"
                                  :key="channel.id"
                                  :value="channel.id"
                                >
                                  {{ channel.channel_name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.canal" />
                          </div>

                          <div class="col-span-12 lg:col-span-4">
                            <label for="template" class="block text-sm font-medium text-foreground">Plantilla</label>
                            <select
                              v-model="form.template"
                              id="template"
                              class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                              :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.template }"
                              :disabled="!templates.length"
                            >
                                 <option value="">
                                    {{ templates.length
                                      ? 'Seleccione template'
                                      : 'Seleccione' }}
                                  </option>

                                  <option
                                    v-for="template in templates"
                                    :key="template.id"
                                    :value="template.id"
                                  >
                                    {{ template.name }}
                                  </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.template" />
                          </div>

                          <div class="col-span-12 lg:col-span-12">
                            <label class="block text-sm font-medium text-foreground">
                              Base Excel
                            </label>

                            <input
                              :key="fileInputKey"
                              type="file"
                              accept=".xlsx,.xls"
                              @change="onFileChange"
                              :disabled="!canUploadFile"
                              class="mt-1 block w-full text-sm text-muted-foreground
                                    file:mr-4 file:py-2 file:px-4
                                    file:rounded-md file:border-0
                                    file:text-sm file:font-semibold
                                    file:bg-primary file:text-primary-foreground
                                    hover:file:bg-primary/90
                                    disabled:opacity-50 disabled:cursor-not-allowed"
                            />

                            <p class="mt-1 text-xs text-muted-foreground">
                              <template v-if="!form.template">
                                Selecciona una <b>plantilla</b> para habilitar la carga del archivo Excel.
                              </template>

                              <template v-else>
                                La primera columna debe ser <b>telefono</b>.  
                                El archivo debe contener <b>{{ variableCount }}</b> columnas de variables.
                              </template>
                            </p>

                            <InputError class="mt-2" :message="form.errors.file" />
                          </div>

                        </div>
                    </div>
                  </form>

                </CardContent>
            </Card>
        </div>

        <div class="lg:col-span-4">
            <Card>
                <CardHeader>
                  <CardTitle>Previsualización de la plantilla</CardTitle>
                  <CardDescription>
                    Vista previa del mensaje configurado para la campaña
                  </CardDescription>
                </CardHeader>
                
                <CardContent class="flex justify-start">
                  <div class="relative w-[450px]" v-if="templatePreview.length">
                    <div
                      class="rounded-md bg-cover bg-center w-full min-h-[200px] bg-no-repeat p-4"
                      :style="{ backgroundImage: 'url(/img/template.jpeg)' }"
                    >
                      <div
                        class="bg-white rounded-xl shadow-md p-4 w-[90%] break-words text-sm"
                        style="color:black"
                      >
                        <!-- HEADER -->
                        <div v-if="header">
                          <!-- Texto -->
                          <div
                            v-if="header.format === 'TEXT'"
                            class="font-bold mb-2"
                          >
                            {{ header.text }}
                          </div>

                          <!-- Imagen -->
                          <img
                            v-if="header.format === 'IMAGE'"
                            :src="header.example?.header_handle?.[0]"
                            class="w-full rounded-md mb-2"
                          />
                        </div>

                        <!-- BODY -->
                        <div
                          v-if="body"
                          class="mb-2 whitespace-pre-wrap"
                          v-html="formatWhatsappText(body.text)"
                        ></div>

                        <!-- BUTTONS -->
                        <div v-if="buttons.length" class="mt-3 space-y-2">
                          <button
                            v-for="(btn, i) in buttons"
                            :key="i"
                            disabled
                            class="w-full rounded-md border py-2 text-sm bg-gray-50"
                          >
                            {{ btn.text }}
                          </button>
                        </div>

                        <!-- FOOTER -->
                        <div v-if="footer" class="mt-2 text-xs text-gray-500">
                          {{ footer.text }}
                        </div>

                        <div class="mt-2 text-xs text-gray-400 text-right">
                          {{ new Date().toLocaleTimeString() }}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div v-else class="text-muted-foreground text-sm">
                    Selecciona una plantilla para previsualizar
                  </div>
                </CardContent>
            </Card>
        </div>

        <div class="px-4 py-3 bg-muted text-right sm:px-6 lg:col-span-12">
            <Link
              :href="route('templates.index')"
              class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-primary bg-muted hover:bg-muted/80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary mr-4 transition-colors">
              Cancel 
            </Link>

            <button 
            type="submit"
            form="campaign-form"
            class="bg-primary text-primary-foreground hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors px-4 py-2 rounded-md text-sm font-medium"
            > Enviar 
            </button>
        </div>

      </div>
    </div>
  </AppLayout>
</template>
