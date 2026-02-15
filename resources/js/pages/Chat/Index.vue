<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed, ref, watch, nextTick } from 'vue'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Separator } from '@/components/ui/separator'

import { Label } from '@/components/ui/label'
import { RangeCalendar } from '@/components/ui/range-calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger
} from '@/components/ui/dialog'

import { Search, Send, Paperclip, MoreVertical, Filter, CalendarIcon } from 'lucide-vue-next'
import { today, getLocalTimeZone } from '@internationalized/date'
import { format } from 'date-fns'
import axios from 'axios'

type ThreadRow = {
  // threads (a.*)
  id: number
  company_id: number
  communication_channel_id: number
  thread_status: 'OPEN' | 'CLOSED' | string
  first_conversation_date: string | null
  last_conversation_date: string | null

  // messages (b.*)
  thread_id: number
  item_type: string
  item_content: string
  create_date_2?: string // si tu api renombra, ignora
  create_date?: string // según payload
  message_created_at?: string // ideal
  external_id?: string

  // customer (c.*)
  name?: string | null
  phone?: string | null

  enviado_por?: 'USUARIO' | 'BOT' | string
}

type Thread = {
  thread_id: number
  thread_status: string
  name: string
  phone: string
  last_message: string
  last_at: string
  messages: ThreadMessage[]
}

type ThreadMessage = {
  id: string // key local
  sender: 'me' | 'them'
  text: string
  created_at: string
  item_type: string
}

const page = usePage()

// ✅ IMPORTANTE: /chat debe enviar companies igual que campaigns
const companies = (page.props.companies ?? []) as { id: number; company_name: string }[]

const channels = ref<{ id: number; channel_name: string }[]>([])

const filtersOpen = ref(false)
const q = ref('')

// filtros
const filters = ref({
  company_id: 1 as number | '',
  communication_channel_id: 3 as number | '',
  date_start: '2026-01-15',
  date_end: '2026-02-14',
})

const dateRange = ref<any>(undefined)
const tz = getLocalTimeZone()
const minDate = today(tz).subtract({ days: 365 })
const maxDate = today(tz).add({ days: 365 })

const formattedRange = computed(() => {
  if (!filters.value.date_start || !filters.value.date_end) return 'Selecciona rango'
  return `${filters.value.date_start} — ${filters.value.date_end}`
})

// datos reales
const rows = ref<ThreadRow[]>([])
const loading = ref(false)

const threads = computed<Thread[]>(() => {
  // Agrupar por thread_id
  const map = new Map<number, Thread>()

  for (const r of rows.value) {
    const threadId = r.thread_id ?? r.id
    if (!threadId) continue

    if (!map.has(threadId)) {
      map.set(threadId, {
        thread_id: threadId,
        thread_status: r.thread_status ?? '—',
        name: (r.name && r.name !== 'undefined') ? r.name : (r.phone ?? `#${threadId}`),
        phone: r.phone ?? '',
        last_message: '',
        last_at: '',
        messages: [],
      })
    }

    const t = map.get(threadId)!

    // created_at del mensaje (ajusta si tu api devuelve otra columna)
    const msgAt =
      // si tu backend lo manda como "create_date"
      (r.create_date as string) ??
      // o si lo renombraste
      (r.create_date_2 as string) ??
      ''

    // sender: USUARIO => them, BOT => me (ajusta si quieres)
    const sender: 'me' | 'them' = r.enviado_por === 'USUARIO' ? 'them' : 'me'

    // solo mensajes con contenido
    if (r.item_content) {
      t.messages.push({
        id: `${threadId}-${t.messages.length}`,
        sender,
        text: r.item_content,
        created_at: msgAt ? new Date(msgAt).toLocaleString() : '',
        item_type: r.item_type ?? 'text',
      })
    }
  }

  // calcular last_message / last_at
  for (const t of map.values()) {
    const last = t.messages.at(-1)
    t.last_message = last?.text ?? ''
    t.last_at = last?.created_at ?? ''
  }

  // ordenar hilos por thread_id desc (o por last_at si tu api lo manda bien)
  return [...map.values()].sort((a, b) => b.thread_id - a.thread_id)
})

const filteredThreads = computed(() => {
  const term = q.value.trim().toLowerCase()
  if (!term) return threads.value
  return threads.value.filter(t =>
    `${t.name} ${t.phone} ${t.last_message}`.toLowerCase().includes(term)
  )
})

const activeThreadId = ref<number | null>(null)

watch(
  threads,
  (list) => {
    if (!activeThreadId.value && list.length) activeThreadId.value = list[0].thread_id
  },
  { immediate: true }
)

const activeThread = computed(() =>
  threads.value.find(t => t.thread_id === activeThreadId.value) ?? null
)

const activeMessages = computed(() => activeThread.value?.messages ?? [])

// scroll
const scrollerRef = ref<HTMLElement | null>(null)
const scrollToBottom = async () => {
  await nextTick()
  const el = scrollerRef.value
  if (!el) return
  el.scrollTop = el.scrollHeight
}

