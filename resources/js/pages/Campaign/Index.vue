<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
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

const page = usePage()

const campaignsRaw = computed(() => page.props.campaigns as any)

const campaigns = computed(() => {
  const raw = campaignsRaw.value
  // si viene paginado -> raw.data
  if (raw && typeof raw === 'object' && Array.isArray(raw.data)) {
    return raw.data as Campaign[]
  }
  // si viene como array -> raw
  if (Array.isArray(raw)) return raw as Campaign[]
  return [] as Campaign[]
})
const logsAll = computed(() => (page.props.campaign_logs ?? page.props.logs ?? []) as CampaignLog[])
const recipientsAll = computed(() => (page.props.campaign_recipients ?? page.props.recipients ?? []) as CampaignRecipient[])

const initialSelected = (page.props.selectedCampaignId ?? null) as number | null
const selectedCampaignId = ref<number | null>(initialSelected ?? (campaigns.value[0]?.id ?? null))

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

const statusVariant = (s: string) => {
  const v = (s ?? '').toUpperCase()
  if (['SENT', 'FINALLY', 'READY', 'SCHEDULED'].includes(v)) return 'default'
  if (['RUNNING', 'PROCESSING', 'SENDING', 'UPLOADED'].includes(v)) return 'secondary'
  if (['FAILED', 'FINALLY_FAILED'].includes(v)) return 'destructive'
  return 'outline'
}

const safeJson = (v: any) => {
  try {
    if (typeof v === 'string') {
      // puede venir como JSON string
      const parsed = JSON.parse(v)
      return JSON.stringify(parsed, null, 2)
    }
    return JSON.stringify(v ?? {}, null, 2)
  } catch {
    return String(v ?? '')
  }
}

const openCampaign = (id: number) => {
  selectedCampaignId.value = id

  router.get(
    route('campaigns.index'),
    { campaign_id: id },
    { preserveScroll: true, replace: true }
  )
}
</script>

