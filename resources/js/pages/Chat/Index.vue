<script setup lang="ts">
import AgentThreadFilter from '@/components/chat/AgentThreadFilter.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed, watch, nextTick, onBeforeUnmount, ref, type Ref } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Separator } from '@/components/ui/separator'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'

import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

const breadcrumbs = [
  {
    title: 'Chat',
    href: '/chat',
  },
  {
    title: 'Bandeja de entrada',
    href: '/chat',
  },
]

import RichDraftInput from '@/components/RichDraftInput.vue'
import { useTextFormat } from '@/composables/useTextFormat'
const { displayThreadName, formatPE, formatReferral } = useTextFormat()
import { useWhatsappFormatter } from '@/composables/useWhatsappFormatter'
const { formatWhatsappText, htmlToWhatsappText } = useWhatsappFormatter()
import { Label } from '@/components/ui/label'
import { RangeCalendar } from '@/components/ui/range-calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
} from '@/components/ui/command'
import { Check, ChevronsUpDown } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger
} from '@/components/ui/dialog'

import EmojiPicker from 'vue3-emoji-picker'
import { Search, Send, Paperclip, MoreVertical, Filter, CalendarIcon, X, Clock, MessageSquareX, RefreshCw, Loader2, Bold, Italic, Underline, UserRoundCog, User, Bot, ZoomIn, ZoomOut } from 'lucide-vue-next'
import type { DateRange } from 'reka-ui'
import { parseDate, getLocalTimeZone, today } from '@internationalized/date'
import { subDays, format } from 'date-fns'
import axios from 'axios'

type ThreadSummary = {
  thread_id: number
  thread_status: string
  assigned_agent_id: number | null
  room: number | null
  sender_id: string | null // ✅
  name: string | null
  phone: string | null
  last_message: string | null
  last_at: string | null
  create_date: string | null
  origin: string | null
  hasNewMessage?: boolean
}

type MessageRow = {
  message_id: number
  thread_id: number
  item_type: string
  item_content: string
  final_content?: any            // ✅ nuevo
  template_components?: any      // ✅ opcional
  message_create_date: string | null
  message_origin?: string | null
  external_id?: string | null
  name?: string | null
  phone?: string | null
  enviado_por?: 'USUARIO' | 'BOT' | string
}

type UiMessage = {
  id: string
  sender: 'me' | 'them'
  text: string
  created_at: string
  item_type: string
}

const page = usePage()

const companies = (page.props.companies ?? []) as { id: number; company_name: string }[]
const channels = ref<{ id: number; channel_name: string }[]>([])
const filtersOpen = ref(false)
const q = ref('')
const tz = getLocalTimeZone()

const hasChannelAssignments =
  (page.props.has_channel_assignments ?? false) as boolean

const isRestrictedRole = (page.props.is_restricted_role ?? false) as boolean
const currentUserAgentId = (page.props.current_user_agent_id ?? null) as number | null

const canUseFilters = computed(() => hasChannelAssignments)

const buildDefaultDates = () => {
  const end = new Date()
  const start = subDays(end, 15)
  return {
    startStr: format(start, 'yyyy-MM-dd'),
    endStr: format(end, 'yyyy-MM-dd'),
  }
}

const { startStr, endStr } = buildDefaultDates()

const dateRange = ref({
  start: parseDate(startStr),
  end: parseDate(endStr),
}) as Ref<DateRange>

const minDate = today(tz).subtract({ days: 365 })
const maxDate = today(tz).add({ days: 365 })

const allowedChannelsByCompany =
  (page.props.allowed_channels_by_company ?? null) as Record<string, number[]> | null

const serverDefaultCompanyId = (page.props.default_company_id ?? null) as number | null
const serverDefaultChannelId = (page.props.default_channel_id ?? null) as number | null

type ThreadStatusFilter = 'OPEN' | 'CLOSED' | 'ALL'
type SearchBy = 'ALL' | 'PHONE' | 'SENDER_ID'
type AgentFilterType = 'bot' | 'holding' | 'asignados' | 'all'

const filters = ref({
  company_id: (serverDefaultCompanyId ?? '') as number | '',
  communication_channel_id: (serverDefaultChannelId ?? '') as number | '',
  date_start: startStr,
  date_end: endStr,
  thread_status: 'OPEN' as ThreadStatusFilter,
  q_by: 'ALL' as SearchBy,
})

const agentFilterType = ref<AgentFilterType>(isRestrictedRole ? 'asignados' : 'all')
const selectedAgentUser = ref<{ id: number; name: string } | null>(
  isRestrictedRole && currentUserAgentId
    ? { id: currentUserAgentId, name: (page.props.auth as any)?.user?.name ?? '' }
    : null
)
const agentPickerOpen = ref(false)
const agentPickerLoading = ref(false)
const agentUsers = ref<{ id: number; name: string }[]>([])

const openAgentPicker = async () => {
  agentPickerOpen.value = true
  agentPickerLoading.value = true
  try {
    const params: Record<string, string | number> = {
      company_id: filters.value.company_id,
      communication_channel_id: filters.value.communication_channel_id,
    }

    if (isPhoneSearchActive.value) {
      params.phone = phoneSearchApplied.value
    } else {
      params.date_start = filters.value.date_start
      params.date_end = filters.value.date_end
      params.thread_status = filters.value.thread_status
    }

    const res = await axios.get('/chat/agents', { params })
    agentUsers.value = res.data ?? []
  } finally {
    agentPickerLoading.value = false
  }
}

const selectAgent = (user: { id: number; name: string }) => {
  selectedAgentUser.value = user
  agentFilterType.value = 'asignados'
  agentPickerOpen.value = false
}

const selectAllAssignedAgents = () => {
  selectedAgentUser.value = null
  agentFilterType.value = 'asignados'
  agentPickerOpen.value = false
}

const selectedAgentLabel = computed(() => {
  if (agentFilterType.value !== 'asignados') return null
  return selectedAgentUser.value?.name ?? 'Todos los asignados'
})

const formattedRange = computed(() => {
  if (!filters.value.date_start || !filters.value.date_end) return 'Selecciona rango'
  return `${filters.value.date_start} — ${filters.value.date_end}`
})

const companyName = computed(() => {
  if (!filters.value.company_id) return ''
  const company = companies.find(c => c.id === filters.value.company_id)
  return company?.company_name ?? ''
})

const channelName = computed(() => {
  if (!filters.value.communication_channel_id) return ''
  const channel = channels.value.find(c => c.id === filters.value.communication_channel_id)
  return channel?.channel_name ?? ''
})

const threadsList = ref<ThreadSummary[]>([])
const threadsNextCursor = ref<number | null>(null)

const activeThreadId = ref<number | null>(null)
const activeThread = computed<ThreadSummary | null>(() => {
  if (!activeThreadId.value) return null
  // Limpiar notificación visual al abrir el thread
  const idx = threadsList.value.findIndex(t => t.thread_id === activeThreadId.value)
  if (idx >= 0 && threadsList.value[idx].hasNewMessage) {
    threadsList.value[idx].hasNewMessage = false
  }
  return threadsList.value.find(t => t.thread_id === activeThreadId.value) ?? null
})

const isThreadClosed = computed(() => {
  return (activeThread.value?.thread_status ?? '').toUpperCase() === 'CLOSED'
})

const draftDisabled = computed(() => !activeThreadId.value || isThreadClosed.value)

const messagesList = ref<MessageRow[]>([])
const messagesNextCursor = ref<number | null>(null)
const messagesHasMore = ref(true)

const loadingThreads = ref(false)
const loadingMessages = ref(false)

const refreshThreads = async () => {
  const keep = activeThreadId.value
  threadsNextCursor.value = null
  await fetchThreads()

  if (keep && threadsList.value.some(t => t.thread_id === keep)) {
    // fuerza recarga del thread (solo si quieres)
    activeThreadId.value = null
    await nextTick()
    activeThreadId.value = keep
  } else if (threadsList.value.length) {
    activeThreadId.value = threadsList.value[0].thread_id
  }
}

const extractTemplateText = (components: any): string => {
  if (!Array.isArray(components)) return ''

  const header = components.find((c: any) => c?.type === 'HEADER' && c?.text)?.text
  const body = components.find((c: any) => c?.type === 'BODY' && c?.text)?.text

  // botones (si algún template los guarda así)
  let buttons = ''
  const btnComp = components.find((c: any) => c?.type === 'BUTTONS' && Array.isArray(c?.buttons))
  if (btnComp) {
    const texts = (btnComp.buttons as any[])
      .map((b) => b?.text)
      .filter((t): t is string => typeof t === 'string' && t.trim().length > 0)

    if (texts.length) buttons = `\n${texts.map((t: string) => `🔘 ${t}`).join('\n')}`
  }

  return [header, body].filter(Boolean).join('\n') + buttons
}

const getMessagePlainText = (m: MessageRow): string => {
  if (m.item_type === 'template') {
    const comps = m.final_content ?? m.template_components
    const txt = extractTemplateText(comps)
    // fallback si no encontró template en DB
    return txt || (m.item_content ?? '')
  }
  return m.item_content ?? ''
}

