<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { FileSpreadsheet, Download, CalendarRange, Clock, CheckCircle2, XCircle, Loader2, SquarePlus } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useAppearance } from '@/composables/useAppearance'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs'

type Campaign = {
  id: number
  name: string
  description?: string | null
  company_id: number
  communication_channel_id: number
  template_id: number
  start_date: string
  end_date: string
  start_time?: string | null
  status: string
  type: 'Manual' | 'Programada' | string
  created_at: string
  updated_at: string
  logs_count?: number
  recipients_count?: number
  sent_count?: number
  failed_count?: number
  pending_count?: number
}

type CampaignLog = {
  id: number
  campaign_id: number
  type: string
  message: string
  meta: any
  created_at: string
}

type CampaignRecipient = {
  id: number
  campaign_id: number
  campaign_upload_id: number
  phone: string
  variables: any
  status: string
  provider_message_id?: string | null
  error_message?: string | null
  created_at: string
  updated_at: string
}

type PaginatorLink = {
  url: string | null
  label: string
  active: boolean
}

type Paginator<T> = {
  data: T[]
  links: PaginatorLink[]
  current_page: number
  from: number | null
  to: number | null
  last_page: number
  per_page: number
  total: number
}

type PageProps = {
  campaigns: Paginator<Campaign>
  campaign_logs: CampaignLog[]
  campaign_recipients: CampaignRecipient[]
  selectedCampaignId: number | null
  config: {
    hsmBaseUrl: string
  }
}

type HsmStatusItem = {
  status: string
  datetime: string
  order: number
}

type HsmStatusResponse =
  | { success: true; memberData: { phone: string; statusList: HsmStatusItem[] } }
  | { success: false; message: string }

const statusModalOpen = ref(false)
const statusLoading = ref(false)
const statusError = ref<string | null>(null)
const statusPayload = ref<{ phone: string; list: HsmStatusItem[] } | null>(null)
const statusMessageId = ref<string | null>(null)

let statusAbort: AbortController | null = null

const openHsmStatus = async (providerMessageId: string | null) => {
  if (!providerMessageId) return

  statusAbort?.abort()
  statusAbort = new AbortController()

  statusModalOpen.value = true
  statusLoading.value = true
  statusError.value = null
  statusPayload.value = null
  statusMessageId.value = providerMessageId

  try {
    const url = `${hsmBaseUrl.value}/hsm/${encodeURIComponent(providerMessageId)}/status`

    const res = await fetch(url, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      signal: statusAbort.signal,
    })

    const json = (await res.json()) as HsmStatusResponse

    if (!json.success) {
      statusError.value = json.message || 'Status not found'
      return
    }

    const list = (json.memberData?.statusList ?? [])
      .slice()
      .sort((a, b) => (a.order ?? 0) - (b.order ?? 0))

    statusPayload.value = {
      phone: json.memberData.phone,
      list,
    }
  } catch (e: any) {
    if (e?.name === 'AbortError') return
    statusError.value = 'No se pudo consultar el status.'
  } finally {
    statusLoading.value = false
  }
}

const page = usePage<PageProps>()
const hsmBaseUrl = computed(() => page.props.config?.hsmBaseUrl ?? '')
const { statusVariant, badgeClass, statusBadgeClass } = useAppearance()

const campaignsPaginator = computed(() => page.props.campaigns)
const campaigns = computed(() => campaignsPaginator.value.data)
const campaignsLinks = computed(() => campaignsPaginator.value.links)

const logsAll = computed(() => (page.props.campaign_logs ?? []) as CampaignLog[])
const recipientsAll = computed(() => (page.props.campaign_recipients ?? []) as CampaignRecipient[])
const initialSelected = (page.props.selectedCampaignId ?? null) as number | null
const selectedCampaignId = ref<number | null>(initialSelected ?? (campaigns.value[0]?.id ?? null))

const breadcrumbs = [
  {
    title: 'Campaigns',
    href: '/campaigns',
  }
]

const logIcon = (type: string) => {
  const t = (type ?? '').toUpperCase()
  if (t === 'FINISHED') return CheckCircle2
  if (t === 'FAILED') return XCircle
  if (t === 'PROCESSING') return Loader2
  return Clock 
}

