<script setup lang="ts">
import { computed, onMounted, ref, watch, type Ref } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { useToast } from '@/components/ui/toast/use-toast';
import { Badge } from '@/components/ui/badge';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { RangeCalendar } from '@/components/ui/range-calendar';
import { cn } from '@/lib/utils';
import { Download, X, CalendarIcon, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import type { DateRange } from 'reka-ui';
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { addDays, differenceInCalendarDays, format, subDays } from 'date-fns';

type Company = { id: number; company_name: string };
type Channel = { id: number; channel_name: string; company_id?: number };
type ThreadRow = {
  id: number;
  thread_status: string;
  create_date: string;
  canal: string;
  cliente: string | null;
  canal_cliente: string | null;
  asesor: string | null;
  cantidad: number;
  text: number;
  image: number;
  document: number;
  otro: number;
  bot: number;
  usuario: number;
};

const breadcrumbs = [
  { title: 'Reportes', href: '/reports/threads' },
  { title: 'Threads', href: '/reports/threads' },
];

const { toast } = useToast();

const showFilterModal = ref(true);
const isLoading = ref(false);
const isExporting = ref(false);
const hasAppliedFilters = ref(false);

const companies = ref<Company[]>([]);
const channels = ref<Channel[]>([]);
const threads = ref<ThreadRow[]>([]);

const selectedCompany = ref<string>('');
const selectedChannel = ref<string>('');

const currentPage = ref(1);
const perPage = ref(100);
const totalRows = ref(0);
const lastPage = ref(1);
const MAX_RANGE_DAYS = 15;

const tz = getLocalTimeZone();
const minDate = today(tz).subtract({ days: 365 });
const maxDate = today(tz);

const buildDefaultDates = () => {
  const end = new Date();
  const start = subDays(end, 7);
  return {
    startStr: format(start, 'yyyy-MM-dd'),
    endStr: format(end, 'yyyy-MM-dd'),
  };
};

const { startStr, endStr } = buildDefaultDates();
const startDate = ref(startStr);
const endDate = ref(endStr);

const dateRange = ref({
  start: parseDate(startStr),
  end: parseDate(endStr),
}) as Ref<DateRange>;

const formattedRange = computed(() => {
  if (!startDate.value || !endDate.value) return 'Selecciona rango';
  return `${startDate.value} — ${endDate.value}`;
});

const filtersReady = computed(() => {
  return !!startDate.value && !!endDate.value && !!selectedCompany.value && !!selectedChannel.value;
});

const fromRow = computed(() => {
  if (totalRows.value === 0) return 0;
  return (currentPage.value - 1) * perPage.value + 1;
});

const toRow = computed(() => {
  return Math.min(currentPage.value * perPage.value, totalRows.value);
});

const toDateString = (d: { year: number; month: number; day: number }) => {
  return `${d.year}-${String(d.month).padStart(2, '0')}-${String(d.day).padStart(2, '0')}`;
};

const normalizeDateRange = (start: string, end: string) => {
  const todayStr = format(new Date(), 'yyyy-MM-dd');
  let normalizedStart = start;
  let normalizedEnd = end > todayStr ? todayStr : end;

  if (normalizedStart > normalizedEnd) {
    normalizedStart = normalizedEnd;
  }

  const startObj = new Date(`${normalizedStart}T00:00:00`);
  const endObj = new Date(`${normalizedEnd}T00:00:00`);
  const spanDays = differenceInCalendarDays(endObj, startObj) + 1;

  if (spanDays > MAX_RANGE_DAYS) {
    const maxEnd = addDays(startObj, MAX_RANGE_DAYS - 1);
    normalizedEnd = format(maxEnd, 'yyyy-MM-dd');

    if (normalizedEnd > todayStr) {
      normalizedEnd = todayStr;
    }
  }

  return {
    start: normalizedStart,
    end: normalizedEnd,
    changed: normalizedStart !== start || normalizedEnd !== end,
  };
};

const isDateRangeValid = () => {
  if (!startDate.value || !endDate.value) return false;
  const startObj = new Date(`${startDate.value}T00:00:00`);
  const endObj = new Date(`${endDate.value}T00:00:00`);
  const todayObj = new Date(`${format(new Date(), 'yyyy-MM-dd')}T00:00:00`);

  if (endObj > todayObj) return false;
  if (startObj > endObj) return false;

  return differenceInCalendarDays(endObj, startObj) + 1 <= MAX_RANGE_DAYS;
};

watch(dateRange, (range) => {
  if (!range?.start || !range?.end) return;

  const normalized = normalizeDateRange(toDateString(range.start), toDateString(range.end));

  startDate.value = normalized.start;
  endDate.value = normalized.end;

  if (normalized.changed) {
    dateRange.value = {
      start: parseDate(normalized.start),
      end: parseDate(normalized.end),
    } as DateRange;

    toast({
      title: 'Rango ajustado',
      description: `El rango máximo permitido es de ${MAX_RANGE_DAYS} días y no puede superar la fecha actual.`,
    });
  }
});

const fetchChannelsByCompany = async (companyId: string) => {
  channels.value = [];
  selectedChannel.value = '';

  if (!companyId) return;

  const channelsRes = await axios.get('/reports/filters/channels', {
    params: { company_id: companyId },
  });

  channels.value = channelsRes.data ?? [];

  if (channels.value.length === 1) {
    selectedChannel.value = String(channels.value[0].id);
  }
};

const fetchThreads = async (page = 1, closeFilter = false) => {
  if (!filtersReady.value) {
    toast({
      title: 'Validación',
      description: 'Completa compañía, canal y rango de fechas.',
      variant: 'destructive',
    });
    return;
  }

  if (!isDateRangeValid()) {
    toast({
      title: 'Validación',
      description: `El rango debe ser de máximo ${MAX_RANGE_DAYS} días y hasta la fecha actual.`,
      variant: 'destructive',
    });
    return;
  }

  isLoading.value = true;

  try {
    const response = await axios.get('/reports/threads/data', {
      params: {
        start_date: startDate.value,
        end_date: endDate.value,
        company_id: selectedCompany.value,
        communication_channel_id: selectedChannel.value,
        page,
        per_page: perPage.value,
      },
    });

    threads.value = response.data.data ?? [];

    const meta = response.data.meta ?? {};
    totalRows.value = Number(meta.total ?? 0);
    currentPage.value = Number(meta.page ?? 1);
    lastPage.value = Number(meta.last_page ?? 1);

    hasAppliedFilters.value = true;
    if (closeFilter) showFilterModal.value = false;

    if (totalRows.value === 0) {
      toast({
        title: 'Sin resultados',
        description: 'No se encontraron threads para los filtros seleccionados.',
      });
    }
  } catch (error: any) {
    toast({
      title: 'Error',
      description: error.response?.data?.message || 'Error al cargar los threads.',
      variant: 'destructive',
    });
  } finally {
    isLoading.value = false;
  }
};

onMounted(async () => {
  try {
    const companiesRes = await axios.get('/reports/filters/companies');
    companies.value = companiesRes.data ?? [];

    if (companies.value.length === 1) {
      selectedCompany.value = String(companies.value[0].id);
      await fetchChannelsByCompany(selectedCompany.value);
    }
  } catch {
    toast({
      title: 'Error',
      description: 'No se pudieron cargar las compañías.',
      variant: 'destructive',
    });
  }
});

const onCompanyChange = async (companyId: string) => {
  selectedCompany.value = companyId;

  try {
    await fetchChannelsByCompany(companyId);
  } catch {
    toast({
      title: 'Error',
      description: 'No se pudieron cargar los canales de la compañía seleccionada.',
      variant: 'destructive',
    });
  }
};

const applyFilters = async () => {
  currentPage.value = 1;
  await fetchThreads(1, true);
};

const closeModal = () => {
  showFilterModal.value = false;
};

const openModal = () => {
  showFilterModal.value = true;
};

const goToPreviousPage = async () => {
  if (currentPage.value <= 1 || isLoading.value) return;
  await fetchThreads(currentPage.value - 1, false);
};

const goToNextPage = async () => {
  if (currentPage.value >= lastPage.value || isLoading.value) return;
  await fetchThreads(currentPage.value + 1, false);
};

const downloadReport = async () => {
  if (!filtersReady.value) {
    toast({
      title: 'Validación',
      description: 'Aplica filtros antes de exportar.',
      variant: 'destructive',
    });
    return;
  }

  if (!isDateRangeValid()) {
    toast({
      title: 'Validación',
      description: `El rango debe ser de máximo ${MAX_RANGE_DAYS} días y hasta la fecha actual.`,
      variant: 'destructive',
    });
    return;
  }

  isExporting.value = true;

  try {
    const response = await axios.get('/reports/threads/export', {
      params: {
        start_date: startDate.value,
        end_date: endDate.value,
        company_id: selectedCompany.value,
        communication_channel_id: selectedChannel.value,
      },
      responseType: 'blob',
    });

    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });

    const disposition = response.headers['content-disposition'] as string | undefined;
    let fileName = `threads_${new Date().toISOString().slice(0, 10)}.xlsx`;

    if (disposition) {
      const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i);
      if (match?.[1]) {
        fileName = match[1].replace(/['"]/g, '');
      }
    }

    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    link.click();
    window.URL.revokeObjectURL(url);

    toast({
      title: 'Éxito',
      description: 'Reporte Excel descargado correctamente.',
    });
  } catch {
    toast({
      title: 'Error',
      description: 'No se pudo exportar el reporte en Excel.',
      variant: 'destructive',
    });
  } finally {
    isExporting.value = false;
  }
};