/** Devuelve HTML final listo para v-html según el tipo de mensaje */
const getMessageHtml = (m: MessageRow): string => {
  if ((m.item_type ?? '').toLowerCase() === 'text_uploading') {
    const rawText = (m.item_content ?? '').trim()
    const safeText = formatWhatsappText(rawText)

    return `
      <div class="space-y-1">
        <div class="inline-flex items-center gap-2 text-xs opacity-80">
          <span class="inline-block h-3.5 w-3.5 rounded-full border-2 border-current border-r-transparent animate-spin"></span>
          <span>Enviando...</span>
        </div>
        <div>${safeText}</div>
      </div>
    `
  }

  if ((m.item_type ?? '').toLowerCase() === 'image_uploading') {
    return `
      <div class="inline-flex items-center gap-2">
        <span class="inline-block h-4 w-4 rounded-full border-2 border-current border-r-transparent animate-spin"></span>
        <span>Subiendo imagen...</span>
      </div>
    `
  }

  if ((m.item_type ?? '').toLowerCase() === 'image') {
    const rawUrl = (m.item_content ?? '').trim()
    const safeUrl = rawUrl.replace(/"/g, '&quot;')

    if (!safeUrl) return ''

    return `<img src="${safeUrl}" alt="Imagen" class="max-h-72 w-auto rounded-lg border object-cover" loading="lazy" />`
  }

  if (m.item_type === 'referral') {
    return formatReferral(m.item_content ?? '')
  }
  const raw = getMessagePlainText(m)
  return raw ? formatWhatsappText(raw) : ''
}

/* ---------------------------
   Timer functions
---------------------------- */
const currentTime = ref<number>(Date.now())

// Actualizar tiempo actual cada segundo
setInterval(() => {
  currentTime.value = Date.now()
}, 1000)

const formatTimeDuration = (ms: number): string => {
  const totalSeconds = Math.floor(ms / 1000)
  const hours = Math.floor(totalSeconds / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const seconds = totalSeconds % 66
  return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
}

const parseUTCDate = (utc: string | null): number => {
  if (!utc) return 0
  // Normaliza igual que formatPE: "2026-02-18 07:39:51" -> "2026-02-18T07:39:51Z"
  const normalized = utc.includes('T') ? utc : utc.replace(' ', 'T')
  const withTZ = /Z$|[+\-]\d{2}:\d{2}$/.test(normalized) ? normalized : `${normalized}Z`
  return new Date(withTZ).getTime()
}

const getThreadElapsedTime = (thread: ThreadSummary): string => {
  if (!thread.create_date || thread.thread_status !== 'OPEN') return ''
  const createTime = parseUTCDate(thread.create_date)
  const elapsed = currentTime.value - createTime
  return formatTimeDuration(elapsed)
}

const getThreadRemainingTime = (thread: ThreadSummary): string => {
  if (!thread.create_date || thread.thread_status !== 'OPEN') return ''
  const createTime = parseUTCDate(thread.create_date)
  const twentyFourHoursMs = 24 * 60 * 60 * 1000 // 24 horas
  const remaining = twentyFourHoursMs - (currentTime.value - createTime)
  
  if (remaining <= 0) return '00:00:00'
  return formatTimeDuration(remaining)
}

const getThreadOriginLabel = (origin: string | null): string => {
  if (!origin) return ''
  if (origin.toUpperCase() === 'IN') return 'INBOUND'
  if (origin.toUpperCase() === 'OUT') return 'OUTBOUND'
  return origin
}

import { useToast } from '@/components/ui/toast/use-toast'
const { toast } = useToast()

watch(canUseFilters, (ok) => {
  if (!ok) filtersOpen.value = false
}, { immediate: true })

const onClickFilters = () => {
  if (!canUseFilters.value) {
    toast({
      title: 'Asignar canales',
      description: 'No tienes canales asignados. Pide que te asignen al menos un canal para usar filtros.',
      variant: 'destructive', // o quítalo si no quieres rojo
    })
    return
  }
  filtersOpen.value = true
}

type TipRow = {
  id: number
  tipificacion_1: string
  tipificacion_2: string
  tipificacion_3: string
}

const closeOpen = ref(false)
const closeLoading = ref(false)
const closeThreadTargetId = ref<number | null>(null)

const reassignOpen = ref(false)
const reassignLoading = ref(false)
const reassignSubmitting = ref(false)
const reassignThreadId = ref<number | null>(null)
const reassignCurrentUser = ref<{ id: number; username?: string | null; name?: string | null } | null>(null)
const reassignCandidates = ref<Array<{ id: number; username?: string | null; name?: string | null }>>([])
const reassignToUserId = ref<number | null>(null)

const openReassignModal = async () => {
  const thread = activeThread.value
  if (!thread?.thread_id) return

  reassignThreadId.value = thread.thread_id
  reassignCurrentUser.value = null
  reassignCandidates.value = []
  reassignToUserId.value = null
  reassignOpen.value = true
  reassignLoading.value = true

  try {
    const res = await axios.get(`/chat/threads/${thread.thread_id}/reassignment-candidates`)
    reassignCurrentUser.value = res.data?.current_user ?? null
    reassignCandidates.value = res.data?.candidates ?? []
  } catch (error: any) {
    const msg = error?.response?.data?.message || 'No se pudieron cargar los usuarios para reasignar.'
    toast({
      title: 'Reasignacion no disponible',
      description: msg,
      variant: 'destructive',
    })
    reassignOpen.value = false
  } finally {
    reassignLoading.value = false
  }
}

const confirmReassignThread = async () => {
  if (!reassignThreadId.value || !reassignToUserId.value) return
  if (reassignToUserId.value === reassignCurrentUser.value?.id) {
    toast({
      title: 'Seleccion invalida',
      description: 'Debes elegir un usuario distinto al asignado actual.',
      variant: 'destructive',
    })
    return
  }

  reassignSubmitting.value = true
  try {
    await axios.patch(`/chat/threads/${reassignThreadId.value}/reassign`, {
      new_assigned_agent_id: reassignToUserId.value,
    })

    const idx = threadsList.value.findIndex(t => t.thread_id === reassignThreadId.value)
    if (idx >= 0) {
      threadsList.value[idx].assigned_agent_id = reassignToUserId.value
    }

    reassignOpen.value = false
    toast({
      title: 'Reasignacion completada',
      description: 'La conversacion fue reasignada correctamente.',
    })
    await fetchThreads()
  } catch (error: any) {
    const msg = error?.response?.data?.message || 'No se pudo reasignar la conversacion.'
    toast({
      title: 'Error al reasignar',
      description: msg,
      variant: 'destructive',
    })
  } finally {
    reassignSubmitting.value = false
  }
}

const tipRows = ref<TipRow[]>([])

const selT1 = ref<string>('')
const selT2 = ref<string>('')
const selT3Id = ref<number | null>(null)
const selT3Label = ref<string>('')
const t1Open = ref(false)
const t2Open = ref(false)
const t3Open = ref(false)

const canConfirmClose = computed(() => !!closeThreadTargetId.value && !!selT3Id.value)

const resetCloseForm = () => {
  selT1.value = ''
  selT2.value = ''
  selT3Id.value = null
  selT3Label.value = ''
  tipRows.value = []
  t1Open.value = false
  t2Open.value = false
  t3Open.value = false
}

const fetchTipificaciones = async () => {
  if (!filters.value.company_id || !filters.value.communication_channel_id) return
  closeLoading.value = true
  try {
    const res = await axios.get('/chat/tipificaciones', {
      params: {
        company_id: filters.value.company_id,
        communication_channel_id: filters.value.communication_channel_id,
      },
    })
    tipRows.value = (res.data?.data ?? []) as TipRow[]
  } finally {
    closeLoading.value = false
  }
}

const openCloseModal = async (threadId: number) => {
  // thread debe estar OPEN (ya tienes el v-if, pero por seguridad)
  const t = threadsList.value.find(x => x.thread_id === threadId)
  if (!t || String(t.thread_status).toUpperCase() !== 'OPEN') return

  closeThreadTargetId.value = threadId
  resetCloseForm()
  closeOpen.value = true
  await fetchTipificaciones()
}

const t1Options = computed(() => {
  const set = new Set(tipRows.value.map(r => r.tipificacion_1).filter(Boolean))
  return Array.from(set).sort()
})

const t2Options = computed(() => {
  if (!selT1.value) return []
  const set = new Set(
    tipRows.value
      .filter(r => r.tipificacion_1 === selT1.value)
      .map(r => r.tipificacion_2)
      .filter(Boolean)
  )
  return Array.from(set).sort()
})

const t3Options = computed(() => {
  if (!selT1.value || !selT2.value) return []
  return tipRows.value
    .filter(r => r.tipificacion_1 === selT1.value && r.tipificacion_2 === selT2.value)
    .map(r => ({ id: r.id, label: r.tipificacion_3 }))
    .sort((a, b) => a.label.localeCompare(b.label))
})

// resets en cascada
watch(selT1, () => {
  selT2.value = ''
  selT3Id.value = null
  selT3Label.value = ''
})

watch(selT2, () => {
  selT3Id.value = null
  selT3Label.value = ''
})

const confirmCloseThread = async () => {
  if (!closeThreadTargetId.value) return

  if (!selT3Id.value) {
    toast({
      title: 'Selecciona una tipificación',
      description: 'Debes seleccionar Tipificación 1, 2 y 3 antes de cerrar.',
      variant: 'destructive',
    })
    return
  }

  closingThreadId.value = closeThreadTargetId.value
  try {
    await axios.patch(`/chat/threads/${closeThreadTargetId.value}/close`, {
      tipificacion_id: selT3Id.value,
    })

    // Actualizar estado local
    const idx = threadsList.value.findIndex(t => t.thread_id === closeThreadTargetId.value)
    if (idx >= 0) threadsList.value[idx].thread_status = 'CLOSED'

    closeOpen.value = false
  } catch (e: any) {
    toast({
      title: 'No se pudo cerrar',
      description: e?.response?.data?.message ?? 'Error cerrando conversación.',
      variant: 'destructive',
    })
  } finally {
    closingThreadId.value = null
    closeThreadTargetId.value = null
  }
}

/* ---------------------------
   History
---------------------------- */
const historyOpen = ref(false)
const historyLoading = ref(false)
const historyRows = ref<MessageRow[]>([])
const historyNextCursor = ref<number | null>(null)
const historyHasMore = ref(true)

const HISTORY_LIMIT = 100

const openHistory = async () => {
  const phone = activeThread.value?.phone
  if (!phone) return

  historyOpen.value = true
  historyRows.value = []
  historyNextCursor.value = null
  historyHasMore.value = true

  await fetchHistory()
}

const fetchHistory = async (opts?: { append?: boolean }) => {
  const phone = activeThread.value?.phone
  if (!phone) return
  if (!filters.value.company_id || !filters.value.communication_channel_id) return

  historyLoading.value = true
  try {
    const params: any = {
      company_id: filters.value.company_id,
      communication_channel_id: filters.value.communication_channel_id,
      phone,
      limit: HISTORY_LIMIT,
    }

    if (opts?.append && historyNextCursor.value) {
      params.cursor = historyNextCursor.value
    }

    const res = await axios.get('/chat/history', { params })
    const payload = res.data as { data: MessageRow[]; next_cursor: number | null }
    const incoming = payload.data ?? []

    if (opts?.append) historyRows.value.push(...incoming)
    else historyRows.value = incoming

    historyNextCursor.value = payload.next_cursor ?? null
    historyHasMore.value = incoming.length === HISTORY_LIMIT
  } finally {
    historyLoading.value = false
  }
}

/* ---------------------------
   Scroll helpers
---------------------------- */
const scrollerRef = ref<HTMLElement | null>(null)
const hasUnreadBelow = ref(false)

const getScrollEl = (): HTMLElement | null => scrollerRef.value

const isNearBottom = (): boolean => {
  const el = getScrollEl()
  if (!el) return true
  return el.scrollHeight - el.scrollTop - el.clientHeight < 80
}

const scrollToBottom = async () => {
  await nextTick()
  const el = getScrollEl()
  if (!el) return
  el.scrollTop = el.scrollHeight
  hasUnreadBelow.value = false
}

const onScrollAreaScroll = () => {
  if (isNearBottom()) hasUnreadBelow.value = false
}

const handleNewMessageScroll = () => {
  if (isNearBottom()) {
    scrollToBottom()
  } else {
    hasUnreadBelow.value = true
  }
}

/* ---------------------------
   Computeds
---------------------------- */
const activeMessages = computed<UiMessage[]>(() => {
  return messagesList.value.map((m, idx) => {
    const sender: 'me' | 'them' = m.enviado_por === 'USUARIO' ? 'them' : 'me'
    const created = formatPE(m.message_create_date)
    const formatted = getMessageHtml(m)

    return {
      id: String(m.message_id ?? `${m.thread_id}-${idx}`),
      sender,
      text: formatted,
      created_at: created,
      item_type: m.item_type ?? 'text',
    }
  })
})

const filteredThreads = computed(() => {
  const term = q.value.trim().toLowerCase()
  if (!term) return threadsList.value

  return threadsList.value.filter(t =>
    `${t.sender_id ?? ''} ${t.name ?? ''} ${t.phone ?? ''} ${t.last_message ?? ''}`
      .toLowerCase()
      .includes(term)
  )
})

/* ---------------------------
   Date watcher
---------------------------- */
watch(dateRange, (range) => {
  if (!range?.start || !range?.end) return
  const start = range.start.toDate(tz)
  const end = range.end.toDate(tz)
  filters.value.date_start = format(start, 'yyyy-MM-dd')
  filters.value.date_end = format(end, 'yyyy-MM-dd')
})

/* ---------------------------
   API
---------------------------- */
const applyFilters = async () => {
  filtersOpen.value = false

  activeThreadId.value = null
  messagesList.value = []
  messagesNextCursor.value = null
  threadsNextCursor.value = null

  await fetchThreads()
}

const MESSAGES_LIMIT = 50

const fetchMessages = async (threadId: number, opts?: { prepend?: boolean }) => {
  loadingMessages.value = true
  try {
    const params: any = { limit: MESSAGES_LIMIT }

    if (opts?.prepend) {
      const oldestId = messagesList.value[0]?.message_id
      if (!oldestId) {
        messagesHasMore.value = false
        return
      }
      params.cursor = oldestId
    }

    const res = await axios.get(`/chat/messages/${threadId}`, { params })
    const payload = res.data as { data: MessageRow[]; next_cursor: number | null }
    const incoming = payload.data ?? []

    if (opts?.prepend) {
      if (incoming.length === 0) {
        messagesHasMore.value = false
        return
      }

      messagesList.value = [...incoming, ...messagesList.value]

      if (incoming.length < MESSAGES_LIMIT) {
        messagesHasMore.value = false
      }
    } else {
      messagesList.value = incoming
      messagesHasMore.value = incoming.length >= MESSAGES_LIMIT
    }

    messagesNextCursor.value = payload.next_cursor ?? null
  } finally {
    loadingMessages.value = false
  }
}

const phoneSearchOpen = ref(false)
const phoneSearchDraft = ref('')
const phoneSearchApplied = ref('')

const isPhoneSearchActive = computed(() => phoneSearchApplied.value.trim().length > 0)

const openPhoneSearchModal = () => {
  phoneSearchDraft.value = phoneSearchApplied.value
  phoneSearchOpen.value = true
}

const applyPhoneSearch = async () => {
  phoneSearchApplied.value = phoneSearchDraft.value.trim()
  phoneSearchOpen.value = false

  activeThreadId.value = null
  messagesList.value = []
  messagesNextCursor.value = null
  threadsNextCursor.value = null
  q.value = ''

  await fetchThreads()
}

const clearPhoneSearch = async () => {
  phoneSearchApplied.value = ''
  phoneSearchDraft.value = ''

  activeThreadId.value = null
  messagesList.value = []
  messagesNextCursor.value = null
  threadsNextCursor.value = null
  q.value = ''

  await fetchThreads()
}

const fetchThreads = async (opts?: { append?: boolean }) => {
  if (!filters.value.company_id || !filters.value.communication_channel_id) return

  loadingThreads.value = true
  try {
    const params: any = {
      company_id: filters.value.company_id,
      communication_channel_id: filters.value.communication_channel_id,
      limit: 60,
    }

    // ✅ Phone search: ignora date/status
    if (isPhoneSearchActive.value) {
      params.phone = phoneSearchApplied.value
    } else {
      // ✅ modo normal (si quieres mantener tus filtros normales)
      Object.assign(params, {
        date_start: filters.value.date_start,
        date_end: filters.value.date_end,
        thread_status: filters.value.thread_status,
      })
    }

    const agentFilter = getAssignedAgentIdFilter()
    if (agentFilter) {
      if (agentFilter.exact !== undefined) {
        params.assigned_agent_id_min = agentFilter.exact
        params.assigned_agent_id_max = agentFilter.exact
      } else {
        if (agentFilter.min !== undefined) params.assigned_agent_id_min = agentFilter.min
        if (agentFilter.max !== undefined) params.assigned_agent_id_max = agentFilter.max
      }
    }

    if (opts?.append && threadsNextCursor.value) params.cursor = threadsNextCursor.value

    const res = await axios.get('/chat/threads', { params })
    const payload = res.data as { data: ThreadSummary[]; next_cursor: number | null }

    if (opts?.append) threadsList.value.push(...(payload.data ?? []))
    else threadsList.value = payload.data ?? []

    threadsNextCursor.value = payload.next_cursor ?? null

    if (!activeThreadId.value && threadsList.value.length) {
      activeThreadId.value = threadsList.value[0].thread_id
    }
  } finally {
    loadingThreads.value = false
  }
}

const getAssignedAgentIdFilter = (): { exact?: number; min?: number; max?: number } | null => {
  switch (agentFilterType.value) {
    case 'bot':
      return { min: 1, max: 1 }
    case 'holding':
      return { min: 2, max: 2 }
    case 'asignados':
      return selectedAgentUser.value ? { exact: selectedAgentUser.value.id } : { min: 3 }
    case 'all':
    default:
      return null
  }
}

const resetFilters = async () => {
  const { startStr, endStr } = buildDefaultDates()

  filters.value.company_id = (serverDefaultCompanyId ?? '') as any
  filters.value.communication_channel_id = (serverDefaultChannelId ?? '') as any

  filters.value.date_start = startStr
  filters.value.date_end = endStr

  dateRange.value = { start: parseDate(startStr), end: parseDate(endStr) }

  filters.value.thread_status = 'OPEN'
  filters.value.q_by = 'ALL'
  q.value = ''
}
/* ---------------------------
   SOCKETS (Reverb/Echo)
   (Colocados AQUÍ para que ya existan filters/threads/messages)
---------------------------- */
let companyChannel: any = null
let threadChannel: any = null

const safeEcho = () => (typeof window !== 'undefined' ? (window as any).Echo : null)

const subscribeCompany = (companyId: number) => {
  const Echo = safeEcho()
  if (!Echo) return

  if (companyChannel?.name) Echo.leave(companyChannel.name)

  companyChannel = Echo.private(`chat.company.${companyId}`)
    .listen('.thread.created', (e: any) => {
      const idx = threadsList.value.findIndex(t => t.thread_id === e.thread_id)
      if (idx >= 0) threadsList.value[idx] = { ...threadsList.value[idx], ...e }
      else threadsList.value.unshift(e)
    })
    .listen('.message.created', (e:any) => {
        console.log('REVERB message.created', e);

        // Soporta payload plano o anidado (e.data)
        const raw = e?.data ?? e
        const eventThreadId = Number(raw?.thread_id ?? 0)
        const eventCompanyId = Number(raw?.company_id)
        const eventChannelId = Number(raw?.communication_channel_id)
        const activeCompanyId = Number(filters.value.company_id)
        const activeChannelId = Number(filters.value.communication_channel_id)

        // Si no trae ids de scope, no notificar para evitar falsos positivos
        const hasScopeIds = Number.isFinite(eventCompanyId) && Number.isFinite(eventChannelId)
        const isActiveScope =
          hasScopeIds &&
          eventCompanyId === activeCompanyId &&
          eventChannelId === activeChannelId

        // actualizar preview en threadsList o agregar si es nuevo
        const idx = threadsList.value.findIndex(t => t.thread_id === eventThreadId)
        if (idx >= 0) {
          threadsList.value[idx] = {
            ...threadsList.value[idx],
            last_message: raw?.item_content ?? threadsList.value[idx].last_message,
            last_at: raw?.message_create_date ?? threadsList.value[idx].last_at,
            hasNewMessage: isActiveScope && activeThreadId.value !== eventThreadId,
          }
        } else if (isActiveScope && eventThreadId > 0) {
          // Thread nuevo: recargar lista en background sin resetear el activo
          fetchThreads()
        }

        // Mostrar toast cuando coincide company+channel activos
        // (siempre, incluso si el thread está abierto, porque el mensaje externo no se guarda en BD)
        if (isActiveScope) {
          const name = raw?.name?.trim() || null
          const phone = raw?.phone ?? ''
          const titleParts = name && name !== phone
            ? `${name} · ${phone}`
            : phone
          toast({
            title: `📱 ${titleParts}`,
            description: `💬 ${raw?.item_content ?? ''}`,
            variant: 'success',
          })
        }

        // Si el thread está abierto, recargar mensajes desde BD y forzar scroll al fondo
        if (isActiveScope && activeThreadId.value === eventThreadId && eventThreadId > 0) {
          fetchMessages(eventThreadId).then(() => scrollToBottom())
        }
    })
}

const subscribeThread = (threadId: number) => {
  const Echo = safeEcho()
  if (!Echo) return

  if (threadChannel?.name) Echo.leave(threadChannel.name)

  threadChannel = Echo.private(`chat.thread.${threadId}`)
  .listen('.message.created', (e: any) => {
    // 1) si ya existe por id -> no duplicar
    if (messagesList.value.some(m => m.message_id === e.message_id)) return

    // 2) Si el mensaje viene de tu lado (APP/BOT), NO lo pintes como otro globo
    //    Solo intenta reemplazar el optimistic (si lo usas) y/o toast.
    const isMine = e.origin === 'APP' || e.enviado_por === 'BOT'

    if (isMine) {
      // si guardas external_id tmp en el optimistic, aquí podrías reemplazarlo:
      const idx = messagesList.value.findIndex(m => m.external_id && m.external_id === e.external_id)
      if (idx >= 0) messagesList.value[idx] = e
      // si no tienes match, al menos NO push
      // toast opcional: "Enviado"
      return
    }

    // 3) Entrante: sí agregar
    messagesList.value.push(e)
    nextTick(() => scrollToBottom())
  })

}

/* ---------------------------
   WATCHERS (sin duplicar)
---------------------------- */

// ✅ UN SOLO watcher para company_id:
// - carga channels
// - ajusta communication_channel_id
// - subscribeCompany
watch(
  () => filters.value.company_id,
  async (companyId) => {
    channels.value = []

    if (!companyId) {
      filters.value.communication_channel_id = ''
      return
    }

    // subscribe a company
    subscribeCompany(Number(companyId))

    // cargar channels
    const { data } = await axios.get(`/campaigns/companies/${companyId}/channels`)
    let incoming = (data ?? []) as { id: number; channel_name: string }[]

    // Si el usuario tiene asignaciones, filtrar canales permitidos para esa company
    const allowed = allowedChannelsByCompany?.[String(companyId)]
    if (Array.isArray(allowed) && allowed.length) {
      incoming = incoming.filter(ch => allowed.includes(Number(ch.id)))
    }

    channels.value = incoming

    // mantener canal si existe
    const current = filters.value.communication_channel_id
    const existsCurrent = !!current && channels.value.some(ch => Number(ch.id) === Number(current))
    if (existsCurrent) return

    filters.value.communication_channel_id = (channels.value[0]?.id ?? '') as any
  },
  { immediate: true }
)

// ✅ watcher del thread: subscribe + reset + fetch + scroll
watch(activeThreadId, async (id) => {
  if (!id) return

  subscribeThread(id)

  messagesList.value = []
  messagesNextCursor.value = null
  messagesHasMore.value = true

  await fetchMessages(id)
  await scrollToBottom()
})

const canFetch = computed(() => !!filters.value.company_id && !!filters.value.communication_channel_id)

let searchTimer: number | null = null

onBeforeUnmount(() => {
  if (searchTimer) window.clearTimeout(searchTimer)
})

watch(
  () => [filters.value.company_id, filters.value.communication_channel_id, filters.value.date_start, filters.value.date_end, agentFilterType.value, selectedAgentUser.value?.id],
  async () => {
    if (!canFetch.value) return
    if (filtersOpen.value) return // ✅ clave: NO buscar mientras editas en el modal

    activeThreadId.value = null
    messagesList.value = []
    messagesNextCursor.value = null
    threadsNextCursor.value = null
    await fetchThreads()
  },
  { immediate: true }
)

/* ---------------------------
   Cleanup sockets
---------------------------- */
onBeforeUnmount(() => {
  const Echo = safeEcho()
  if (!Echo) return
  if (companyChannel?.name) Echo.leave(companyChannel.name)
  if (threadChannel?.name) Echo.leave(threadChannel.name)
})

/* ---------------------------
   Send message
---------------------------- */
const onEditorSubmit = async (html: string) => {
  if (draftDisabled.value) return
  const msg = htmlToWhatsappText(html).trim()
  if (!msg) return

  // Limpiar editor antes del envío (tanto ref como DOM del contenteditable)
  draftHtml.value = ''
  draftEditorRef.value?.clear?.()

  await sendMessageWithText(msg)
}

const draftText = computed(() => htmlToWhatsappText(draftHtml.value))
const canSend = computed(() => !draftDisabled.value && draftText.value.trim().length > 0)

const draftHtml = ref('')
const draftEditorRef = ref<any>(null)
const closingThreadId = ref<number | null>(null)
const showEmojiPickerChat = ref(false)
const imageInputRef = ref<HTMLInputElement | null>(null)
const imagePreviewOpen = ref(false)
const imagePreviewUrl = ref('')
const imageFile = ref<File | null>(null)
const imageFileName = ref('')
const sendingImage = ref(false)

const toggleEmojiPickerChat = () => {
  if (draftDisabled.value) return
  showEmojiPickerChat.value = !showEmojiPickerChat.value
}

const applyDraftFormat = (kind: 'bold' | 'italic' | 'underline') => {
  if (draftDisabled.value) return
  if (kind === 'bold') draftEditorRef.value?.toggleBold?.()
  if (kind === 'italic') draftEditorRef.value?.toggleItalic?.()
  if (kind === 'underline') draftEditorRef.value?.toggleUnderline?.()
}

const addEmojiToDraft = (emoji: any) => {
  const emojiChar = emoji?.i
  if (!emojiChar) return
  draftEditorRef.value?.insertText?.(emojiChar)
  showEmojiPickerChat.value = false
}

const buildImageFileName = (file: File): string => {
  const original = file.name || 'image.jpg'
  const namePart = original.includes('.') ? original.slice(0, original.lastIndexOf('.')) : original
  const extPart = original.includes('.') ? original.slice(original.lastIndexOf('.') + 1) : 'jpg'
  const base = namePart.toLowerCase().replace(/[^a-z0-9_-]/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '')
  const ext = extPart.toLowerCase().replace(/[^a-z0-9]/g, '') || 'jpg'
  const timestamp = Date.now()
  return `msg_${timestamp}_${base || 'image'}.${ext}`
}

const clearImageDraft = () => {
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value)
  }
  imagePreviewUrl.value = ''
  imageFile.value = null
  imageFileName.value = ''
  if (imageInputRef.value) {
    imageInputRef.value.value = ''
  }
}

