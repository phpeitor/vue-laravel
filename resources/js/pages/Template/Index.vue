<script setup>
import AppLayout from '@/layouts/AppLayout.vue'
import MagnifyingGlass from "@/components/Icons/MagnifyingGlass.vue";
import { Trash2, FlaskConical, Send, Phone, MessageCircle, Instagram, Facebook } from 'lucide-vue-next'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { h, ref, watch } from 'vue'
import { useToast } from '@/components/ui/toast'
import { Badge } from '@/components/ui/badge'
import { useWhatsappFormatter } from '@/composables/useWhatsappFormatter'
import {
  AlertDialog,
  AlertDialogTrigger,
  AlertDialogContent,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogCancel,
  AlertDialogAction
} from '@/components/ui/alert-dialog'
import {
  Dialog,
  DialogTrigger,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose
} from '@/components/ui/dialog'
import {
  getCoreRowModel,
  getFilteredRowModel,
  getSortedRowModel,
  getPaginationRowModel,
  useVueTable,
  FlexRender,
} from '@tanstack/vue-table'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger
} from '@/components/ui/tooltip'

import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { ArrowUpDown, ChevronLeft, ChevronRight } from 'lucide-vue-next'

const breadcrumbs = [
  {
    title: 'Templates',
    href: '/templates',
  }
]
const { formatWhatsappText } = useWhatsappFormatter()

function onCompanyChange() {
  channelId.value = ''
  router.get(route('templates.index'), { companyId: companyId.value })
}

function applyFilters() {
  router.get(route('templates.index'), {
    companyId: companyId.value,
    communicationChannelId: channelId.value,
  })
}

function channelIcon(type) {
  switch (type) {
    case 'whatsapp-meta':
      return MessageCircle
    case 'instagram-messenger':
      return Instagram
    case 'facebook-messenger':
      return Facebook
    case 'voice-voximplant':
      return Phone
    default:
      return MessageCircle
  }
}

function channelIdentifier(channel) {
   return channel.channel_name || channel.channel_type || 'Canal Desconocido'
}

const props = defineProps({
  templates: Array,
  companies: Array,
  channels: Array,
  selectedCompanyId: [Number, String],
  selectedChannelId: [Number, String],
  errorMessage: {
    type: String,
    default: '',
  },
})

const { toast } = useToast()
const page = usePage()
const companyId = ref(props.selectedCompanyId ?? '')
const channelId = ref(props.selectedChannelId ?? '')
const globalFilter = ref('');
const sorting = ref([{ id: 'id', desc: true }]);
const pagination = ref({ pageIndex: 0, pageSize: 10 });

