<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref, nextTick } from 'vue'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Separator } from '@/components/ui/separator'

import { Search, Send, Paperclip, MoreVertical } from 'lucide-vue-next'

type Message = {
  id: number
  conversation_id: number
  sender: 'me' | 'them'
  text: string
  created_at: string
}

type Conversation = {
  id: number
  name: string
  last_message: string
  unread: number
  status?: 'online' | 'offline'
}

const conversations = ref<Conversation[]>([
  { id: 1, name: 'Soporte Talina', last_message: '¿Te llegó el archivo?', unread: 2, status: 'online' },
  { id: 2, name: 'Marketing', last_message: 'Listo el copy de la campaña', unread: 0, status: 'offline' },
  { id: 3, name: 'Alejandro', last_message: 'Dale, lo reviso', unread: 1, status: 'online' },
])

const messages = ref<Message[]>([
  { id: 1, conversation_id: 1, sender: 'them', text: 'Hola 👋 ¿cómo va?', created_at: '10:40' },
  { id: 2, conversation_id: 1, sender: 'me', text: 'Todo bien. Estoy maqueteando el chat primero.', created_at: '10:41' },
  { id: 3, conversation_id: 1, sender: 'them', text: 'Perfecto. Luego conectamos broadcasting.', created_at: '10:41' },
  { id: 4, conversation_id: 1, sender: 'me', text: 'Sí, por ahora solo UI con shadcn-vue.', created_at: '10:42' },
])

const activeId = ref<number>(conversations.value[0].id)
const q = ref('')
const draft = ref('')

const filteredConversations = computed(() => {
  const term = q.value.trim().toLowerCase()
  if (!term) return conversations.value
  return conversations.value.filter(c => c.name.toLowerCase().includes(term))
})

const activeConversation = computed(() =>
  conversations.value.find(c => c.id === activeId.value)
)

const activeMessages = computed(() =>
  messages.value
    .filter(m => m.conversation_id === activeId.value)
    .sort((a, b) => a.id - b.id)
)

// Scroll-to-bottom (simple)
const scrollerRef = ref<HTMLElement | null>(null)
const scrollToBottom = async () => {
  await nextTick()
  const el = scrollerRef.value
  if (!el) return
  el.scrollTop = el.scrollHeight
}

const setActive = async (id: number) => {
  activeId.value = id
  // “marcar como leído” (mock)
  const c = conversations.value.find(x => x.id === id)
  if (c) c.unread = 0
  await scrollToBottom()
}

const sendMessage = async () => {
  const text = draft.value.trim()
  if (!text) return

  const newId = (messages.value.at(-1)?.id ?? 0) + 1
  messages.value.push({
    id: newId,
    conversation_id: activeId.value,
    sender: 'me',
    text,
    created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
  })

  // actualizar preview en sidebar (mock)
  const c = conversations.value.find(x => x.id === activeId.value)
  if (c) c.last_message = text

  draft.value = ''
  await scrollToBottom()
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
              <Button variant="ghost" size="icon">
                <MoreVertical class="h-5 w-5" />
              </Button>
            </CardTitle>

            <div class="relative mt-2">
              <Search class="absolute left-3 top-2.5 h-4 w-4 opacity-60" />
              <Input v-model="q" class="pl-9" placeholder="Buscar conversación..." />
            </div>
          </CardHeader>

          <CardContent class="pt-0">
            <ScrollArea class="h-[62vh] pr-2">
              <div class="space-y-2">
                <button
                  v-for="c in filteredConversations"
                  :key="c.id"
                  type="button"
                  @click="setActive(c.id)"
                  class="w-full rounded-xl border p-3 text-left transition hover:bg-muted"
                  :class="c.id === activeId ? 'border-primary/50 bg-muted' : 'border-border'"
                >
                  <div class="flex items-center gap-3">
                    <Avatar>
                      <AvatarFallback>
                        {{ c.name.split(' ').slice(0,2).map(x => x[0]).join('').toUpperCase() }}
                      </AvatarFallback>
                    </Avatar>

                    <div class="min-w-0 flex-1">
                      <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                          <div class="truncate font-medium">{{ c.name }}</div>
                          <div class="truncate text-sm text-muted-foreground">{{ c.last_message }}</div>
                        </div>

                        <div class="flex items-center gap-2">
                          <span
                            class="h-2 w-2 rounded-full"
                            :class="c.status === 'online' ? 'bg-emerald-500' : 'bg-zinc-300'"
                            title="Estado"
                          />
                          <Badge v-if="c.unread" variant="default">{{ c.unread }}</Badge>
                        </div>
                      </div>
                    </div>
                  </div>
                </button>
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
                    {{ activeConversation?.name ?? '—' }}
                  </div>
                  <Badge variant="secondary">
                    {{ activeConversation?.status === 'online' ? 'Online' : 'Offline' }}
                  </Badge>
                </div>
                <div class="text-sm text-muted-foreground">
                  Maqueta UI (sin backend aún)
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
                </div>
              </div>
            </ScrollArea>
          </div>

          <Separator />

          <!-- Composer -->
          <div class="p-3">
            <form class="flex items-center gap-2" @submit.prevent="sendMessage">
              <Input
                v-model="draft"
                placeholder="Escribe un mensaje…"
                class="h-11"
                @keydown.enter.exact.prevent="sendMessage"
              />
              <Button type="submit" class="h-11">
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