const logBadgeClass = (type: string) => {
  const t = (type ?? '').toUpperCase()
  if (t === 'FINISHED') return 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30'
  if (t === 'PROCESSING') return 'bg-amber-500/15 text-amber-300 border border-amber-500/30'
  if (t === 'UPLOAD') return 'bg-sky-500/15 text-sky-300 border border-sky-500/30'
  if (t === 'FAILED') return 'bg-rose-500/15 text-rose-300 border border-rose-500/30'
  return 'bg-muted text-foreground border border-border'
}

watch(
  () => campaigns.value,
  (arr) => {
    if (!selectedCampaignId.value && arr.length) selectedCampaignId.value = arr[0].id
  },
  { immediate: true }
)

const selectedCampaign = computed(() =>
  campaigns.value.find(c => c.id === selectedCampaignId.value) ?? null
)

const selectedLogs = computed(() =>
  logsAll.value.filter(l => l.campaign_id === selectedCampaignId.value)
)

const selectedRecipients = computed(() =>
  recipientsAll.value.filter(r => r.campaign_id === selectedCampaignId.value)
)

const parseMaybeJson = (v: any) => {
  if (v == null) return null
  if (typeof v === 'string') {
    try {
      return JSON.parse(v)
    } catch {
      return v
    }
  }
  return v
}

const pad2 = (n: number) => String(n).padStart(2, '0')
const toDateSafe = (value: string) => {
  if (!value) return null
  // ISO -> ok
  if (value.includes('T')) {
    const d = new Date(value)
    return isNaN(d.getTime()) ? null : d
  }
  // "YYYY-MM-DD HH:mm:ss" -> "YYYY-MM-DDTHH:mm:ss"
  const d = new Date(value.replace(' ', 'T'))
  return isNaN(d.getTime()) ? null : d
}

const formatDateTime = (value: string) => {
  const d = toDateSafe(value)
  if (!d) return value
  return `${pad2(d.getDate())}/${pad2(d.getMonth() + 1)}/${d.getFullYear()} ${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`
}

const formatDate = (value: string) => {
  const d = toDateSafe(value)
  if (!d) return value
  return `${pad2(d.getDate())}/${pad2(d.getMonth() + 1)}/${d.getFullYear()}`
}

const formatHHmm = (value: string) => {
  if (!value) return value
  // start_time puede venir "10:55:00"
  return value.slice(0, 5)
}

const logMeta = (l: CampaignLog) => parseMaybeJson(l.meta)

const uploadDownload = (l: CampaignLog) => {
  const meta = logMeta(l)
  if (!meta || typeof meta !== 'object') return null
  if (!meta.download_url) return null
  return {
    url: meta.download_url as string,
    upload_id: meta.upload_id ?? null,
    file: meta.file ?? null,
  }
}

const currentPage = computed(() => {
  const qs = (page.url.split('?')[1] ?? '')
  const p = new URLSearchParams(qs).get('page')
  return p ? Number(p) : campaignsPaginator.value.current_page ?? 1
})

const activeTab = ref<'campaigns' | 'campaign_logs' | 'campaign_recipients'>('campaigns')

const goToRecipientsTab = (campaignId: number) => {
  openCampaign(campaignId)
  activeTab.value = 'campaign_recipients'
}