const columns = [
  {
    accessorKey: 'id',
    header: ({ column }) =>
      h(
        Button,
        {
          variant: 'ghost',
          onClick: () =>
            column.toggleSorting(column.getIsSorted() === 'asc'),
        },
        () => ['ID', h(ArrowUpDown, { class: 'ml-2 w-4 h-4' })]
      ),
    cell: ({ row }) => h('span', row.getValue('id')),
  },
  {
    accessorKey: 'name',
    header: 'Nombre',
    cell: ({ row }) => h('span', row.getValue('name')),
  },
  {
    accessorKey: 'category',
    header: 'Categoría',
    cell: ({ row }) => h('span', row.getValue('category')),
  },
  {
    accessorKey: 'meta_status',
    header: 'Estado',
    cell: ({ row }) => {
      const status = row.getValue('meta_status')
      const statusClass = {
        APPROVED: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        REJECTED: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        PENDING: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
      }[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'

      return h('span', {
        class: `px-2 py-1 rounded text-xs font-medium ${statusClass}`,
      }, status)
    },
  },
  {
    id: 'components',
    header: 'Componentes',
    cell: ({ row }) => {
      const components = row.original.components || [];

      const typeMap = {
        HEADER: null,
        BODY: null,
        FOOTER: null,
        BUTTONS: null,
      };

      components.forEach((comp) => {
        if (typeMap.hasOwnProperty(comp.type)) {
          typeMap[comp.type] = comp;
        }
      });

      const children = [];

      // HEADER
      const header = typeMap.HEADER;
      if (header) {
        const url = header.example?.header_handle?.[0] ?? '';

        if (!url.includes('.') && header.format) {
          children.push(
            h(Badge, { variant: 'secondary', class: 'mb-2' }, () => `Header ${header.format}`)
          )
        }else if (header.format === 'IMAGE' ) {
          children.push(
            h('img', {
              src: url,
              alt: 'Header Image',
              class: 'rounded-md mx-auto max-h-40 object-cover mb-2',
            })
          );
        } else if (header.format === 'VIDEO' ) {
          children.push(
            h('video', {
              src: url,
              controls: true,
              class: 'rounded-md mx-auto max-h-40 mb-2',
            })
          );
        } else if (header.format === 'DOCUMENT' ) {
          children.push(
            h('iframe', {
              src: url,
              class: 'rounded-md mx-auto w-full h-30 mb-2 border',
            })
          );
        } else {
          children.push(
            h('div', {
              class: 'text-sm font-semibold text-foreground mb-1',
            }, header.text)
          );
        }
      }

      // BODY
      const body = typeMap.BODY;
      if (body) {
        const formattedText = formatWhatsappText(body.text || '')
        .replace(/({{\d+}})/g, '<strong>$1</strong>')

        children.push(
          h('p', {
            class: 'text-sm text-muted-foreground mb-1',
            innerHTML: formattedText
          })
        );
      }

      // FOOTER
      const footer = typeMap.FOOTER;
      if (footer) {
        children.push(
          h('p', {
            class: 'text-xs text-muted-foreground italic mb-1',
          }, footer.text)
        );
      }

      // BUTTONS
      const buttons = typeMap.BUTTONS?.buttons ?? [];
      if (buttons.length > 0) {
        children.push(
          h('div', {
            class: 'flex flex-wrap gap-2 mt-2',
          }, buttons.map((btn, i) =>
            h('button', {
              key: i,
              class: 'text-xs px-2 py-1 border rounded-md bg-background hover:bg-accent transition-colors',
            }, btn.text)
          ))
        );
      }

      return h('div', {
        class: 'p-3 rounded-xl border shadow-sm bg-muted text-left',
      }, children);
    },
  },
  {
    id: 'acciones',
    header: 'Acciones',
    cell: ({ row }) => {
      const templateId = row.original.id
      const isApproved = row.original.meta_status === 'APPROVED'

      const acciones = [
        h(TooltipProvider, {}, {
          default: () => h(Tooltip, {}, {
            default: () => [
              h(TooltipTrigger, { asChild: true }, () =>
                h(Button, {
                  variant: 'ghost',
                  size: 'icon',
                  class: 'h-8 w-8',
                  onClick: () => confirmDeleteTemplate(templateId),
                }, () => h(Trash2, { class: 'h-4 w-4 text-red-500' }))
              ),
              h(TooltipContent, { side: 'top' }, () => 'Eliminar')
            ]
          })
        })
      ]

      if (isApproved) {
        // Botón Test
        acciones.push(
          h(TooltipProvider, {}, {
            default: () => h(Tooltip, {}, {
              default: () => [
                h(TooltipTrigger, { asChild: true }, () =>
                  h(Button, {
                    variant: 'ghost',
                    size: 'icon',
                    class: 'h-8 w-8',
                    onClick: () => openTestTemplateDialog(templateId),
                  }, () => h(FlaskConical, { class: 'h-4 w-4 text-yellow-500' }))
                ),
                h(TooltipContent, { side: 'top' }, () => 'Test')
              ]
            })
          })
        )
      }

      return h('div', { class: 'flex gap-2' }, acciones)
    }
  }
]

const table = useVueTable({
  data: props.templates,
  columns,
  getCoreRowModel: getCoreRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  state: {
    get globalFilter() {
      return globalFilter.value
    },
    get sorting() {
      return sorting.value
    },
    get pagination() {
      return pagination.value
    },
  },
  onGlobalFilterChange: (val) => (globalFilter.value = val),
  onSortingChange: (val) =>
    (sorting.value = typeof val === 'function' ? val(sorting.value) : val),
  onPaginationChange: (val) =>
    (pagination.value = typeof val === 'function' ? val(pagination.value) : val),
})

const showAlert = ref(false)

function goToCreate() {
  if (!companyId.value || !channelId.value) {
    showAlert.value = true
    return
  }

  const url = route('templates.create', {
    communicationChannelId: channelId.value,
    companyId: companyId.value,
  })

  router.visit(url)
}

const openDeleteDialog = ref(false)
const templateToDelete = ref(null)

function confirmDeleteTemplate(id) {
  templateToDelete.value = id
  openDeleteDialog.value = true
}

function eliminarTemplate() {
  if (!templateToDelete.value) return;

  router.visit(
    route('templates.destroy', {
      id: templateToDelete.value,
      companyId: companyId.value,
      communicationChannelId: channelId.value
    }),
    {
      method: 'delete',
      preserveScroll: true,
      onSuccess: () => {
        openDeleteDialog.value = false
        templateToDelete.value = null
      },
      onError: (errors) => {
        alert('Error al eliminar la plantilla')
        console.error(errors)
      }
    }
  )

}

const openTestDialog = ref(false)
const testInputs = ref([])
const testTemplate = ref(null)
const telefonoDestino = ref('') 
const loadingTest = ref(false)

function openTestTemplateDialog(templateId) {
  const template = props.templates.find(t => t.id === templateId)
  if (!template) return

  testTemplate.value = template
  const body = (template.components || []).find(c => c.type === 'BODY')
  const matches = body?.text.match(/{{\d+}}/g) || []

  testInputs.value = matches.map((placeholder, i) => ({
    key: placeholder,
    value: ''
  }))

  console.log(testTemplate);

  openTestDialog.value = true
}

function renderBodyWithInputs(text) {
  let parts = text.split(/({{\d+}})/g)

  return parts.map((part, index) => {
    const matchIndex = testInputs.value.findIndex(p => p.key === part)
    if (matchIndex !== -1) {
      return h('input', {
        type: 'text',
        maxlength: 30,
        class: 'border-0 border-b border-muted-foreground bg-transparent w-32 text-sm focus:outline-none focus:ring-0 focus:border-muted-foreground',
        value: testInputs.value[matchIndex].value,
        onInput: (e) => testInputs.value[matchIndex].value = e.target.value
      })
    }
    return part
  })
}

function handleTestSubmit() {
  const valores = testInputs.value.map(i => i.value?.trim())

  if (!testTemplate.value) {
    toast({
      title: 'Error inesperado',
      description: 'No hay plantilla seleccionada',
      variant: 'destructive',
    })
    return
  }

  if (valores.some(v => !v)) {
    toast({
      title: 'Campos requeridos',
      description: 'Por favor, completa todos los campos antes de continuar',
      variant: 'destructive',
    })
    return
  }

  if (!telefonoDestino.value || telefonoDestino.value.length !== 11) {
    toast({
      title: 'Número inválido',
      description: 'Teléfono debe tener 11 dígitos',
      variant: 'destructive',
    })
    return
  }

  loadingTest.value = true

  const headerComponent = (testTemplate.value.components || []).find(c => c.type === 'HEADER')
  let templateHeader = null

  if (headerComponent && ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(headerComponent.format)) {
    //const url = headerComponent.example?.header_handle?.[0]
    const url = testTemplate.value.url
    if (url) {
      templateHeader = {
        type: headerComponent.format.toLowerCase(), 
        variable: url
      }
    }
  }

  const payload = {
    companyId: Number(companyId.value),
    communicationChannelId: Number(channelId.value),
    messageTemplateId: testTemplate.value.id,
    recipientData: {
      phone: telefonoDestino.value,
      templateBody: valores,
    }
  }

  if (templateHeader) {
    payload.recipientData.templateHeader = templateHeader
  }

  router.post(route('templates.sendTest'), payload, {
    preserveScroll: true,
    onSuccess: () => {
      if (page.props.flash.success) {
        toast({
          title: 'Exito',
          description: page.props.flash.success,
          variant: 'success',
        })
        openTestDialog.value = false
      }
    },
    onError: (errors) => {

      if (errors.toast || errors.general || errors.api) {
        toast({
          title: 'Error',
          description: errors.toast || errors.general || errors.api,
          variant: 'destructive',
        })
      } else {
        toast({
          title: 'Error',
          description: 'Ocurrió un error inesperado',
          variant: 'destructive',
        })
      }
    },
    onFinish: () => {
      loadingTest.value = false
    }
  })

}

function asignarCampania(id) {
  console.log('Asignar campaña a template', id)
}

function soloNumeros(e) {
  if (!/[0-9]/.test(e.key)) {
    e.preventDefault()
  }
}
</script>

<template>
  <Head title="Templates" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="bg-background text-foreground py-10">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="sm:flex sm:items-center">
          <div class="sm:flex-auto">
            <div class="flex items-center gap-2">
              <h1 class="text-xl font-semibold text-foreground">Plantillas de Comunicación</h1>
              
              <TooltipProvider>
                <Tooltip>
                  <TooltipTrigger as-child>
                    <Button
                      variant="outline"
                      class="h-7 w-7 p-0"
                      @click="goToCreate"
                    >
                      <ChevronRight class="w-3 h-3" />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent side="top" align="center">
                    <span>Crear</span>
                  </TooltipContent>
                </Tooltip>
              </TooltipProvider>

            </div>
          </div>

          <div class="flex items-center gap-3 mr-4 min-w-[420px]">
            <select
              v-model="companyId"
              @change="onCompanyChange"
              class="h-9 w-44 rounded-md border bg-background px-3 text-sm text-foreground
                    ring-1 ring-border focus:outline-none"
            >
              <option value="" disabled>
                Seleccione compañía
              </option>
              <option
                v-for="c in props.companies"
                :key="c.id"
                :value="c.id"
              >
                {{ c.company_name }}
              </option>
            </select>
            
            <div class="relative w-56">
              <div
                class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-muted-foreground"
              >
                <component
                  :is="
                    channelIcon(
                      props.channels.find(c => c.id == channelId)?.channel_type
                    )
                  "
                  class="h-4 w-4"
                />
              </div>

              <select
                v-model="channelId"
                @change="applyFilters"
                :disabled="!companyId"
                class="h-9 w-full rounded-md border bg-background
                      pl-8 pr-3 text-sm text-foreground
                      ring-1 ring-border focus:outline-none
                      truncate disabled:opacity-50"
              >
                <option value="" disabled>
                  Seleccione canal
                </option>

                <option
                  v-for="c in props.channels"
                  :key="c.id"
                  :value="c.id"
                >
                  {{ channelIdentifier(c) }}
                </option>
              </select>
            </div>

          </div>

          <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none relative w-full max-w-xs">
            <MagnifyingGlass class="absolute left-2.5 top-1/2 h-4 w-4 text-muted-foreground transform -translate-y-1/2 pointer-events-none" />
            <Input
              type="text"
              placeholder="Buscar"
              class="pl-8 py-1.5 text-sm bg-card text-foreground ring-1 ring-inset ring-border placeholder:text-muted-foreground focus:ring-2 focus:ring-inset focus:ring-indigo-600 transition-colors"
              :model-value="globalFilter"
              @update:model-value="(value) => table.setGlobalFilter(value)"
            />
          </div>

        </div>

        <div class="mt-8 rounded-md border shadow">
          <Table>
            <TableHeader>
              <TableRow
                v-for="headerGroup in table.getHeaderGroups()"
                :key="headerGroup.id"
              >
                <TableHead
                  v-for="header in headerGroup.headers"
                  :key="header.id"
                >
                  <FlexRender
                    v-if="!header.isPlaceholder"
                    :render="header.column.columnDef.header"
                    :props="header.getContext()"
                  />
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <template v-if="table.getRowModel().rows.length">
                <TableRow
                  v-for="row in table.getRowModel().rows"
                  :key="row.id"
                >
                  <TableCell
                    v-for="cell in row.getVisibleCells()"
                    :key="cell.id"
                  >
                    <FlexRender
                      :render="cell.column.columnDef.cell"
                      :props="cell.getContext()"
                    />
                  </TableCell>
                </TableRow>
              </template>
              <TableRow v-else>
                <TableCell :colspan="columns.length" class="text-center py-4">
                    <template v-if="props.errorMessage">
                      <span class="text-red-500 font-medium">{{ props.errorMessage }}</span>
                    </template>
                    <template v-else>
                      No se encontraron resultados
                    </template>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>

        <div class="mt-4 flex justify-between items-center">
          <div class="text-sm text-muted-foreground">
            Página {{ table.getState().pagination.pageIndex + 1 }} de
            {{ table.getPageCount() }}
          </div>
          <div class="space-x-2">
            <Button
              variant="outline"
              :disabled="!table.getCanPreviousPage()"
              @click="table.previousPage()"
            >
              <ChevronLeft class="w-4 h-4 mr-1" /> Anterior
            </Button>
            <Button
              variant="outline"
              :disabled="!table.getCanNextPage()"
              @click="table.nextPage()"
            >
              Siguiente <ChevronRight class="w-4 h-4 ml-1" />
            </Button>
          </div>
        </div>
      </div>
    </div>

    <AlertDialog v-model:open="openDeleteDialog">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
          <AlertDialogDescription>
            Esta acción eliminará la plantilla seleccionada. Esta operación no se puede deshacer.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancelar</AlertDialogCancel>
          <AlertDialogAction @click="eliminarTemplate">
            Confirmar
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <AlertDialog v-model:open="showAlert">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>
            Selección requerida
          </AlertDialogTitle>
          <AlertDialogDescription>
            Debe seleccionar una compañía y un canal antes de continuar.
          </AlertDialogDescription>
        </AlertDialogHeader>

        <AlertDialogFooter>
          <AlertDialogAction @click="showAlert = false">
            Entendido
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <Dialog v-model:open="openTestDialog">
      <DialogContent class="max-w-lg">
        <DialogHeader>
          <DialogTitle>Test Template</DialogTitle>
          <DialogDescription class="mb-2">
            Simula el contenido con valores de prueba
          </DialogDescription>
        </DialogHeader>

        <div class="border rounded-md p-4 bg-muted space-y-4">
          <!-- HEADER -->
          <div v-if="testTemplate">
            <div v-for="component in testTemplate.components" :key="component.type">
              <template v-if="component.type === 'HEADER'">
                <div v-if="component.format === 'IMAGE'">
                  <img
                    :src="component.example?.header_handle?.[0]"
                    alt="Header Image"
                    class="rounded-md max-h-32 object-cover mb-2"
                  />
                </div>
                <div v-else class="text-sm font-semibold mb-2">
                  {{ component.text }}
                </div>
              </template>

              <!-- BODY -->
              <template v-if="component.type === 'BODY'">
                <p class="text-sm text-muted-foreground whitespace-pre-wrap">
                  <component :is="'span'" v-for="(node, i) in renderBodyWithInputs(component.text)" :key="i">
                    <template v-if="typeof node === 'string'">{{ node }}</template>
                    <template v-else><component :is="node" /></template>
                  </component>
                </p>
              </template>

              <!-- FOOTER -->
              <template v-if="component.type === 'FOOTER'">
                <p class="text-xs italic text-muted-foreground mt-2">{{ component.text }}</p>
              </template>

              <!-- BUTTONS -->
              <template v-if="component.type === 'BUTTONS' && component.buttons?.length">
                <div class="flex gap-2 mt-4">
                  <button
                    v-for="(btn, idx) in component.buttons"
                    :key="idx"
                    class="text-xs px-2 py-1 border rounded bg-background hover:bg-accent transition"
                  >
                    {{ btn.text }}
                  </button>
                </div>
              </template>
            </div>

            <div class="mt-6 flex items-center gap-2">
              <Phone class="w-4 h-4 text-muted-foreground" />
              <input
                v-model="telefonoDestino"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="11"
                placeholder="Teléfono"
                class="w-32 border-0 border-b border-muted-foreground rounded-none px-0 py-0.5 text-sm bg-transparent focus:outline-none focus:ring-0 focus:border-muted-foreground"
                @input="e => telefonoDestino = e.target.value.replace(/\D/g, '')"
                @keypress="soloNumeros"
              />
            </div>
          </div>
        </div>

        <DialogFooter class="mt-4">
          <DialogClose as-child>
            <Button variant="outline">Cancelar</Button>
          </DialogClose>
          <Button :disabled="loadingTest" @click="handleTestSubmit">
            <template v-if="loadingTest">Enviando...</template>
            <template v-else>Enviar</template>
          </Button>
        </DialogFooter>

      </DialogContent>
    </Dialog>

  </AppLayout>
</template>