<template>
  <Head title="Campaigns" />

  <AppLayout>
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
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
        >
          Nueva campaña
        </Link>
      </div>

      <Tabs default-value="campaigns" class="w-full">
        <TabsList class="mb-4">
          <TabsTrigger value="campaigns">Campaigns</TabsTrigger>
          <TabsTrigger value="campaign_logs" :disabled="!selectedCampaignId">Campaign Logs</TabsTrigger>
          <TabsTrigger value="campaign_recipients" :disabled="!selectedCampaignId">Campaign Recipients</TabsTrigger>
        </TabsList>

        <!-- TAB: CAMPAIGNS -->
        <TabsContent value="campaigns">
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-12">
              <div v-if="!campaigns.length" class="text-sm text-muted-foreground">
                No hay campañas aún.
              </div>

              <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <Card
                  v-for="c in campaigns"
                  :key="c.id"
                  class="cursor-pointer hover:shadow-md transition"
                  :class="selectedCampaignId === c.id ? 'ring-2 ring-primary' : ''"
                  @click="openCampaign(c.id)"
                >
                  <CardHeader>
                    <div class="flex items-start justify-between gap-3">
                      <div>
                        <CardTitle class="text-lg">
                          #{{ c.id }} — {{ c.name }}
                        </CardTitle>
                        <CardDescription>
                          {{ c.description || 'Sin descripción' }}
                        </CardDescription>
                      </div>

                      <Badge :variant="statusVariant(c.status)">
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

                    <div class="grid gap-1">
                      <div>
                        <span class="text-muted-foreground">Vigencia:</span>
                        <span class="ml-1">{{ c.start_date }} → {{ c.end_date }}</span>
                      </div>

                      <div v-if="c.type === 'Programada' && c.start_time">
                        <span class="text-muted-foreground">Inicio:</span>
                        <span class="ml-1">{{ c.start_time }}</span>
                      </div>

                      <div class="text-xs text-muted-foreground">
                        Creada: {{ c.created_at }} · Actualizada: {{ c.updated_at }}
                      </div>
                    </div>
                  </CardContent>

                  <CardFooter class="justify-between">
                    <div class="text-xs text-muted-foreground">
                      Logs: {{ c.logs_count ?? 0 }}
                      · Recipients: {{ c.recipients_count ?? 0 }}
                      · Sent: {{ c.sent_count ?? 0 }}
                      · Pending: {{ c.pending_count ?? 0 }}
                      · Failed: {{ c.failed_count ?? 0 }}
                    </div>

                    <Button variant="secondary" size="sm" type="button">
                      Ver detalle
                    </Button>
                  </CardFooter>
                </Card>
              </div>
            </div>
          </div>
        </TabsContent>

        <!-- TAB: LOGS -->
        <TabsContent value="campaign_logs">
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-12">
              <Card>
                <CardHeader>
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <CardTitle>Logs</CardTitle>
                      <CardDescription v-if="selectedCampaign">
                        Campaña #{{ selectedCampaign.id }} — {{ selectedCampaign.name }}
                      </CardDescription>
                    </div>
                    <Badge v-if="selectedCampaign" :variant="statusVariant(selectedCampaign.status)">
                      {{ selectedCampaign.status }}
                    </Badge>
                  </div>
                </CardHeader>

                <CardContent>
                  <div v-if="!selectedCampaignId" class="text-sm text-muted-foreground">
                    Selecciona una campaña.
                  </div>

                  <div v-else-if="!selectedLogs.length" class="text-sm text-muted-foreground">
                    No hay logs para esta campaña.
                  </div>

                  <div v-else class="space-y-3">
                    <Card v-for="l in selectedLogs" :key="l.id">
                      <CardHeader class="py-4">
                        <div class="flex items-center justify-between gap-3">
                          <div class="flex items-center gap-2">
                            <Badge variant="outline">{{ l.type }}</Badge>
                            <span class="text-sm font-medium">#{{ l.id }}</span>
                          </div>
                          <span class="text-xs text-muted-foreground">{{ l.created_at }}</span>
                        </div>
                      </CardHeader>

                      <CardContent class="pt-0">
                        <p class="text-sm">{{ l.message }}</p>
                        <pre
                          v-if="l.meta"
                          class="mt-3 text-xs bg-muted rounded-md p-3 overflow-auto"
                        >{{ safeJson(l.meta) }}</pre>
                      </CardContent>
                    </Card>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </TabsContent>

        <!-- TAB: RECIPIENTS -->
        <TabsContent value="campaign_recipients">
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-12">
              <Card>
                <CardHeader>
                  <CardTitle>Recipients</CardTitle>
                  <CardDescription v-if="selectedCampaign">
                    Campaña #{{ selectedCampaign.id }} — {{ selectedCampaign.name }}
                  </CardDescription>
                </CardHeader>

                <CardContent>
                  <div v-if="!selectedCampaignId" class="text-sm text-muted-foreground">
                    Selecciona una campaña.
                  </div>

                  <div v-else-if="!selectedRecipients.length" class="text-sm text-muted-foreground">
                    No hay recipients para esta campaña.
                  </div>

                  <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <Card v-for="r in selectedRecipients" :key="r.id">
                      <CardHeader>
                        <div class="flex items-start justify-between gap-3">
                          <div>
                            <CardTitle class="text-base">#{{ r.id }} — {{ r.phone }}</CardTitle>
                            <CardDescription>
                              Upload: {{ r.campaign_upload_id }}
                            </CardDescription>
                          </div>

                          <Badge :variant="statusVariant(r.status)">
                            {{ r.status }}
                          </Badge>
                        </div>
                      </CardHeader>

                      <CardContent class="space-y-2">
                        <div class="text-xs text-muted-foreground">
                          {{ r.created_at }} → {{ r.updated_at }}
                        </div>

                        <div v-if="r.provider_message_id" class="text-sm">
                          <span class="text-muted-foreground">provider_message_id:</span>
                          <span class="ml-1 font-mono">{{ r.provider_message_id }}</span>
                        </div>

                        <div v-if="r.error_message" class="text-sm text-destructive">
                          {{ r.error_message }}
                        </div>

                        <pre class="text-xs bg-muted rounded-md p-3 overflow-auto">
                          {{ safeJson(r.variables) }}
                        </pre>
                      </CardContent>
                    </Card>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  </AppLayout>
</template>
