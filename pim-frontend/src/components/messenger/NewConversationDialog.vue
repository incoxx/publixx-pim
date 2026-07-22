<script setup>
import { ref, watch, computed } from 'vue'
import { X } from 'lucide-vue-next'
import messengerApi from '@/api/messenger'
import { useMessengerStore } from '@/stores/messenger'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'started'])

const messengerStore = useMessengerStore()

const mode = ref('users')
const colleagues = ref([])
const teams = ref([])
const selectedUserIds = ref([])
const selectedTeamId = ref('')
const body = ref('')
const sending = ref(false)
const error = ref('')

const canSend = computed(() => {
  if (!body.value.trim()) return false
  if (mode.value === 'users') return selectedUserIds.value.length > 0
  if (mode.value === 'team') return !!selectedTeamId.value
  return true // 'all'
})

watch(() => props.modelValue, async (open) => {
  if (!open) return
  error.value = ''
  body.value = ''
  selectedUserIds.value = []
  selectedTeamId.value = ''
  mode.value = 'users'
  const [colleaguesRes, teamsRes] = await Promise.all([messengerApi.colleagues(), messengerApi.teams()])
  colleagues.value = colleaguesRes.data.data || []
  teams.value = teamsRes.data.data || []
})

function toggleUser(id) {
  const idx = selectedUserIds.value.indexOf(id)
  if (idx === -1) selectedUserIds.value.push(id)
  else selectedUserIds.value.splice(idx, 1)
}

async function send() {
  if (!canSend.value) return
  sending.value = true
  error.value = ''
  try {
    const recipients = mode.value === 'users'
      ? { mode: 'users', user_ids: selectedUserIds.value }
      : mode.value === 'team'
        ? { mode: 'team', team_id: selectedTeamId.value }
        : { mode: 'all' }

    const conversations = await messengerStore.startConversation({ recipients, body: body.value.trim() })
    emit('started', conversations)
    close()
  } catch (e) {
    error.value = e.response?.data?.detail || 'Nachricht konnte nicht gesendet werden'
  } finally {
    sending.value = false
  }
}

function close() {
  emit('update:modelValue', false)
}
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/40" @click="close" />
      <div class="relative bg-[var(--color-surface)] rounded-xl shadow-xl w-full max-w-md flex flex-col m-4">
        <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
          <h3 class="text-sm font-semibold">Neue Nachricht</h3>
          <button class="p-1 rounded hover:bg-[var(--color-bg)]" @click="close">
            <X class="w-4 h-4" :stroke-width="2" />
          </button>
        </div>

        <div class="p-4 space-y-3">
          <!-- Empfänger-Modus -->
          <div class="flex items-center rounded-lg border border-[var(--color-border)] p-0.5 text-xs w-fit">
            <button
              class="px-2.5 py-1 rounded-md"
              :class="mode === 'users' ? 'bg-[var(--color-bg)] font-medium' : 'opacity-70'"
              @click="mode = 'users'"
            >Person(en)</button>
            <button
              class="px-2.5 py-1 rounded-md"
              :class="mode === 'team' ? 'bg-[var(--color-bg)] font-medium' : 'opacity-70'"
              @click="mode = 'team'"
            >Team</button>
            <button
              class="px-2.5 py-1 rounded-md"
              :class="mode === 'all' ? 'bg-[var(--color-bg)] font-medium' : 'opacity-70'"
              @click="mode = 'all'"
            >Alle</button>
          </div>

          <div v-if="mode === 'users'" class="max-h-40 overflow-y-auto border border-[var(--color-border)] rounded-lg divide-y divide-[var(--color-border)]">
            <label
              v-for="colleague in colleagues"
              :key="colleague.id"
              class="flex items-center gap-2 px-3 py-2 text-xs cursor-pointer hover:bg-[var(--color-bg)]"
            >
              <input type="checkbox" class="pim-checkbox" :checked="selectedUserIds.includes(colleague.id)" @change="toggleUser(colleague.id)" />
              {{ colleague.name }}
            </label>
            <p v-if="!colleagues.length" class="px-3 py-4 text-center text-xs text-[var(--color-text-tertiary)]">Keine Kollegen gefunden.</p>
          </div>

          <select v-else-if="mode === 'team'" v-model="selectedTeamId" class="pim-input text-xs w-full">
            <option value="" disabled>Team wählen…</option>
            <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
          </select>

          <p v-else class="text-xs text-[var(--color-text-tertiary)]">
            Nachricht geht an alle aktiven Nutzer im System (je eine eigene 1:1-Konversation).
          </p>

          <textarea
            v-model="body"
            rows="3"
            class="pim-input text-sm w-full resize-none"
            placeholder="Nachricht schreiben…"
          />

          <p v-if="error" class="text-xs text-[var(--color-error)]">{{ error }}</p>
        </div>

        <div class="flex justify-end gap-2 px-4 py-3 border-t border-[var(--color-border)]">
          <button class="pim-btn pim-btn-ghost text-xs" @click="close">Abbrechen</button>
          <button class="pim-btn pim-btn-primary text-xs" :disabled="!canSend || sending" @click="send">Senden</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