const openCampaign = (id: number) => {
  selectedCampaignId.value = id

  router.get(
    route('campaigns.index'),
    { campaign_id: id, page: currentPage.value },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

const goToCampaignPage = (url: string | null) => {
  if (!url) return

  const u = new URL(url, window.location.origin)
  if (selectedCampaignId.value && !u.searchParams.get('campaign_id')) {
    u.searchParams.set('campaign_id', String(selectedCampaignId.value))
  }

  router.get(u.pathname + u.search, {}, { preserveState: true, preserveScroll: true, replace: true })
}

</script>

<template>
  <Head title="Campaigns" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="w-full py-6 sm:px-6 lg:px-8">
    <div class="max-w-7xl">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h1 class="text-2xl font-semibold">Campañas</h1>
          <p class="text-sm text-muted-foreground">
            Gestiona campañas, logs y destinatarios.
          </p>
        </div>

        <Link
          :href="route('campaigns.create')"
          class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-primary text-primary-foreground hover:bg-primary/90"
          title="Nueva Campaña"
        >
         <SquarePlus class="w-5 h-5" />
        </Link>
      </div>

      <Tabs v-model="activeTab" class="w-full">
        <TabsList class="mb-4">
          <TabsTrigger value="campaigns">Campaigns</TabsTrigger>
          <TabsTrigger value="campaign_logs" :disabled="!selectedCampaignId">Campaign Logs</TabsTrigger>
          <TabsTrigger value="campaign_recipients" :disabled="!selectedCampaignId">Campaign Recipients</TabsTrigger>
        </TabsList>
        <!-- TAB: CAMPAIGNS -->
        <TabsContent value="campaigns" class="w-full">
          <div v-if="!campaigns.length" class="text-sm text-muted-foreground">
            No hay campañas aún.
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <Card
              v-for="c in campaigns"
              :key="c.id"
              class="cursor-pointer hover:shadow-md transition w-full"
              :class="selectedCampaignId === c.id ? 'ring-2 ring-primary' : ''"
              @click="openCampaign(c.id)"
            >
              <CardHeader>
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <CardTitle class="text-lg">#{{ c.id }} — {{ c.name }}</CardTitle>
                    <CardDescription>{{ c.description || 'Sin descripción' }}</CardDescription>
                  </div>

                  <Badge :variant="statusVariant(c.status)" class="border" :class="badgeClass(c.status)">
                    {{ c.status }}
                  </Badge>
                </div>
              </CardHeader>

              <CardContent class="space-y-2 text-sm">
                <div class="flex flex-wrap gap-2">
                  <Badge variant="outline">Tipo: {{ c.type }}</Badge>
                  <Badge variant="outline">Template: {{ c.template_id }}</Badge>
                  <Badge variant="outline">Canal: {{ c.communication_channel_id }}</Badge>
                </div>

                <div class="grid gap-2 text-sm">
                  <div class="flex items-center gap-2 text-muted-foreground">
                    <CalendarRange class="h-4 w-4" />
                    <span class="text-foreground">
                      Vigencia: {{ formatDate(c.start_date) }} → {{ formatDate(c.end_date) }}
                    </span>
                  </div>

                  <div v-if="c.type === 'Programada' && c.start_time" class="flex items-center gap-2 text-muted-foreground">
                    <Clock class="h-4 w-4" />
                    <span class="text-foreground">Inicio: {{ formatHHmm(c.start_time) }}</span>
                  </div>

                  <div class="text-xs text-muted-foreground">
                    Creada: {{ formatDateTime(c.created_at) }} · Actualizada: {{ formatDateTime(c.updated_at) }}
                  </div>
                </div>
              </CardContent>

              <CardFooter class="justify-between">
                <div class="text-xs text-muted-foreground">
                  Logs: {{ c.logs_count ?? logsAll.filter(l => l.campaign_id === c.id).length }}
                  · Recipients: {{ c.recipients_count ?? recipientsAll.filter(r => r.campaign_id === c.id).length }}
                  <template v-if="c.sent_count != null">
                    · Sent: {{ c.sent_count }} · Pending: {{ c.pending_count }} · Failed: {{ c.failed_count }}
                  </template>
                </div>

                <Button
                  variant="secondary"
                  size="sm"
                  type="button"
                  @click.stop="goToRecipientsTab(c.id)"
                >
                  Ver detalle
                </Button>
              </CardFooter>
            </Card>
          </div>

          <!-- Pagination -->
          <div v-if="campaignsPaginator.last_page > 1" class="mt-6 flex items-center justify-between gap-3">
            <p class="text-xs text-muted-foreground">
              Mostrando {{ campaignsPaginator.from ?? 0 }}–{{ campaignsPaginator.to ?? 0 }}
              de {{ campaignsPaginator.total ?? 0 }}
            </p>

            <div class="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                :disabled="!campaignsLinks[0]?.url"
                @click="goToCampaignPage(campaignsLinks[0]?.url)"
              >
                «
              </Button>

              <Button
                v-for="(l, i) in campaignsLinks"
                :key="i"
                variant="outline"
                size="sm"
                class="min-w-9"
                :class="l.active ? 'bg-muted' : ''"
                :disabled="!l.url || l.label.includes('Previous') || l.label.includes('Next')"
                @click="goToCampaignPage(l.url)"
                v-html="l.label"
              />

              <Button
                variant="outline"
                size="sm"
                :disabled="!campaignsLinks[campaignsLinks.length - 1]?.url"
                @click="goToCampaignPage(campaignsLinks[campaignsLinks.length - 1]?.url)"
              >
                »
              </Button>
            </div>
          </div>
        </TabsContent>

        <TabsContent value="campaign_logs" class="w-full">
          <div v-if="!selectedCampaignId" class="text-sm text-muted-foreground">
            Selecciona una campaña.
          </div>

          <div v-else class="w-full">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start w-full">
              <!-- HEADER (ocupa todo el ancho del grid) -->
              <div class="lg:col-span-12 flex items-start justify-between">
                <div>
                  <h2 class="text-xl font-semibold">Logs</h2>
                  <p v-if="selectedCampaign" class="text-sm text-muted-foreground">
                    Campaña #{{ selectedCampaign.id }} — {{ selectedCampaign.name }}
                  </p>
                </div>

              </div>

              <!-- IZQUIERDA: timeline -->
              <div class="lg:col-span-8 w-full">
                <div v-if="!selectedLogs.length" class="text-sm text-muted-foreground">
                  No hay logs para esta campaña.
                </div>

                <div v-else class="relative pl-6 w-full">
                  <div class="absolute left-2 top-0 bottom-0 w-px bg-border/60" />

                  <div v-for="l in selectedLogs" :key="l.id" class="relative mb-4">
                    <div class="absolute -left-[3px] top-4 h-3 w-3 rounded-full border bg-background" />

                    <Card class="w-full">
                      <CardHeader class="py-4">
                        <div class="flex items-center justify-between gap-3 w-full">
                          <div class="flex items-center gap-2">
                            <component
                              :is="logIcon(l.type)"
                              class="h-4 w-4"
                              :class="(l.type ?? '').toUpperCase() === 'PROCESSING' ? 'animate-spin' : ''"
                            />
                            <Badge variant="outline" class="border" :class="logBadgeClass(l.type)">
                              {{ l.type }}
                            </Badge>
                            <span class="text-sm font-medium">#{{ l.id }}</span>
                          </div>

                          <span class="text-xs text-muted-foreground">
                            {{ formatDateTime(l.created_at) }}
                          </span>
                        </div>
                      </CardHeader>

                      <CardContent class="pt-0 space-y-3">
                        <p class="text-sm">{{ l.message }}</p>
                        <!-- UPLOAD -->
                        <div
                          v-if="(l.type ?? '').toUpperCase() === 'UPLOAD' && uploadDownload(l)"
                          class="flex items-center justify-between gap-3"
                        >
                          <div class="flex items-center gap-2 text-sm text-muted-foreground">
                            <FileSpreadsheet class="h-4 w-4" />
                            <span>Archivo Excel</span>
                          </div>

                          <a
                            :href="uploadDownload(l)!.url"
                            class="inline-flex items-center gap-2 text-sm px-3 py-2 rounded-md border hover:bg-muted transition"
                            target="_blank"
                            rel="noopener"
                          >
                            <Download class="h-4 w-4" />
                            Descargar
                          </a>
                        </div>
                      </CardContent>
                    </Card>
                  </div>
                </div>
              </div>

              <!-- DERECHA: resumen -->
              <div class="lg:col-span-4 w-full">
                <Card class="w-full lg:sticky lg:top-24">
                  <CardHeader>
                    <CardTitle class="text-base">Resumen</CardTitle>
                  </CardHeader>

                  <CardContent class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                      <span class="text-muted-foreground">Status</span>
                      <Badge class="border" :class="badgeClass(selectedCampaign?.status ?? '')">
                        {{ selectedCampaign?.status }}
                      </Badge>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                      <span class="text-muted-foreground">Total</span>
                      <Badge class="font-medium">{{ selectedCampaign?.recipients_count ?? 0 }}</Badge>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                      <span class="text-muted-foreground">Enviado</span>
                      <Badge class="font-medium">{{ selectedCampaign?.sent_count ?? 0 }}</Badge>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                      <span class="text-muted-foreground">Fallido</span>
                      <Badge class="font-medium">{{ selectedCampaign?.failed_count ?? 0 }}</Badge>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>
          </div>
        </TabsContent>

        <!-- TAB: RECIPIENTS (alineado a la izquierda / ancho completo) -->
        <TabsContent value="campaign_recipients" class="w-full">
          <div v-if="!selectedCampaignId" class="text-sm text-muted-foreground">
            Selecciona una campaña.
          </div>

          <div v-else class="w-full flex flex-col items-start">
            <div class="mb-4 w-full">
              <h2 class="text-xl font-semibold">Destinatarios</h2>

              <div class="mt-1 flex items-center justify-between gap-3">
                <p v-if="selectedCampaign" class="text-sm text-muted-foreground">
                  Campaña #{{ selectedCampaign.id }} — {{ selectedCampaign.name }}
                </p>

                <Button
                  v-if="selectedCampaignId"
                  variant="outline"
                  size="sm"
                  as-child
                  class="shrink-0"
                >
                  <a
                    :href="route('campaigns.recipients.export', selectedCampaignId)"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-2"
                  >
                    <FileSpreadsheet class="h-4 w-4" />
                    Exportar
                  </a>
                </Button>
              </div>
            </div>

            <div v-if="!selectedRecipients.length" class="text-sm text-muted-foreground">
              No hay recipients para esta campaña.
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 w-full">
              <Card v-for="r in selectedRecipients" :key="r.id" class="w-full">
                <CardHeader>
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <CardTitle class="text-base">#{{ r.id }} — {{ r.phone }}</CardTitle>
                    </div>

                    <Badge :variant="statusVariant(r.status)" class="border" :class="badgeClass(r.status)">
                      {{ r.status }}
                    </Badge>
                  </div>
                </CardHeader>

                <CardContent class="space-y-2">
                  <div class="text-xs text-muted-foreground">
                    {{ formatDateTime(r.created_at) }} → {{ formatDateTime(r.updated_at) }}
                  </div>

                  <div v-if="r.provider_message_id" class="text-sm">
                    <span class="text-muted-foreground">Status:</span>
                    <button
                      v-if="r.provider_message_id"
                      type="button"
                      class="ml-1 font-mono underline underline-offset-4 hover:text-primary transition"
                      @click.stop="openHsmStatus(r.provider_message_id)"
                    >
                      {{ r.provider_message_id }}
                    </button>
                  </div>

                  <div v-if="r.error_message" class="text-sm text-destructive">
                    {{ r.error_message }}
                  </div>

                  <pre class="text-xs bg-muted rounded-md p-3 overflow-auto">{{ parseMaybeJson(r.variables) ? JSON.stringify(parseMaybeJson(r.variables), null, 2) : '' }}</pre>
                </CardContent>
              </Card>
            </div>
          </div>
        </TabsContent>
      </Tabs>
    </div>
    </div>

    <Dialog v-model:open="statusModalOpen">
      <DialogContent class="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>Estado del mensaje</DialogTitle>
          <DialogDescription>
            <span v-if="statusMessageId" class="font-mono text-xs">{{ statusMessageId }}</span>
            <span v-if="statusPayload?.phone" class="ml-2 text-xs text-muted-foreground">
              · {{ statusPayload.phone }}
            </span>
          </DialogDescription>
        </DialogHeader>

        <div class="mt-2">
          <div v-if="statusLoading" class="text-sm text-muted-foreground">
            Consultando status...
          </div>

          <div v-else-if="statusError" class="text-sm text-destructive">
            {{ statusError }}
          </div>

          <div v-else-if="statusPayload" class="relative pl-6">
            <div class="absolute left-2 top-0 bottom-0 w-px bg-border/60" />

            <div v-for="(it, idx) in statusPayload.list" :key="idx" class="relative py-3">
              <div class="absolute -left-[3px] top-5 h-3 w-3 rounded-full border bg-background" />

              <div class="flex items-center justify-between gap-3">
                <Badge variant="outline" class="border" :class="statusBadgeClass(it.status)">
                  {{ it.status }}
                </Badge>

                <span class="text-xs text-muted-foreground">
                  {{ formatDateTime(it.datetime) }}
                </span>
              </div>
            </div>

            <div v-if="!statusPayload.list.length" class="text-sm text-muted-foreground">
              No hay estados para mostrar.
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>

  </AppLayout>
</template>
