<script setup lang="ts">
import { Button } from '@/components/ui/button'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { Bot, Clock, List, User } from 'lucide-vue-next'

type AgentFilterType = 'bot' | 'holding' | 'asignados' | 'all'

const props = defineProps<{
  modelValue: AgentFilterType
  selectedAgentName?: string | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: AgentFilterType]
  'open-user-picker': []
}>()

const handleClick = (value: AgentFilterType) => {
  if (value === 'asignados') {
    emit('open-user-picker')
  } else {
    emit('update:modelValue', value)
  }
}

const tooltip = (item: { value: AgentFilterType; label: string }) => {
  if (item.value === 'asignados' && props.modelValue === 'asignados' && props.selectedAgentName) {
    return props.selectedAgentName
  }
  return item.label
}

const items: Array<{ value: AgentFilterType; label: string; icon: any }> = [
  { value: 'bot', label: 'BOT', icon: Bot },
  { value: 'holding', label: 'HOLDING', icon: Clock },
  { value: 'asignados', label: 'ASIGNADOS', icon: User },
  { value: 'all', label: 'TODOS', icon: List },
]
</script>

<template>
  <TooltipProvider :delay-duration="0">
    <div class="flex flex-col gap-3">
      <Tooltip v-for="item in items" :key="item.value">
        <TooltipTrigger as-child>
          <Button
            :variant="modelValue === item.value ? 'default' : 'outline'"
            size="icon"
            class="h-12 w-12 rounded-2xl shadow-sm"
            @click="handleClick(item.value)"
          >
            <component :is="item.icon" class="h-5 w-5" />
          </Button>
        </TooltipTrigger>
        <TooltipContent side="right">
          <p>{{ tooltip(item) }}</p>
        </TooltipContent>
      </Tooltip>
    </div>
  </TooltipProvider>
</template>