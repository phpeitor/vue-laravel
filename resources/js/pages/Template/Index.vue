<script setup>
import MagnifyingGlass from "@/components/Icons/MagnifyingGlass.vue";
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue'
import { Head, Link, router  } from '@inertiajs/vue3'
import { h, ref, watch } from 'vue'
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
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { ArrowUpDown, ChevronLeft, ChevronRight } from 'lucide-vue-next'

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

const companyId = ref(props.selectedCompanyId || null)
const channelId = ref(props.selectedChannelId || null)
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
    accessorKey: 'metaStatus',
    header: 'Estado',
    cell: ({ row }) => {
      const status = row.getValue('metaStatus')
      const statusClass = {
        APPROVED: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        REJECTED: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
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
        if (header.format === 'IMAGE') {
          const url = header.example?.header_handle?.[0] ?? '';
          children.push(
            h('img', {
              src: url,
              alt: 'Header Image',
              class: 'rounded-md mx-auto max-h-40 object-cover mb-2',
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
        const values = body.example?.body_text?.[0] ?? [];
        let formatted = body.text || '';
        values.forEach((v, i) => {
          formatted = formatted.replace(`{{${i + 1}}}`, v);
        });

        children.push(
          h('p', {
            class: 'text-sm text-muted-foreground mb-1 whitespace-pre-wrap',
          }, formatted)
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
</script>

<template>
  <Head title="Templates" />
  <AuthenticatedLayout>
    <div class="bg-background text-foreground py-10">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="sm:flex sm:items-center">
          <div class="sm:flex-auto">
            <div class="flex items-center gap-2">
              <h1 class="text-xl font-semibold text-foreground">Plantillas de Comunicación</h1>
              <Button variant="outline" class="h-7 w-7 p-0">
                <ChevronRight class="w-3 h-3" />
              </Button>
            </div>
          </div>

          <div class="flex flex-wrap items-center">
            <select
              v-model="companyId"
              @change="onCompanyChange"
              class="text-sm rounded-md border px-2 py-1 bg-background text-foreground ring-1 ring-border focus:outline-none"
            >
              <option value="">Seleccione compañia</option>
              <option v-for="c in props.companies" :key="c.id" :value="c.id">
                {{ c.company_name }}
              </option>
            </select>

            <select
              v-model="channelId"
              @change="applyFilters"
              :disabled="!companyId"
              class="text-sm rounded-md border px-2 py-1 bg-background text-foreground ring-1 ring-border focus:outline-none"
            >
              <option value="">Seleccione canal</option>
              <option v-for="c in props.channels" :key="c.id" :value="c.id">
                {{ c.channel_name }} ({{ c.channel_type }})
              </option>
            </select>

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
  </AuthenticatedLayout>
</template>