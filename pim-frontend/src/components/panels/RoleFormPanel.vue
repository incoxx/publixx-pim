<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { roles } from '@/api/users'
import roleRestrictionsApi from '@/api/roleRestrictions'
import { Check, Minus } from 'lucide-vue-next'
import RoleRestrictionSection from '@/components/roles/RoleRestrictionSection.vue'
import HierarchyNodeRestrictionPicker from '@/components/roles/HierarchyNodeRestrictionPicker.vue'

const props = defineProps({
  role: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const saving = ref(false)
const errors = ref({})
const roleName = ref(props.role?.name || '')
const selectedPermissions = ref(new Set(
  props.role?.permissions?.map(p => p.name) || []
))
const availablePermissions = ref({})

// Instance restrictions
const attributeTypeRestrictions = ref([])
const mediaUsageTypeRestrictions = ref([])
const hierarchyNodeRestrictions = ref([])
const attributeTypeItems = ref([])
const mediaUsageTypeItems = ref([])

// Tab permissions
const tabPermissions = ref({})
const productEditorTabs = [
  { key: 'base-data', label: 'Grunddaten' },
  { key: 'attributes', label: 'Attribute' },
  { key: 'variant-attributes', label: 'Varianten-Attribute' },
  { key: 'variants', label: 'Varianten' },
  { key: 'media', label: 'Medien' },
  { key: 'prices', label: 'Preise' },
  { key: 'relations', label: 'Beziehungen' },
  { key: 'output-hierarchies', label: 'Ausgabehierarchien' },
  { key: 'preview', label: 'Vorschau' },
  { key: 'versions', label: 'Versionen' },
  { key: 'scheduled-actions', label: 'Planung' },
  { key: 'workflow-history', label: 'Workflow-Verlauf' },
]
const tabAccessLevels = [
  { value: 'write', label: 'Schreiben' },
  { value: 'read', label: 'Lesen' },
  { value: 'hidden', label: 'Versteckt' },
]

function getTabAccess(tabKey) {
  return tabPermissions.value[tabKey] || 'write'
}

function setTabAccess(tabKey, level) {
  tabPermissions.value = { ...tabPermissions.value, [tabKey]: level }
}

const isEdit = computed(() => !!props.role)

// Entity labels for display
// Labels und Gruppen kommen vom Backend (GET /permissions → labels, groups)
const entityLabels = ref({})
const categoryGroups = ref([])

// Standard action columns
const standardActions = ['view', 'create', 'edit', 'delete']

// All unique actions across all entities
const allActions = computed(() => {
  const actions = new Set(standardActions)
  for (const entityActions of Object.values(availablePermissions.value)) {
    for (const action of entityActions) {
      actions.add(action)
    }
  }
  return [...actions]
})

// Action labels
const actionLabels = {
  'view': 'Lesen',
  'create': 'Erstellen',
  'edit': 'Bearbeiten',
  'delete': 'Löschen',
  'execute': 'Ausführen',
  'move': 'Verschieben',
  'mappings.edit': 'Mappings',
  'manage': 'Verwalten',
}

// Grouped entities with their permissions
const groupedPermissions = computed(() => {
  const perms = availablePermissions.value
  const assignedEntities = new Set()

  const groups = categoryGroups.value.map(group => {
    const entities = group.entities
      .filter(e => perms[e])
      .map(e => {
        assignedEntities.add(e)
        return { key: e, label: entityLabels.value[e] || e, actions: perms[e] }
      })
    return { label: group.label, entities }
  }).filter(g => g.entities.length > 0)

  // Add ungrouped entities
  const ungrouped = Object.keys(perms)
    .filter(e => !assignedEntities.has(e))
    .map(e => ({ key: e, label: entityLabels.value[e] || e, actions: perms[e] }))

  if (ungrouped.length > 0) {
    groups.push({ label: 'Sonstige', entities: ungrouped })
  }

  return groups
})

function hasPermission(entity, action) {
  return selectedPermissions.value.has(`${entity}.${action}`)
}

function togglePermission(entity, action) {
  const perm = `${entity}.${action}`
  if (selectedPermissions.value.has(perm)) {
    selectedPermissions.value.delete(perm)
  } else {
    selectedPermissions.value.add(perm)
  }
  // Trigger reactivity
  selectedPermissions.value = new Set(selectedPermissions.value)
}

function isRowAllSelected(entity, actions) {
  return actions.every(a => selectedPermissions.value.has(`${entity}.${a}`))
}

function isRowPartialSelected(entity, actions) {
  const selected = actions.filter(a => selectedPermissions.value.has(`${entity}.${a}`))
  return selected.length > 0 && selected.length < actions.length
}

function toggleRow(entity, actions) {
  const allSelected = isRowAllSelected(entity, actions)
  for (const action of actions) {
    const perm = `${entity}.${action}`
    if (allSelected) {
      selectedPermissions.value.delete(perm)
    } else {
      selectedPermissions.value.add(perm)
    }
  }
  selectedPermissions.value = new Set(selectedPermissions.value)
}

function isColumnAllSelected(action) {
  const entities = Object.keys(availablePermissions.value).filter(e =>
    availablePermissions.value[e].includes(action)
  )
  return entities.length > 0 && entities.every(e => selectedPermissions.value.has(`${e}.${action}`))
}

function isColumnPartialSelected(action) {
  const entities = Object.keys(availablePermissions.value).filter(e =>
    availablePermissions.value[e].includes(action)
  )
  const selected = entities.filter(e => selectedPermissions.value.has(`${e}.${action}`))
  return selected.length > 0 && selected.length < entities.length
}

function toggleColumn(action) {
  const entities = Object.keys(availablePermissions.value).filter(e =>
    availablePermissions.value[e].includes(action)
  )
  const allSelected = isColumnAllSelected(action)
  for (const entity of entities) {
    const perm = `${entity}.${action}`
    if (allSelected) {
      selectedPermissions.value.delete(perm)
    } else {
      selectedPermissions.value.add(perm)
    }
  }
  selectedPermissions.value = new Set(selectedPermissions.value)
}

function selectAll() {
  for (const [entity, actions] of Object.entries(availablePermissions.value)) {
    for (const action of actions) {
      selectedPermissions.value.add(`${entity}.${action}`)
    }
  }
  selectedPermissions.value = new Set(selectedPermissions.value)
}

function deselectAll() {
  selectedPermissions.value = new Set()
}

const isAllSelected = computed(() => {
  for (const [entity, actions] of Object.entries(availablePermissions.value)) {
    for (const action of actions) {
      if (!selectedPermissions.value.has(`${entity}.${action}`)) return false
    }
  }
  return Object.keys(availablePermissions.value).length > 0
})

const isPartialSelected = computed(() => {
  return selectedPermissions.value.size > 0 && !isAllSelected.value
})

async function handleSubmit() {
  if (!roleName.value.trim()) {
    errors.value = { name: 'Name ist erforderlich' }
    return
  }

  saving.value = true
  errors.value = {}

  const payload = {
    name: roleName.value.trim(),
    permissions: [...selectedPermissions.value],
  }

  try {
    let roleId
    if (isEdit.value) {
      await roles.update(props.role.id, payload)
      roleId = props.role.id
    } else {
      const { data } = await roles.create(payload)
      roleId = (data.data || data).id
    }

    // Sync instance restrictions and tab permissions
    if (roleId) {
      await Promise.all([
        syncRestrictions(roleId),
        syncTabPerms(roleId),
      ])
    }

    authStore.closePanel()
    if (props.onSaved) props.onSaved()
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data.errors || {}
      for (const [key, val] of Object.entries(serverErrors)) {
        errors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally {
    saving.value = false
  }
}

async function syncRestrictions(roleId) {
  const syncType = async (type, restrictions) => {
    if (restrictions.length === 0) {
      // Remove all restrictions for this type (= full access)
      try { await roleRestrictionsApi.remove(roleId, type) } catch { /* ignore 404 */ }
    } else {
      await roleRestrictionsApi.sync(roleId, type, restrictions.map(r => ({
        id: r.id,
        access_level: r.access_level,
      })))
    }
  }

  await Promise.all([
    syncType('attribute-types', attributeTypeRestrictions.value),
    syncType('media-usage-types', mediaUsageTypeRestrictions.value),
    syncType('hierarchy-nodes', hierarchyNodeRestrictions.value),
  ])
}

async function syncTabPerms(roleId) {
  const tabs = Object.entries(tabPermissions.value)
    .filter(([, level]) => level !== 'write')
    .map(([tab_key, access_level]) => ({ tab_key, access_level }))
  await roleRestrictionsApi.syncTabPermissions(roleId, tabs)
}

onMounted(async () => {
  loading.value = true
  try {
    const [permResponse, atResponse, mutResponse] = await Promise.all([
      roles.listPermissions(),
      import('@/api/attributes').then(m => m.attributeTypes.list({ per_page: 100 })),
      import('@/api/mediaUsageTypes').then(m => m.default.list()),
    ])

    availablePermissions.value = permResponse.data.data || permResponse.data

    // Labels und Gruppen vom Backend uebernehmen (Single Source of Truth)
    if (permResponse.data.labels) entityLabels.value = permResponse.data.labels
    if (permResponse.data.groups) categoryGroups.value = permResponse.data.groups

    // Load attribute types and media usage types for restriction pickers
    const atData = atResponse.data.data || atResponse.data
    attributeTypeItems.value = Array.isArray(atData) ? atData : (atData.data || [])

    const mutData = mutResponse.data.data || mutResponse.data
    mediaUsageTypeItems.value = Array.isArray(mutData) ? mutData : (mutData.data || [])

    // Load existing restrictions when editing
    if (isEdit.value && props.role?.id) {
      try {
        const { data } = await roleRestrictionsApi.list(props.role.id)
        const restrictions = data.data || {}

        if (restrictions['attribute-types']) {
          attributeTypeRestrictions.value = restrictions['attribute-types'].map(r => ({
            id: r.restrictable_id,
            access_level: r.access_level,
            name: r.restrictable?.name_de || r.restrictable_id,
          }))
        }
        if (restrictions['media-usage-types']) {
          mediaUsageTypeRestrictions.value = restrictions['media-usage-types'].map(r => ({
            id: r.restrictable_id,
            access_level: r.access_level,
            name: r.restrictable?.name_de || r.restrictable_id,
          }))
        }
        if (restrictions['hierarchy-nodes']) {
          hierarchyNodeRestrictions.value = restrictions['hierarchy-nodes'].map(r => ({
            id: r.restrictable_id,
            access_level: r.access_level,
            name: r.restrictable?.name_de || r.restrictable_id,
          }))
        }
        if (restrictions['tab-permissions']) {
          const perms = {}
          for (const tp of restrictions['tab-permissions']) {
            perms[tp.tab_key] = tp.access_level
          }
          tabPermissions.value = perms
        }
      } catch {
        // Restrictions might not load
      }
    }
  } catch {
    // Permissions might not load
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="p-4 flex flex-col h-full">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">
      {{ isEdit ? 'Rolle bearbeiten' : 'Neue Rolle' }}
    </h3>

    <!-- Role name -->
    <div class="mb-4">
      <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Name</label>
      <input
        v-model="roleName"
        type="text"
        class="pim-input w-full text-sm"
        placeholder="Rollenname"
      />
      <p v-if="errors.name" class="text-xs text-[var(--color-error)] mt-1">{{ errors.name }}</p>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <span class="text-xs text-[var(--color-text-tertiary)]">Berechtigungen werden geladen...</span>
    </div>

    <!-- Permission matrix -->
    <div v-else class="flex-1 overflow-y-auto -mx-4 px-4">
      <!-- Header with select all -->
      <div class="flex items-center justify-between mb-3">
        <span class="text-xs font-medium text-[var(--color-text-secondary)]">
          Berechtigungen ({{ selectedPermissions.size }})
        </span>
        <button
          class="text-[11px] text-[var(--color-accent)] hover:underline cursor-pointer"
          @click="isAllSelected ? deselectAll() : selectAll()"
        >
          {{ isAllSelected ? 'Keine auswählen' : 'Alle auswählen' }}
        </button>
      </div>

      <!-- Groups -->
      <div v-for="group in groupedPermissions" :key="group.label" class="mb-4">
        <div class="text-[10px] uppercase tracking-wider font-semibold text-[var(--color-text-tertiary)] mb-1.5">
          {{ group.label }}
        </div>

        <!-- Entity rows -->
        <div v-for="entity in group.entities" :key="entity.key" class="mb-1">
          <!-- Entity header row -->
          <div class="flex items-center gap-1 py-1">
            <!-- Row select-all checkbox -->
            <button
              class="w-4 h-4 rounded border flex items-center justify-center shrink-0 cursor-pointer transition-colors"
              :class="isRowAllSelected(entity.key, entity.actions)
                ? 'bg-[var(--color-accent)] border-[var(--color-accent)]'
                : isRowPartialSelected(entity.key, entity.actions)
                  ? 'border-[var(--color-accent)] bg-[var(--color-accent)]/20'
                  : 'border-[var(--color-border-strong)]'"
              @click="toggleRow(entity.key, entity.actions)"
            >
              <Check v-if="isRowAllSelected(entity.key, entity.actions)" class="w-3 h-3 text-white" :stroke-width="3" />
              <Minus v-else-if="isRowPartialSelected(entity.key, entity.actions)" class="w-3 h-3 text-[var(--color-accent)]" :stroke-width="3" />
            </button>
            <span class="text-xs font-medium text-[var(--color-text-primary)] ml-1">{{ entity.label }}</span>
          </div>

          <!-- Action checkboxes -->
          <div class="flex flex-wrap gap-1 ml-5 mb-1">
            <button
              v-for="action in entity.actions"
              :key="action"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] cursor-pointer transition-colors border"
              :class="hasPermission(entity.key, action)
                ? 'bg-[var(--color-accent)]/10 border-[var(--color-accent)]/30 text-[var(--color-accent)]'
                : 'bg-[var(--color-bg)] border-[var(--color-border)] text-[var(--color-text-tertiary)] hover:border-[var(--color-border-strong)]'"
              @click="togglePermission(entity.key, action)"
            >
              <Check v-if="hasPermission(entity.key, action)" class="w-3 h-3" :stroke-width="2.5" />
              <span>{{ actionLabels[action] || action }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Instance restrictions section -->
      <div class="mt-4 mb-2">
        <div class="text-[10px] uppercase tracking-wider font-semibold text-[var(--color-text-tertiary)] mb-2">
          Instanz-Einschränkungen
        </div>
        <p class="text-[10px] text-[var(--color-text-tertiary)] mb-3">
          Zugriff auf bestimmte Einträge einschränken. Ohne Einschränkung hat die Rolle Vollzugriff.
        </p>

        <div class="space-y-2">
          <RoleRestrictionSection
            label="Attributgruppen"
            :items="attributeTypeItems"
            :selected-restrictions="attributeTypeRestrictions"
            :access-levels="['read', 'write']"
            @update:restrictions="attributeTypeRestrictions = $event"
          />

          <RoleRestrictionSection
            label="Medientypen"
            :items="mediaUsageTypeItems"
            :selected-restrictions="mediaUsageTypeRestrictions"
            :access-levels="['read', 'write', 'delete']"
            @update:restrictions="mediaUsageTypeRestrictions = $event"
          />

          <HierarchyNodeRestrictionPicker
            :selected-restrictions="hierarchyNodeRestrictions"
            @update:restrictions="hierarchyNodeRestrictions = $event"
          />
        </div>
      </div>

      <!-- Tab permissions section -->
      <div class="mt-4 mb-2">
        <div class="text-[10px] uppercase tracking-wider font-semibold text-[var(--color-text-tertiary)] mb-2">
          Produkt-Editor Tabs
        </div>
        <p class="text-[10px] text-[var(--color-text-tertiary)] mb-3">
          Sichtbarkeit und Zugriff auf Tabs im Produkteditor steuern. Standard: Schreiben.
        </p>

        <div class="border border-[var(--color-border)] rounded-lg overflow-hidden">
          <div
            v-for="tab in productEditorTabs"
            :key="tab.key"
            class="flex items-center gap-2 px-3 py-1.5 border-b border-[var(--color-border)] last:border-b-0"
          >
            <span class="text-xs text-[var(--color-text-primary)] flex-1">{{ tab.label }}</span>
            <div class="flex gap-0 border border-[var(--color-border)] rounded-lg overflow-hidden">
              <button
                v-for="level in tabAccessLevels"
                :key="level.value"
                class="px-2 py-0.5 text-[10px] cursor-pointer transition-colors"
                :class="getTabAccess(tab.key) === level.value
                  ? level.value === 'hidden'
                    ? 'bg-[var(--color-error)]/15 text-[var(--color-error)] font-medium'
                    : level.value === 'read'
                      ? 'bg-[var(--color-warning)]/15 text-[var(--color-warning)] font-medium'
                      : 'bg-[var(--color-accent)]/15 text-[var(--color-accent)] font-medium'
                  : 'text-[var(--color-text-tertiary)] hover:bg-[var(--color-bg-hover)]'"
                @click="setTabAccess(tab.key, level.value)"
              >
                {{ level.label }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="flex items-center justify-end gap-2 pt-3 mt-3 border-t border-[var(--color-border)] -mx-4 px-4">
      <button class="pim-btn pim-btn-secondary text-xs" @click="authStore.closePanel()">Abbrechen</button>
      <button
        class="pim-btn pim-btn-primary text-xs"
        :disabled="saving || !roleName.trim()"
        @click="handleSubmit"
      >
        {{ saving ? 'Wird gespeichert...' : 'Speichern' }}
      </button>
    </div>
  </div>
</template>