watch(activeThreadId, async () => {
  await scrollToBottom()
})

// cargar canales por company (igual que campaigns)
watch(
  () => filters.value.company_id,
  async (companyId) => {
    filters.value.communication_channel_id = ''
    channels.value = []
    if (!companyId) return

    const { data } = await axios.get(`/campaigns/companies/${companyId}/channels`)
    channels.value = data
  },
  { immediate: true }
)

// calendario → strings
watch(dateRange, (range) => {
  if (!range?.start || !range?.end) {
    filters.value.date_start = ''
    filters.value.date_end = ''
    return
  }
  const start = range.start.toDate(tz)
  const end = range.end.toDate(tz)
  filters.value.date_start = format(start, 'yyyy-MM-dd')
  filters.value.date_end = format(end, 'yyyy-MM-dd')
})

const applyFilters = async () => {
  filtersOpen.value = false
  await fetchThreads()
}

const resetFilters = async () => {
  filters.value.company_id = 1
  filters.value.communication_channel_id = 3
  filters.value.date_start = '2026-01-15'
  filters.value.date_end = '2026-02-14'
  dateRange.value = undefined
  await fetchThreads()
}

const fetchThreads = async () => {
  loading.value = true
  try {
    // ✅ endpoint sugerido (lo haces en Laravel)
    // GET /chat/threads?company_id=1&communication_channel_id=3&date_start=...&date_end=...
    const { data } = await axios.get('/chat/threads', { params: filters.value })
    rows.value = data // debe ser array de filas como tu query
    activeThreadId.value = threads.value[0]?.thread_id ?? null
  } finally {
    loading.value = false
  }
}

// primera carga
fetchThreads()

// composer (por ahora mock)
const draft = ref('')
const sendMessage = async () => {
  if (!draft.value.trim()) return
  // luego: POST /chat/threads/{id}/messages
  draft.value = ''
}
</script>