const openAttachPicker = () => {
  if (draftDisabled.value) return
  imageInputRef.value?.click()
}

const onImageSelected = (event: Event) => {
  const target = event.target as HTMLInputElement | null
  const file = target?.files?.[0]
  if (!file) return

  if (!file.type.startsWith('image/')) {
    toast({
      title: 'Archivo no permitido',
      description: 'Solo se permiten imagenes.',
      variant: 'destructive',
    })
    clearImageDraft()
    return
  }

  const maxBytes = 2 * 1024 * 1024
  if (file.size > maxBytes) {
    toast({
      title: 'Imagen demasiado pesada',
      description: 'El tamano maximo es 2MB.',
      variant: 'destructive',
    })
    clearImageDraft()
    return
  }

  clearImageDraft()
  imageFile.value = file
  imageFileName.value = buildImageFileName(file)
  imagePreviewUrl.value = URL.createObjectURL(file)
  imagePreviewOpen.value = true
}

const imagePayloadPreview = computed(() => {
  const userId = (page.props.auth as any)?.user?.id ?? null
  const message = `${window.location.origin}/storage/messages_image/${imageFileName.value || 'image.jpg'}`

  return JSON.stringify(
    {
      message,
      messageType: 'image',
      userId: String(userId ?? ''),
    },
    null,
    2
  )
})

