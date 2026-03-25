<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription,
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
import {
  Input,
} from '@/components/ui/input';
import { useToast } from '@/components/ui/toast/use-toast';
import { Badge } from '@/components/ui/badge';
import { Download, X, Calendar } from 'lucide-vue-next';
import { useTextFormat } from '@/composables/useTextFormat';

const { formatPEPlus5 } = useTextFormat();
const { toast } = useToast();

const showFilterModal = ref(true);
const companies = ref<any[]>([]);
const channels = ref<any[]>([]);
const interactions = ref<any[]>([]);
const isLoading = ref(false);
const selectedCompany = ref<string>('');
const selectedChannel = ref<string>('');
const startDate = ref<string>('');
const endDate = ref<string>('');

const fetchChannelsByCompany = async (companyId: string) => {
  channels.value = [];
  selectedChannel.value = '';

  if (!companyId) {
    return;
  }

  const channelsRes = await axios.get('/reports/filters/channels', {
    params: { company_id: companyId },
  });

  channels.value = channelsRes.data;
};

// Obtener compañías y canales al cargar
onMounted(async () => {
  try {
    const companiesRes = await axios.get('/reports/filters/companies');
    companies.value = companiesRes.data;

    if (companies.value.length === 1) {
      selectedCompany.value = String(companies.value[0].id);
      await fetchChannelsByCompany(selectedCompany.value);
    }
  } catch (error) {
    toast({
      title: 'Error',
      description: 'No se pudieron cargar las compañías y canales',
      variant: 'destructive',
    });
  }
});

const onCompanyChange = async (companyId: string) => {
  selectedCompany.value = companyId;

  try {
    await fetchChannelsByCompany(companyId);
  } catch (error) {
    toast({
      title: 'Error',
      description: 'No se pudieron cargar los canales de la compañía seleccionada',
      variant: 'destructive',
    });
  }
};

const onChannelChange = (channelId: string) => {
  selectedChannel.value = channelId;
};

const applyFilters = async () => {
  if (!startDate.value || !endDate.value || !selectedCompany.value || !selectedChannel.value) {
    toast({
      title: 'Validación',
      description: 'Por favor completa todos los campos',
      variant: 'destructive',
    });
    return;
  }

  isLoading.value = true;
  try {
    const response = await axios.get('/reports/interactions/data', {
      params: {
        start_date: startDate.value,
        end_date: endDate.value,
        company_id: selectedCompany.value,
        communication_channel_id: selectedChannel.value,
      },
    });

    interactions.value = response.data.data;
    showFilterModal.value = false;

    if (interactions.value.length === 0) {
      toast({
        title: 'Sin resultados',
        description: 'No se encontraron interacciones para los filtros seleccionados',
      });
    } else {
      toast({
        title: 'Éxito',
        description: `Se cargaron ${interactions.value.length} interacciones`,
      });
    }
  } catch (error: any) {
    toast({
      title: 'Error',
      description: error.response?.data?.message || 'Error al cargar las interacciones',
      variant: 'destructive',
    });
  } finally {
    isLoading.value = false;
  }
};

const closeModal = () => {
  showFilterModal.value = false;
};

const openModal = () => {
  showFilterModal.value = true;
};