<template>
  <Head title="Chat" />

  <AppLayout>
    <div class="mx-auto w-full max-w-7xl p-4">
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-[360px_1fr]">
        <!-- Sidebar -->
        <Card class="h-[78vh]">
          <CardHeader class="pb-3">
            <CardTitle class="flex items-center justify-between">
              <span>Chats</span>

              <div class="flex items-center gap-1">
                <!-- ✅ FILTROS ARRIBA -->
                <Dialog v-model:open="filtersOpen">
                  <DialogTrigger as-child>
                    <Button variant="outline" size="icon" title="Filtrar hilos">
                      <Filter class="h-4 w-4" />
                    </Button>
                  </DialogTrigger>

                  <DialogContent class="sm:max-w-[520px]">
                    <DialogHeader>
                      <DialogTitle>Filtrar hilos</DialogTitle>
                      <DialogDescription>
                        company_id, canal y rango de fechas (o thread_status OPEN)
                      </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-2">
                      <div class="grid gap-2">
                        <Label>Compañía</Label>
                        <select
                          v-model="filters.company_id"
                          class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3"
                        >
                          <option value="">Seleccione compañía</option>
                          <option v-for="c in companies" :key="c.id" :value="c.id">
                            {{ c.company_name }}
                          </option>
                        </select>
                      </div>

                      <div class="grid gap-2">
                        <Label>Canal</Label>
                        <select
                          v-model="filters.communication_channel_id"
                          class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3"
                          :disabled="!channels.length"
                        >
                          <option value="">
                            {{ channels.length ? 'Seleccione canal' : 'Seleccione' }}
                          </option>
                          <option v-for="ch in channels" :key="ch.id" :value="ch.id">
                            {{ ch.channel_name }}
                          </option>
                        </select>
                      </div>

                      <div class="grid gap-2">
                        <Label>Rango de fechas</Label>

                        <Popover>
                          <PopoverTrigger as-child>
                            <Button type="button" variant="outline" class="w-full justify-start text-left font-normal">
                              <CalendarIcon class="mr-2 h-4 w-4" />
                              <span>{{ formattedRange }}</span>
                            </Button>
                          </PopoverTrigger>

                          <PopoverContent class="w-auto p-0" align="start" :side-offset="4" :portalled="false">
                            <RangeCalendar
                              v-model="dateRange"
                              :number-of-months="1"
                              :min-value="minDate"
                              :max-value="maxDate"
                            />
                          </PopoverContent>
                        </Popover>

                        <p class="text-xs text-muted-foreground">
                          Aplica: (b.create_date between inicio y fin) o thread_status = OPEN
                        </p>
                      </div>
                    </div>

                    <DialogFooter class="gap-2">
                      <Button type="button" variant="ghost" @click="resetFilters">Reset</Button>
                      <Button type="button" variant="outline" @click="filtersOpen = false">Cancelar</Button>
                      <Button
                        type="button"
                        @click="applyFilters"
                        :disabled="!filters.company_id || !filters.communication_channel_id"
                      >
                        Aplicar
                      </Button>
                    </DialogFooter>
                  </DialogContent>
                </Dialog>

                <Button variant="ghost" size="icon">
                  <MoreVertical class="h-5 w-5" />
                </Button>
              </div>
            </CardTitle>

            <div class="relative mt-2">
              <Search class="absolute left-3 top-2.5 h-4 w-4 opacity-60" />
              <Input v-model="q" class="pl-9" placeholder="Buscar hilo..." />
            </div>

            <div class="mt-2 flex flex-wrap gap-2">
              <Badge variant="secondary">Company: {{ filters.company_id || '—' }}</Badge>
              <Badge variant="secondary">Canal: {{ filters.communication_channel_id || '—' }}</Badge>
              <Badge variant="secondary">{{ formattedRange }}</Badge>
            </div>
          </CardHeader>

          <CardContent class="pt-0">
            <ScrollArea class="h-[60vh] pr-2">
              <div class="space-y-2">
                <div v-if="loading" class="text-sm text-muted-foreground p-2">
                  Cargando...
                </div>

                <button
                  v-for="t in filteredThreads"
                  :key="t.thread_id"
                  type="button"
                  @click="activeThreadId = t.thread_id"
                  class="w-full rounded-xl border p-3 text-left transition hover:bg-muted"
                  :class="t.thread_id === activeThreadId ? 'border-primary/50 bg-muted' : 'border-border'"
                >
                  <div class="flex items-center gap-3">
                    <Avatar>
                      <AvatarFallback>
                        {{ (t.name || '').split(' ').slice(0,2).map(x => x[0]).join('').toUpperCase() || 'TH' }}
                      </AvatarFallback>
                    </Avatar>

                    <div class="min-w-0 flex-1">
                      <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                          <div class="truncate font-medium">
                            {{ t.name }}
                            <span class="ml-2 text-xs text-muted-foreground">#{{ t.thread_id }}</span>
                          </div>
                          <div class="truncate text-sm text-muted-foreground">
                            {{ t.last_message }}
                          </div>
                        </div>

                        <div class="flex flex-col items-end gap-1">
                          <Badge :variant="t.thread_status === 'OPEN' ? 'default' : 'secondary'">
                            {{ t.thread_status }}
                          </Badge>
                          <span class="text-[11px] text-muted-foreground">{{ t.last_at }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </button>

                <div v-if="!loading && !filteredThreads.length" class="text-sm text-muted-foreground p-2">
                  Sin resultados.
                </div>
              </div>
            </ScrollArea>
          </CardContent>
        </Card>

        <!-- Chat panel -->
        <Card class="h-[78vh] flex flex-col">
          <CardHeader class="pb-3">
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <div class="truncate text-lg font-semibold">
                    {{ activeThread?.name ?? '—' }}
                  </div>
                  <Badge variant="secondary">
                    {{ activeThread?.thread_status ?? '—' }}
                  </Badge>
                </div>
                <div class="text-sm text-muted-foreground">
                  {{ activeThread?.phone ?? '' }}
                </div>
              </div>

              <div class="flex items-center gap-2">
                <Button variant="outline" size="icon" title="Adjuntar (mock)">
                  <Paperclip class="h-5 w-5" />
                </Button>
                <Button variant="ghost" size="icon" title="Opciones">
                  <MoreVertical class="h-5 w-5" />
                </Button>
              </div>
            </div>
          </CardHeader>

          <Separator />

          <!-- Messages -->
          <div class="flex-1 overflow-hidden">
            <ScrollArea class="h-full">
              <div ref="scrollerRef" class="h-full overflow-auto p-4">
                <div class="space-y-3">
                  <div
                    v-for="m in activeMessages"
                    :key="m.id"
                    class="flex"
                    :class="m.sender === 'me' ? 'justify-end' : 'justify-start'"
                  >
                    <div
                      class="max-w-[78%] rounded-2xl px-4 py-2 text-sm shadow-sm"
                      :class="m.sender === 'me'
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-foreground'"
                    >
                      <div class="whitespace-pre-wrap leading-relaxed">
                        {{ m.text }}
                      </div>
                      <div class="mt-1 text-[11px] opacity-70" :class="m.sender === 'me' ? 'text-right' : ''">
                        {{ m.created_at }}
                      </div>
                    </div>
                  </div>

                  <div v-if="!activeMessages.length" class="text-sm text-muted-foreground">
                    Selecciona un hilo.
                  </div>
                </div>
              </div>
            </ScrollArea>
          </div>

          <Separator />

          <!-- Composer -->
          <div class="p-3">
            <form class="flex items-center gap-2" @submit.prevent="sendMessage">
              <Input v-model="draft" placeholder="Escribe un mensaje…" class="h-11" />
              <Button type="submit" class="h-11" :disabled="!activeThreadId">
                <Send class="mr-2 h-4 w-4" />
                Enviar
              </Button>
            </form>
          </div>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