const sendAttachedImage = async () => {
  if (!activeThreadId.value || !imageFile.value) return
  if (isThreadClosed.value) return

  sendingImage.value = true

  const threadId = activeThreadId.value
  const optimisticId = `tmp-img-${Date.now()}`
  const socketId = (window as any).Echo?.socketId?.()

  messagesList.value.push({
    message_id: -1,
    thread_id: threadId,
    item_type: 'image_uploading',
    item_content: imageFileName.value,
    message_create_date: new Date().toISOString(),
    origin: 'APP',
    external_id: optimisticId,
  } as any)

  nextTick(() => scrollToBottom())

  try {
    const formData = new FormData()
    formData.append('messageType', 'image')
    formData.append('userId', String((page.props.auth as any)?.user?.id ?? ''))
    formData.append('fileName', imageFileName.value)
    formData.append('image', imageFile.value)

    const response = await axios.post(`/api/chat/threads/${threadId}/reply`, formData, {
      headers: {
        ...(socketId ? { 'X-Socket-Id': socketId } : {}),
      },
    })

    const expectedMessageId = Number(response?.data?.interaction?.id ?? 0)

    // Refresca el thread para reemplazar el placeholder por la imagen real.
    await fetchMessages(threadId)

    // Si la BD tarda en reflejar el mensaje, reintenta brevemente.
    if (expectedMessageId > 0 && !messagesList.value.some(m => Number(m.message_id) === expectedMessageId)) {
      await new Promise((resolve) => setTimeout(resolve, 700))
      await fetchMessages(threadId)
    }

    await scrollToBottom()

    imagePreviewOpen.value = false
    clearImageDraft()
  } catch (e: any) {
    const idx = messagesList.value.findIndex(m => m.external_id === optimisticId)
    if (idx >= 0) messagesList.value.splice(idx, 1)

    const serverMsg = e?.response?.data?.error ?? e?.message ?? 'Error al enviar la imagen'
    toast({
      title: 'No se pudo enviar',
      description: serverMsg,
      variant: 'destructive',
    })
  } finally {
    sendingImage.value = false
  }
}