const getStatusBadgeClass = (status: string) => {
  const statusMap: Record<string, string> = {
    OPEN: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900 dark:text-blue-100 pointer-events-none',
    CLOSED: 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 pointer-events-none',
    SPAM: 'border-rose-200 bg-rose-100 text-rose-700 dark:border-rose-700 dark:bg-rose-900 dark:text-rose-100 pointer-events-none',
  };

  return statusMap[status] || 'border-slate-200 bg-slate-100 text-slate-700 pointer-events-none';
};
</script>

<template>
  <Head title="Reporte de Threads" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background p-6">
      <div class="mb-6">
        <h1 class="text-3xl font-bold tracking-tight">Reporte de Threads</h1>
        <p class="mt-2 text-muted-foreground">
          Visualiza y analiza threads sin sobrecargar el navegador.
        </p>
      </div>

      <div class="mb-6 flex flex-wrap gap-3">
        <Button v-if="hasAppliedFilters" @click="openModal" variant="outline">
          <X class="mr-2 h-4 w-4" />
          Cambiar Filtros
        </Button>

        <Button @click="downloadReport" :disabled="isExporting || !filtersReady">
          <Download class="mr-2 h-4 w-4" />
          {{ isExporting ? 'Exportando...' : 'Exportar Excel' }}
        </Button>
      </div>

      <Dialog :open="showFilterModal" @update:open="showFilterModal = $event">
        <DialogContent class="sm:max-w-[520px]">
          <DialogHeader>
            <DialogTitle>Filtrar Reporte</DialogTitle>
            <DialogDescription>
              Selecciona compañía, canal y rango de fechas.
            </DialogDescription>
          </DialogHeader>

          <div class="space-y-6 py-6">
            <div class="space-y-2">
              <label for="company" class="text-sm font-medium">Compañía</label>
              <select
                id="company"
                v-model="selectedCompany"
                @change="onCompanyChange(selectedCompany)"
                class="w-full rounded-md border border-input bg-background px-3 py-2"
              >
                <option value="">Seleccione</option>
                <option v-for="company in companies" :key="company.id" :value="String(company.id)">
                  {{ company.company_name }}
                </option>
              </select>
            </div>

            <div class="space-y-2">
              <label for="channel" class="text-sm font-medium">Canal</label>
              <select
                id="channel"
                v-model="selectedChannel"
                :disabled="!selectedCompany"
                class="w-full rounded-md border border-input bg-background px-3 py-2"
              >
                <option value="">{{ selectedCompany ? 'Seleccione' : 'Primero selecciona una compañía' }}</option>
                <option v-for="channel in channels" :key="channel.id" :value="String(channel.id)">
                  {{ channel.channel_name }}
                </option>
              </select>
            </div>

            <div class="space-y-2">
              <label class="text-sm font-medium">Rango de fechas</label>
              <Popover>
                <PopoverTrigger as-child>
                  <Button
                    variant="outline"
                    :class="cn('w-full justify-start text-left font-normal', !startDate && 'text-muted-foreground')"
                  >
                    <CalendarIcon class="mr-2 h-4 w-4" />
                    <span>{{ formattedRange }}</span>
                  </Button>
                </PopoverTrigger>
                <PopoverContent class="w-auto p-0" align="start">
                  <RangeCalendar
                    v-model="dateRange"
                    :max-value="maxDate"
                    :min-value="minDate"
                    :number-of-months="2"
                    initial-focus
                  />
                </PopoverContent>
              </Popover>
            </div>
          </div>

          <DialogFooter>
            <Button @click="closeModal" variant="outline">Cancelar</Button>
            <Button @click="applyFilters" :disabled="isLoading">
              {{ isLoading ? 'Cargando...' : 'Aplicar Filtros' }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <div v-if="threads.length > 0" class="overflow-hidden rounded-lg border border-border bg-card">
        <div class="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Thread</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead>Fecha</TableHead>
                <TableHead>Canal</TableHead>
                <TableHead>Cliente</TableHead>
                <TableHead>Canal Cliente</TableHead>
                <TableHead>Asesor</TableHead>
                <TableHead>Cantidad</TableHead>
                <TableHead>Text</TableHead>
                <TableHead>Image</TableHead>
                <TableHead>Document</TableHead>
                <TableHead>Otro</TableHead>
                <TableHead>Bot</TableHead>
                <TableHead>Usuario</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="thread in threads" :key="thread.id">
                <TableCell class="font-medium">{{ thread.id }}</TableCell>
                <TableCell>
                  <Badge :class="getStatusBadgeClass(thread.thread_status)">{{ thread.thread_status }}</Badge>
                </TableCell>
                <TableCell class="text-xs text-muted-foreground">{{ thread.create_date }}</TableCell>
                <TableCell class="max-w-xs truncate text-sm">{{ thread.canal }}</TableCell>
                <TableCell class="max-w-xs truncate text-sm">{{ thread.cliente || 'N/A' }}</TableCell>
                <TableCell class="max-w-xs truncate text-sm">{{ thread.canal_cliente || 'N/A' }}</TableCell>
                <TableCell class="text-sm"><Badge>{{ thread.asesor || '-' }}</Badge></TableCell>
                <TableCell class="text-center">{{ thread.cantidad }}</TableCell>
                <TableCell class="text-center">{{ thread.text }}</TableCell>
                <TableCell class="text-center">{{ thread.image }}</TableCell>
                <TableCell class="text-center">{{ thread.document }}</TableCell>
                <TableCell class="text-center">{{ thread.otro }}</TableCell>
                <TableCell class="text-center">{{ thread.bot }}</TableCell>
                <TableCell class="text-center">{{ thread.usuario }}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-6 py-4 text-sm text-muted-foreground">
          <div>
            Mostrando <span class="font-semibold">{{ fromRow }}</span> -
            <span class="font-semibold">{{ toRow }}</span>
            de <span class="font-semibold">{{ totalRows }}</span> threads.
          </div>

          <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" :disabled="currentPage <= 1 || isLoading" @click="goToPreviousPage">
              <ChevronLeft class="mr-1 h-4 w-4" /> Anterior
            </Button>
            <span class="px-2 text-xs">Página {{ currentPage }} / {{ lastPage }}</span>
            <Button variant="outline" size="sm" :disabled="currentPage >= lastPage || isLoading" @click="goToNextPage">
              Siguiente <ChevronRight class="ml-1 h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>

      <div v-else class="py-12 text-center">
        <div class="space-y-2 text-muted-foreground">
          <p class="text-lg font-medium">No hay datos para mostrar</p>
          <p class="text-sm">Aplica filtros para ver threads.</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