const downloadReport = async () => {
  if (interactions.value.length === 0) {
    toast({
      title: 'Sin datos',
      description: 'No hay datos para descargar',
      variant: 'destructive',
    });
    return;
  }

  try {
    // Crear CSV
    const headers = [
      'Thread',
      'Año',
      'Mes',
      'Fecha Thread',
      'Cod Interacción',
      'Fecha Interacción',
      'Canal',
      'Asesor',
      'Intención',
      'Tipo Interacción',
      'Texto Interacción',
      'Grupo',
      'Persona',
      'Nombre Original',
      'Número Cliente',
      'Correo',
      'Canal Comunicación',
    ];

    const rows = interactions.value.map(interaction => [
      interaction.thread,
      interaction.anio,
      interaction.mes,
      interaction.fecha_thread,
      interaction.cod_interaccion,
      interaction.fecha_interaccion,
      interaction.canal,
      interaction.asesor,
      interaction.intencion,
      interaction.tipo_interaccion,
      interaction.texto_interaccion || '',
      interaction.grupo,
      interaction.persona,
      interaction.nombre_original,
      interaction.numero_cliente,
      interaction.correo,
      interaction.canal_comunicacion,
    ]);

    let csv = headers.join(',') + '\n';
    rows.forEach(row => {
      csv += row.map(cell => {
        if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n'))) {
          return `"${cell.replace(/"/g, '""')}"`;
        }
        return cell;
      }).join(',') + '\n';
    });

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `interacciones_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    window.URL.revokeObjectURL(url);

    toast({
      title: 'Éxito',
      description: 'Reporte descargado correctamente',
    });
  } catch (error) {
    toast({
      title: 'Error',
      description: 'Error al descargar el reporte',
      variant: 'destructive',
    });
  }
};

const getStatusBadgeClass = (status: string) => {
  const statusMap: Record<string, string> = {
    OPEN: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100',
    CLOSED: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100',
    SPAM: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100',
  };
  return statusMap[status] || 'bg-gray-100 text-gray-800';
};

const getOriginBadgeClass = (origin: string) => {
  const originMap: Record<string, string> = {
    APP: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-100',
    WHATSAPP: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100',
    API: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-100',
  };
  return originMap[origin] || 'bg-gray-100 text-gray-800';
};
</script>

<template>
  <div class="min-h-screen bg-background p-6">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-3xl font-bold tracking-tight">Reporte de Interacciones</h1>
      <p class="text-muted-foreground mt-2">
        Visualiza y analiza todas las interacciones en tus canales de comunicación
      </p>
    </div>

    <!-- Action Buttons -->
    <div class="flex gap-3 mb-6">
      <Button
        v-if="interactions.length > 0"
        @click="openModal"
        variant="outline"
      >
        <X class="w-4 h-4 mr-2" />
        Cambiar Filtros
      </Button>
      <Button
        v-if="interactions.length > 0"
        @click="downloadReport"
        variant="default"
      >
        <Download class="w-4 h-4 mr-2" />
        Descargar CSV
      </Button>
    </div>

    <!-- Filter Modal -->
    <Dialog :open="showFilterModal" @update:open="showFilterModal = $event">
      <DialogContent class="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>Filtrar Conversaciones</DialogTitle>
          <DialogDescription>
            Selecciona el rango de fechas, compañía y canal para ver las interacciones
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-6 py-6">
          <!-- Company -->
          <div class="space-y-2">
            <label for="company" class="text-sm font-medium">Compañía</label>
            <select
              id="company"
              v-model="selectedCompany"
              @change="onCompanyChange(selectedCompany)"
              class="w-full px-3 py-2 border border-input rounded-md bg-background"
            >
              <option value="">Selecciona una compañía</option>
              <option v-for="company in companies" :key="company.id" :value="String(company.id)">
                {{ company.company_name }}
              </option>
            </select>
          </div>

          <!-- Channel -->
          <div class="space-y-2">
            <label for="channel" class="text-sm font-medium">Canal</label>
            <select
              id="channel"
              v-model="selectedChannel"
              @change="onChannelChange(selectedChannel)"
              :disabled="!selectedCompany"
              class="w-full px-3 py-2 border border-input rounded-md bg-background"
            >
              <option value="">{{ selectedCompany ? 'Selecciona un canal' : 'Primero selecciona una compañía' }}</option>
              <option v-for="channel in channels" :key="channel.id" :value="String(channel.id)">
                {{ channel.channel_name }}
              </option>
            </select>
          </div>

          <!-- Date Range -->
          <div class="space-y-2">
            <label class="text-sm font-medium flex items-center gap-2">
              <Calendar class="w-4 h-4" />
              Rango de Fechas
            </label>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label for="start-date" class="text-xs text-muted-foreground mb-1 block">Desde</label>
                <Input
                  id="start-date"
                  v-model="startDate"
                  type="date"
                />
              </div>
              <div>
                <label for="end-date" class="text-xs text-muted-foreground mb-1 block">Hasta</label>
                <Input
                  id="end-date"
                  v-model="endDate"
                  type="date"
                />
              </div>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button @click="closeModal" variant="outline">
            Cancelar
          </Button>
          <Button @click="applyFilters" :disabled="isLoading">
            {{ isLoading ? 'Cargando...' : 'Aplicar Filtros' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Results Table -->
    <div v-if="interactions.length > 0" class="bg-card rounded-lg border border-border overflow-hidden">
      <div class="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Thread</TableHead>
              <TableHead>Fecha</TableHead>
              <TableHead>Cod Interacción</TableHead>
              <TableHead>Canal</TableHead>
              <TableHead>Asesor</TableHead>
              <TableHead>Tipo</TableHead>
              <TableHead>Grupo</TableHead>
              <TableHead>Cliente</TableHead>
              <TableHead>Número</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="interaction in interactions" :key="interaction.cod_interaccion">
              <TableCell class="font-medium">{{ interaction.thread }}</TableCell>
              <TableCell class="text-xs text-muted-foreground">
                {{ formatPEPlus5(interaction.fecha_interaccion) }}
              </TableCell>
              <TableCell class="font-mono text-sm">{{ interaction.cod_interaccion }}</TableCell>
              <TableCell class="text-sm truncate max-w-xs">
                {{ interaction.canal }}
              </TableCell>
              <TableCell class="text-sm">
                <Badge>{{ interaction.asesor }}</Badge>
              </TableCell>
              <TableCell class="text-sm">
                <Badge :class="getOriginBadgeClass(interaction.intencion)">
                  {{ interaction.intencion }}
                </Badge>
              </TableCell>
              <TableCell>
                <Badge :class="getStatusBadgeClass(interaction.grupo)">
                  {{ interaction.grupo }}
                </Badge>
              </TableCell>
              <TableCell class="text-sm truncate max-w-xs">
                {{ interaction.persona || 'N/A' }}
              </TableCell>
              <TableCell class="text-sm">
                {{ interaction.numero_cliente || 'N/A' }}
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
      <div class="px-6 py-4 border-t border-border text-sm text-muted-foreground">
        Total de interacciones: <span class="font-bold">{{ interactions.length }}</span>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
      <div class="text-muted-foreground space-y-2">
        <p class="text-lg font-medium">No hay datos para mostrar</p>
        <p class="text-sm">Aplica filtros para ver las interacciones</p>
      </div>
    </div>
  </div>
</template>