watch(imagePreviewOpen, (open) => {
  if (!open) {
    clearImageDraft()
  }
})

const sendMessageWithText = async (msg: string) => {
  if (!activeThreadId.value) return
  if (isThreadClosed.value) return
  if (!msg.trim()) return

  const threadId = activeThreadId.value
  const optimisticId = `tmp-${Date.now()}`
  const socketId = (window as any).Echo?.socketId?.()

  messagesList.value.push({
    message_id: -1,
    thread_id: threadId,
    item_type: 'text_uploading',
    item_content: msg,
    message_create_date: new Date().toISOString(),
    origin: 'APP',
    external_id: optimisticId,
  } as any)

  nextTick(() => scrollToBottom())

  draftHtml.value = '' // limpia editor

  try {
    const response = await axios.post(`/api/chat/threads/${threadId}/reply`, {
      message: msg,
      messageType: 'text',
      userId: (page.props.auth as any)?.user?.id ?? null,
    },{
      headers: socketId ? { 'X-Socket-Id': socketId } : {}
    })

    const expectedMessageId = Number(response?.data?.interaction?.id ?? 0)

    await fetchMessages(threadId)

    if (expectedMessageId > 0 && !messagesList.value.some(m => Number(m.message_id) === expectedMessageId)) {
      await new Promise((resolve) => setTimeout(resolve, 700))
      await fetchMessages(threadId)
    }

    await scrollToBottom()
  } catch (e: any) {
    const idx = messagesList.value.findIndex(m => m.external_id === optimisticId)
    if (idx >= 0) messagesList.value.splice(idx, 1)

    const serverMsg = e?.response?.data?.error ?? e?.message ?? 'Error al enviar el mensaje'
    toast({
      title: 'No se pudo enviar',
      description: serverMsg,
      variant: 'destructive',
    })
  }
}

const sendMessage = async () => {
  const msg = draftText.value.trim()
  await sendMessageWithText(msg)
}

const lightboxOpen = ref(false)
const lightboxSrc = ref('')
const lightboxZoom = ref(1)

const openImageLightbox = (src: string) => {
  if (!src) return
  lightboxSrc.value = src
  lightboxZoom.value = 1
  lightboxOpen.value = true
}

const onMessageBodyClick = (event: MouseEvent) => {
  const target = event.target as HTMLElement | null
  const imgEl = target?.closest('img') as HTMLImageElement | null
  if (!imgEl) return

  const src = imgEl.getAttribute('src') || ''
  openImageLightbox(src)
}

const setLightboxZoom = (value: number) => {
  lightboxZoom.value = Math.min(4, Math.max(0.5, Number(value.toFixed(2))))
}

const zoomInLightbox = () => setLightboxZoom(lightboxZoom.value + 0.25)
const zoomOutLightbox = () => setLightboxZoom(lightboxZoom.value - 0.25)
const resetLightboxZoom = () => setLightboxZoom(1)

const onLightboxWheel = (event: WheelEvent) => {
  if (event.deltaY < 0) {
    zoomInLightbox()
    return
  }
  zoomOutLightbox()
}
</script>

<template>
  <Head title="Chat" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-auto w-full max-w-7xl p-4">

      <div class="grid grid-cols-1 gap-3" :class="isRestrictedRole ? 'lg:grid-cols-[360px_1fr]' : 'lg:grid-cols-[64px_360px_1fr]'">
        <!-- Filter rail (solo para roles no restringidos) -->
        <div v-if="!isRestrictedRole" class="hidden lg:flex flex-col items-center gap-2 pt-4">
          <AgentThreadFilter
            v-model="agentFilterType"
            :selected-agent-name="selectedAgentLabel"
            @open-user-picker="openAgentPicker"
          />
        </div>

        <!-- Sidebar -->
        <Card class="h-[78vh] flex flex-col overflow-hidden">
          <CardHeader class="pb-3">
            <CardTitle class="flex items-center justify-between">
              <span>Chats</span>

              <div class="flex items-center gap-1">
                <TooltipProvider>
                  <Tooltip>
                    <TooltipTrigger as-child>
                      <Button
                        variant="outline"
                        size="icon"
                        :disabled="loadingThreads || !canFetch"
                        @click="refreshThreads"
                      >
                        <Loader2 v-if="loadingThreads" class="h-4 w-4 animate-spin" />
                        <RefreshCw v-else class="h-4 w-4" />
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                      <p>Actualizar</p>
                    </TooltipContent>
                  </Tooltip>

                  <Tooltip>
                    <TooltipTrigger as-child>
                      <Button
                        variant="outline"
                        size="icon"
                        :class="!canUseFilters ? 'opacity-90 cursor-not-allowed' : ''"
                        @click="onClickFilters"
                      >
                        <Filter class="h-4 w-4" />
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                      <p>Filtrar</p>
                    </TooltipContent>
                  </Tooltip>
                </TooltipProvider>

                <Dialog v-model:open="filtersOpen">
                  <DialogContent class="sm:max-w-[520px]">
                    <DialogHeader>
                      <DialogTitle>Filtrar conversaciones</DialogTitle>
                      <DialogDescription>
                        company, canal y rango de fechas (o status OPEN)
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
                              :number-of-months="2"
                              :min-value="minDate"
                              :max-value="maxDate"
                            />
                          </PopoverContent>
                        </Popover>
                      </div>

                      <div class="grid gap-2">
                        <Label>Estado</Label>
                        <select
                          v-model="filters.thread_status"
                          class="mt-1 block w-full border border-border bg-background text-foreground rounded-md shadow-sm py-2 px-3"
                        >
                          <option value="OPEN">OPEN</option>
                          <option value="CLOSED">CLOSED</option>
                          <option value="ALL">ALL</option>
                        </select>
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

                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" title="Opciones">
                      <MoreVertical class="h-5 w-5" />
                    </Button>
                  </DropdownMenuTrigger>

                  <DropdownMenuContent align="end">
                    <DropdownMenuItem @select="openPhoneSearchModal">
                      Buscar por teléfono
                    </DropdownMenuItem>

                    <DropdownMenuItem v-if="isPhoneSearchActive" @select="clearPhoneSearch">
                      Limpiar búsqueda
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
                
              </div>
            </CardTitle>

            <div class="mt-2 flex items-center gap-2">
              <div class="relative flex-1">
                <Search class="absolute left-3 top-2.5 h-4 w-4 opacity-60" />
                <Input v-model="q" class="pl-9" placeholder="Buscar conversación" />
              </div>
            </div>

            <div class="mt-2 flex flex-wrap gap-2">
              <Badge variant="secondary">Company: {{ companyName || filters.company_id || '' }}</Badge>
              <Badge variant="secondary">Canal: {{ channelName || filters.communication_channel_id || '' }}</Badge>

              <Badge v-if="isPhoneSearchActive" variant="secondary">
                Búsqueda: {{ phoneSearchApplied }}
              </Badge>

              <template v-else>
                <Badge variant="secondary">{{ formattedRange }}</Badge>
                <Badge variant="secondary">Estado: {{ filters.thread_status }}</Badge>
              </template>
            </div>
          </CardHeader>

          <CardContent class="pt-0 flex-1 overflow-hidden">
            <ScrollArea class="h-full pr-2">
              <div class="space-y-2">
                <div v-if="loadingThreads" class="flex items-center gap-2 text-sm text-muted-foreground p-2">
                  <Loader2 class="h-4 w-4 animate-spin" />
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
                        {{ displayThreadName(t).split(' ').slice(0,2).map(x => x[0]).join('').toUpperCase() || 'TH' }}
                      </AvatarFallback>
                    </Avatar>

                    <div class="min-w-0 flex-1">
                      <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                          <div class="truncate font-medium">
                            {{ displayThreadName(t) }}
                          </div>
                          <div class="text-xs text-muted-foreground mt-0.5">
                            <div class="truncate">{{ t.last_message }}</div>
                            <div class="mt-1 flex gap-0.5 items-center text-[10px]">
                              <span class="inline-flex px-1 py-0.5 bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200 rounded font-semibold">
                                #{{ t.thread_id }}
                              </span>
                              <span v-if="t.origin" class="inline-flex px-1 py-0.5 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded font-semibold">
                                {{ getThreadOriginLabel(t.origin) }}
                              </span>
                              <span v-if="t.thread_status === 'OPEN' && t.create_date"  class="inline-flex px-1 py-0.5 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded whitespace-nowrap">
                                ⏱ {{ getThreadElapsedTime(t) }}
                              </span>
                              <span
                                v-if="t.thread_status === 'OPEN' && t.create_date"
                                class="inline-flex px-1 py-0.5 rounded whitespace-nowrap"
                                :class="getThreadRemainingTime(t) === '00:00:00'
                                  ? 'bg-red-600 text-white font-bold animate-pulse'
                                  : 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'"
                              >
                                ⏳ {{ getThreadRemainingTime(t) }}
                              </span>
                            </div>                            
                          </div>
                        </div>

                        <div class="flex flex-col items-end gap-1 flex-shrink-0">
                          <div class="flex items-center gap-1">
                              <Badge :variant="t.thread_status === 'OPEN' ? 'default' : 'secondary'">
                                {{ t.thread_status }}
                              </Badge>
                              <span v-if="t.hasNewMessage && t.thread_status === 'OPEN'" class="ml-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-500 text-white text-[10px] font-bold animate-pulse">
                                <span class="inline-block w-1.5 h-1.5 rounded-full bg-white"></span>
                                Nuevo
                              </span>
                          </div>
                          <span class="text-[11px] text-muted-foreground">{{ formatPE(t.last_at) }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </button>

                <div v-if="!loadingThreads && !filteredThreads.length" class="text-sm text-muted-foreground p-2">
                 Sin resultados
                </div>

                <Button
                    v-if="threadsNextCursor && !loadingThreads"
                    variant="outline"
                    class="w-full"
                    @click="fetchThreads({ append: true })"
                    >
                    Cargar más
                </Button>
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
                    {{ activeThread ? displayThreadName(activeThread) : '' }}
                  </div>
                  <Badge variant="secondary">
                    {{ activeThread?.thread_status ?? '' }}
                  </Badge>
                </div>
                <div class="text-sm text-muted-foreground">
                  {{ activeThread?.phone ?? '' }}
                  
                </div>
              </div>

              <div class="flex items-center gap-2">
                <input
                  ref="imageInputRef"
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="onImageSelected"
                />

                <TooltipProvider>
                  <Tooltip>
                    <TooltipTrigger as-child>
                      <Button
                        variant="outline"
                        size="icon"
                        :disabled="draftDisabled"
                        @click="openAttachPicker"
                      >
                        <Paperclip class="h-5 w-5" />
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                      <p>Adjuntar</p>
                    </TooltipContent>
                  </Tooltip>
                </TooltipProvider>
                <TooltipProvider>
                  <Tooltip>
                    <TooltipTrigger as-child>
                      <Button
                        v-if="!isRestrictedRole && activeThread && (activeThread.thread_status === 'OPEN') && Number(activeThread.assigned_agent_id ?? 0) > 2"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        @click="openReassignModal"
                      >
                        <UserRoundCog class="h-5 w-5" />
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                      <p>Reasignar</p>
                    </TooltipContent>
                  </Tooltip>
                </TooltipProvider>
                <TooltipProvider>
                  <Tooltip>
                    <TooltipTrigger as-child>
                      <Button
                        v-if="activeThread && (activeThread.thread_status === 'OPEN')"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        :disabled="closingThreadId === activeThreadId"
                        @click="openCloseModal(activeThread.thread_id)"
                      >
                        <MessageSquareX class="h-5 w-5" />
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                      <p>Cerrar</p>
                    </TooltipContent>
                  </Tooltip>
                </TooltipProvider>
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" title="Opciones">
                      <MoreVertical class="h-5 w-5" />
                    </Button>
                  </DropdownMenuTrigger>

                  <DropdownMenuContent align="end">
                    <DropdownMenuItem @select="openHistory">
                      <Clock class="mr-2 h-4 w-4" />
                      Historial mensajes
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>
          </CardHeader>

          <Separator />

          <!-- Messages -->
          <div class="flex-1 overflow-hidden relative">
            <div ref="scrollerRef" class="h-full overflow-y-auto p-4" @scroll.passive="onScrollAreaScroll">
              <div class="space-y-3">

                  <div class="flex justify-center">
                    <Button
                        v-if="activeThreadId && messagesHasMore && messagesList.length"
                        variant="outline"
                        size="sm"
                        :disabled="loadingMessages"
                        @click="fetchMessages(activeThreadId, { prepend: true })"
                    >
                        {{ loadingMessages ? 'Cargando...' : 'Cargar anteriores' }}
                    </Button>

                    <div v-else-if="activeThreadId && !messagesHasMore && messagesList.length" class="text-xs text-muted-foreground">
                        No hay más mensajes
                    </div>
                  </div>
  
                  <div
                    v-for="m in activeMessages"
                    :key="m.id"
                    class="flex"
                    :class="m.sender === 'me' ? 'justify-end' : 'justify-start'"
                  >
                    <div
                        class="max-w-[78%] rounded-2xl px-4 py-2 text-sm shadow-sm overflow-hidden"
                        :class="m.sender === 'me'
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-foreground'"
                        >
                        
                        <div class="leading-relaxed break-words cursor-zoom-in" v-html="m.text" @click="onMessageBodyClick"></div>

                        <div class="mt-1 text-[11px] opacity-70" :class="m.sender === 'me' ? 'text-right' : ''">
                         {{ m.created_at }}
                        </div>
                    </div>
                  </div>

                  <div v-if="!activeMessages.length" class="text-sm text-muted-foreground">
                    Selecciona una conversación
                  </div>
              </div>
            </div>

            <!-- Botón flotante nuevo mensaje -->
            <Transition
              enter-active-class="transition duration-200 ease-out"
              enter-from-class="opacity-0 translate-y-2"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 translate-y-2"
            >
              <button
                v-if="hasUnreadBelow"
                type="button"
                @click="scrollToBottom"
                class="absolute bottom-4 left-1/2 -translate-x-1/2 z-10 flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-primary text-primary-foreground text-xs font-medium shadow-lg hover:bg-primary/90 transition-colors"
              >
                ▼ Nuevo mensaje
              </button>
            </Transition>
          </div>

          <Separator />

          <div class="p-3">
            <form class="flex items-center gap-2" @submit.prevent="sendMessage">
              <div class="relative flex-1">
                <RichDraftInput
                  ref="draftEditorRef"
                  v-model="draftHtml"
                  :disabled="draftDisabled"
                  :placeholder="draftDisabled && activeThreadId ? 'Conversación cerrada' : 'Escribe un mensaje…'"
                  class="pr-36"
                  @submit="onEditorSubmit" 
                />

                <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                  <TooltipProvider :delayDuration="200">
                    <Tooltip>
                      <TooltipTrigger as-child>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          class="h-8 w-8 p-0"
                          :disabled="draftDisabled"
                          @click="applyDraftFormat('bold')"
                        >
                          <Bold class="h-4 w-4" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent side="top" :sideOffset="6">Negrita</TooltipContent>
                    </Tooltip>

                    <Tooltip>
                      <TooltipTrigger as-child>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          class="h-8 w-8 p-0"
                          :disabled="draftDisabled"
                          @click="applyDraftFormat('italic')"
                        >
                          <Italic class="h-4 w-4" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent side="top" :sideOffset="6">Cursiva</TooltipContent>
                    </Tooltip>

                    <Tooltip>
                      <TooltipTrigger as-child>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          class="h-8 w-8 p-0"
                          :disabled="draftDisabled"
                          @click="applyDraftFormat('underline')"
                        >
                          <Underline class="h-4 w-4" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent side="top" :sideOffset="6">Subrayado</TooltipContent>
                    </Tooltip>

                    <Tooltip>
                      <TooltipTrigger as-child>
                        <button
                          type="button"
                          :disabled="draftDisabled"
                          @click="toggleEmojiPickerChat"
                          class="h-8 w-8 flex items-center justify-center bg-muted rounded-full hover:bg-muted/70 disabled:opacity-50 disabled:cursor-not-allowed"
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
                </div>

                <div v-if="showEmojiPickerChat" class="absolute bottom-12 right-0 z-50">
                  <EmojiPicker @select="addEmojiToDraft" :native="true" />
                </div>
              </div>

              <Button type="submit" class="h-11" :disabled="!canSend">
                <Send class="mr-2 h-4 w-4" />
                Enviar
              </Button>
            </form>
          </div>
        </Card>
      </div>
    </div>

    <Dialog v-model:open="closeOpen">
      <DialogContent class="sm:max-w-[560px]">
        <DialogHeader>
          <DialogTitle>Cerrar conversación</DialogTitle>
          <DialogDescription>
            Selecciona una tipificación (1 → 2 → 3). No podrás cerrar sin tipificación.
          </DialogDescription>
        </DialogHeader>

        <div v-if="closeLoading" class="text-sm text-muted-foreground py-4">
          Cargando tipificaciones...
        </div>

        <div v-else class="grid gap-4 py-2">
          <!-- Tipificación 1 -->
          <div class="grid gap-2">
            <Label>Tipificación 1</Label>
            <Popover v-model:open="t1Open">
              <PopoverTrigger as-child>
                <Button variant="outline" role="combobox" class="w-full justify-between">
                  <span class="truncate">{{ selT1 || 'Seleccionar...' }}</span>
                  <ChevronsUpDown class="ml-2 h-4 w-4 opacity-50" />
                </Button>
              </PopoverTrigger>
              <PopoverContent class="w-[520px] p-0" align="start">
                <Command>
                  <CommandInput placeholder="Buscar..." />
                  <CommandEmpty>Sin resultados</CommandEmpty>
                  <CommandGroup>
                    <CommandItem
                      v-for="opt in t1Options"
                      :key="opt"
                      :value="opt"
                      @select="() => { selT1 = opt; t1Open = false }"
                    >
                      <Check :class="cn('mr-2 h-4 w-4', selT1 === opt ? 'opacity-100' : 'opacity-0')" />
                      {{ opt }}
                    </CommandItem>
                  </CommandGroup>
                </Command>
              </PopoverContent>
            </Popover>
          </div>

          <!-- Tipificación 2 -->
          <div class="grid gap-2">
            <Label>Tipificación 2</Label>
            <Popover v-model:open="t2Open">
              <PopoverTrigger as-child>
                <Button variant="outline" role="combobox" class="w-full justify-between" :disabled="!selT1">
                  <span class="truncate">{{ selT2 || 'Seleccionar...' }}</span>
                  <ChevronsUpDown class="ml-2 h-4 w-4 opacity-50" />
                </Button>
              </PopoverTrigger>
              <PopoverContent class="w-[520px] p-0" align="start">
                <Command>
                  <CommandInput placeholder="Buscar..." />
                  <CommandEmpty>Sin resultados</CommandEmpty>
                  <CommandGroup>
                    <CommandItem
                      v-for="opt in t2Options"
                      :key="opt"
                      :value="opt"
                      @select="() => { selT2 = opt; t2Open = false }"
                    >
                      <Check :class="cn('mr-2 h-4 w-4', selT2 === opt ? 'opacity-100' : 'opacity-0')" />
                      {{ opt }}
                    </CommandItem>
                  </CommandGroup>
                </Command>
              </PopoverContent>
            </Popover>
          </div>

          <!-- Tipificación 3 (esta ya amarra a id) -->
          <div class="grid gap-2">
            <Label>Tipificación 3</Label>
            <Popover v-model:open="t3Open">
              <PopoverTrigger as-child>
                <Button variant="outline" role="combobox" class="w-full justify-between" :disabled="!selT1 || !selT2">
                  <span class="truncate">{{ selT3Label || 'Seleccionar...' }}</span>
                  <ChevronsUpDown class="ml-2 h-4 w-4 opacity-50" />
                </Button>
              </PopoverTrigger>
              <PopoverContent class="w-[520px] p-0" align="start">
                <Command>
                  <CommandInput placeholder="Buscar..." />
                  <CommandEmpty>Sin resultados</CommandEmpty>
                  <CommandGroup>
                    <CommandItem
                      v-for="opt in t3Options"
                      :key="opt.id"
                      :value="opt.label"
                      @select="() => { selT3Id = opt.id; selT3Label = opt.label; t3Open = false }"
                    >
                      <Check :class="cn('mr-2 h-4 w-4', selT3Id === opt.id ? 'opacity-100' : 'opacity-0')" />
                      {{ opt.label }}
                    </CommandItem>
                  </CommandGroup>
                </Command>
              </PopoverContent>
            </Popover>
          </div>
        </div>

        <DialogFooter class="gap-2">
          <Button type="button" variant="outline" @click="closeOpen = false">Cancelar</Button>
          <Button type="button" :disabled="!canConfirmClose || closingThreadId === closeThreadTargetId" @click="confirmCloseThread">
            Cerrar conversación
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="imagePreviewOpen">
      <DialogContent class="sm:max-w-[680px]">
        <DialogHeader>
          <DialogTitle>Previsualizar imagen</DialogTitle>
          <DialogDescription>
            Verifica la imagen y el payload antes de enviar al API.
          </DialogDescription>
        </DialogHeader>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <p class="text-sm font-medium">Imagen</p>
            <div class="rounded-md border p-2 bg-muted/20">
              <img
                v-if="imagePreviewUrl"
                :src="imagePreviewUrl"
                alt="Preview"
                class="max-h-72 w-full rounded-md object-contain"
              />
            </div>
            <p class="text-xs text-muted-foreground">
              Nombre final: {{ imageFileName }}
            </p>
          </div>

          <div class="space-y-2">
            <p class="text-sm font-medium">Payload</p>
            <div class="rounded-md border bg-muted p-3">
              <pre class="max-h-72 overflow-auto text-xs leading-relaxed whitespace-pre-wrap">{{ imagePayloadPreview }}</pre>
            </div>
          </div>
        </div>

        <DialogFooter class="gap-2">
          <DialogClose as-child>
            <Button type="button" variant="outline" @click="clearImageDraft">Cancelar</Button>
          </DialogClose>
          <Button type="button" :disabled="sendingImage || !imageFile" @click="sendAttachedImage">
            {{ sendingImage ? 'Enviando...' : 'Enviar' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="lightboxOpen">
      <DialogContent class="sm:max-w-[90vw] p-0 overflow-hidden">
        <div class="relative bg-black/95 text-white">
          <div class="absolute right-3 top-3 z-10 flex items-center gap-2">
            <Button type="button" variant="secondary" size="icon" @click="zoomOutLightbox">
              <ZoomOut class="h-4 w-4" />
            </Button>
            <Button type="button" variant="secondary" size="icon" @click="zoomInLightbox">
              <ZoomIn class="h-4 w-4" />
            </Button>
            <Button type="button" variant="secondary" @click="resetLightboxZoom">
              100%
            </Button>
          </div>

          <div class="h-[80vh] w-full overflow-auto flex items-center justify-center" @wheel.prevent="onLightboxWheel">
            <img
              v-if="lightboxSrc"
              :src="lightboxSrc"
              alt="Imagen ampliada"
              class="max-h-[78vh] max-w-full object-contain transition-transform duration-150"
              :style="{ transform: `scale(${lightboxZoom})` }"
            />
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="reassignOpen">
      <DialogContent class="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>Reasignar conversación</DialogTitle>
          <DialogDescription>
            Selecciona un usuario del mismo room para reasignar el thread.
          </DialogDescription>
        </DialogHeader>

        <div v-if="reassignLoading" class="flex items-center gap-2 py-4 text-sm text-muted-foreground">
          <Loader2 class="h-4 w-4 animate-spin" />
          Cargando usuarios...
        </div>

        <div v-else class="grid gap-3 py-1">
          <div class="text-sm">
            <span class="text-muted-foreground">Usuario actual: </span>
            <span class="font-medium">
              {{ reassignCurrentUser?.username || reassignCurrentUser?.name || '-' }}
            </span>
          </div>

          <div class="grid gap-2 max-h-72 overflow-y-auto">
            <button
              v-for="u in reassignCandidates"
              :key="u.id"
              type="button"
              class="flex items-center gap-3 rounded-lg border px-3 py-2 text-left hover:bg-muted transition-colors"
              :class="[
                reassignToUserId === u.id ? 'border-primary bg-muted' : 'border-border',
                u.id === reassignCurrentUser?.id ? 'opacity-60 cursor-not-allowed' : '',
              ]"
              :disabled="u.id === reassignCurrentUser?.id"
              @click="u.id !== reassignCurrentUser?.id ? (reassignToUserId = u.id) : null"
            >
              <Avatar class="h-8 w-8 flex-shrink-0">
                <AvatarFallback class="text-xs">
                  {{ (u.username || u.name || 'U').slice(0, 2).toUpperCase() }}
                </AvatarFallback>
              </Avatar>
              <span class="truncate text-sm">{{ u.username || u.name }}</span>
              <span v-if="u.id === reassignCurrentUser?.id" class="ml-auto text-[11px] text-muted-foreground">Actual</span>
            </button>

            <div v-if="!reassignCandidates.length" class="text-sm text-muted-foreground py-2">
              No hay usuarios disponibles para reasignar.
            </div>
          </div>
        </div>

        <DialogFooter class="gap-2">
          <Button type="button" variant="outline" @click="reassignOpen = false">Cancelar</Button>
          <Button type="button" :disabled="!reassignToUserId || reassignSubmitting" @click="confirmReassignThread">
            Reasignar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Sheet v-model:open="historyOpen">
      <SheetContent side="right" class="w-full sm:max-w-[520px]">
        <SheetHeader>
          <SheetTitle>Historial por teléfono</SheetTitle>
          <SheetDescription>
            {{ activeThread?.name ?? '' }} — {{ activeThread?.phone ?? '' }}
          </SheetDescription>
        </SheetHeader>

        <div class="mt-4">
          <div class="flex items-center justify-between">
            <Badge variant="secondary">
              Company: {{ companyName }} / Canal: {{ channelName }}
            </Badge>

            <Button
              v-if="historyHasMore"
              size="sm"
              variant="outline"
              :disabled="historyLoading"
              @click="fetchHistory({ append: true })"
            >
              {{ historyLoading ? 'Cargando...' : 'Cargar más' }}
            </Button>
          </div>

          <ScrollArea class="mt-3 h-[70vh] pr-2">
            <div v-if="!historyRows.length && !historyLoading" class="text-sm text-muted-foreground p-2">
              Sin historial
            </div>

            <div class="space-y-3">
              <div
                v-for="m in historyRows"
                :key="`h-${m.message_id}`"
                class="flex"
                :class="m.enviado_por === 'USUARIO' ? 'justify-start' : 'justify-end'"
              >
                <div
                  class="max-w-[78%] rounded-2xl px-4 py-2 text-sm shadow-sm overflow-hidden border"
                  :class="m.enviado_por === 'USUARIO'
                    ? 'bg-muted text-foreground'
                    : 'bg-primary text-primary-foreground border-transparent'"
                >
                  <!-- Header -->
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                      <Badge
                        :variant="m.enviado_por === 'USUARIO' ? 'default' : 'secondary'"
                        class="h-6 w-6 p-0 inline-flex items-center justify-center rounded-full"
                      >
                        <User v-if="m.enviado_por === 'USUARIO'" class="h-3.5 w-3.5" />
                        <Bot v-else class="h-3.5 w-3.5" />
                      </Badge>

                      <span class="text-xs opacity-70">#{{ m.thread_id }}</span>
                    </div>

                    <span class="text-xs opacity-70">
                      {{ formatPE(m.message_create_date) }}
                    </span>
                  </div>

                  <!-- Body -->
                  <div
                    class="mt-2 leading-relaxed break-words"
                    @click="onMessageBodyClick"
                    v-html="getMessageHtml(m)"
                  ></div>
                </div>
              </div>
            </div>

            <div v-if="historyLoading" class="text-sm text-muted-foreground p-2">
              Cargando...
            </div>
          </ScrollArea>
        </div>
      </SheetContent>
    </Sheet>

    <Dialog v-model:open="phoneSearchOpen">
      <DialogContent class="sm:max-w-[420px]">
        <DialogHeader>
          <DialogTitle>Buscar por teléfono</DialogTitle>
          <DialogDescription>
            Busca en threads.sender_id o customers.phone (ignora fecha/estado).
          </DialogDescription>
        </DialogHeader>

        <div class="grid gap-2 py-2">
          <Label>Teléfono / Sender ID</Label>
          <Input v-model="phoneSearchDraft" placeholder="Ej: 51942890820" />
        </div>

        <DialogFooter class="gap-2">
          <Button type="button" variant="outline" @click="phoneSearchOpen = false">Cancelar</Button>
          <Button type="button" :disabled="!phoneSearchDraft.trim()" @click="applyPhoneSearch">
            Buscar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Agent User Picker Modal -->
    <Dialog v-model:open="agentPickerOpen">
      <DialogContent class="sm:max-w-[420px]">
        <DialogHeader>
          <DialogTitle>Seleccionar agente</DialogTitle>
          <DialogDescription>
            Filtra los threads por el agente asignado.
          </DialogDescription>
        </DialogHeader>

        <div v-if="agentPickerLoading" class="flex items-center gap-2 py-4 text-sm text-muted-foreground">
          <Loader2 class="h-4 w-4 animate-spin" />
          Cargando agentes...
        </div>

        <div v-else class="flex flex-col gap-1 max-h-80 overflow-y-auto py-1">
          <button
            type="button"
            class="flex items-center gap-3 rounded-lg px-3 py-2 text-left hover:bg-muted transition-colors"
            :class="agentFilterType === 'asignados' && !selectedAgentUser ? 'bg-muted font-semibold' : ''"
            @click="selectAllAssignedAgents"
          >
            <Avatar class="h-8 w-8 flex-shrink-0">
              <AvatarFallback class="text-xs">
                TA
              </AvatarFallback>
            </Avatar>
            <span class="truncate text-sm">Todos los asignados</span>
          </button>

          <button
            v-for="user in agentUsers"
            :key="user.id"
            type="button"
            class="flex items-center gap-3 rounded-lg px-3 py-2 text-left hover:bg-muted transition-colors"
            :class="selectedAgentUser?.id === user.id ? 'bg-muted font-semibold' : ''"
            @click="selectAgent(user)"
          >
            <Avatar class="h-8 w-8 flex-shrink-0">
              <AvatarFallback class="text-xs">
                {{ user.name.split(' ').slice(0, 2).map((x: string) => x[0]).join('').toUpperCase() }}
              </AvatarFallback>
            </Avatar>
            <span class="truncate text-sm">{{ user.name }}</span>
          </button>
          <div v-if="!agentUsers.length" class="text-sm text-muted-foreground py-2 px-3">
            No hay chats disponibles
          </div>
        </div>

        <DialogFooter class="gap-2">
          <Button type="button" variant="ghost" @click="agentPickerOpen = false">Cancelar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>