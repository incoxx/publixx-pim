<script setup>
import { useI18n } from 'vue-i18n'
import { Plus, FolderPlus, X } from 'lucide-vue-next'
import QueryBuilderRule from './QueryBuilderRule.vue'

const { t } = useI18n()

const props = defineProps({
  group: { type: Object, required: true },
  attributes: { type: Array, default: () => [] },
  depth: { type: Number, default: 0 },
  removable: { type: Boolean, default: false },
})

const emit = defineEmits(['update', 'remove'])

const MAX_DEPTH = 3

function updateOperator(operator) {
  emit('update', { ...props.group, operator })
}

function addRule() {
  const rules = [...(props.group.rules || [])]
  rules.push({ type: 'rule', attribute_id: '', operator: 'eq', value: '' })
  emit('update', { ...props.group, rules })
}

function addGroup() {
  if (props.depth >= MAX_DEPTH) return
  const rules = [...(props.group.rules || [])]
  rules.push({ type: 'group', operator: 'OR', rules: [] })
  emit('update', { ...props.group, rules })
}

function updateRule(index, updated) {
  const rules = [...(props.group.rules || [])]
  rules[index] = updated
  emit('update', { ...props.group, rules })
}

function removeRule(index) {
  const rules = [...(props.group.rules || [])]
  rules.splice(index, 1)
  emit('update', { ...props.group, rules })
}

const operatorColors = {
  AND: 'qb-group-and',
  OR: 'qb-group-or',
  NOT: 'qb-group-not',
}

const operatorBtnColors = {
  AND: { active: 'qb-btn-and-active', inactive: 'qb-btn-and' },
  OR: { active: 'bg-amber-500 text-white', inactive: 'text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20' },
  NOT: { active: 'bg-red-500 text-white', inactive: 'text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20' },
}
</script>

<template>
  <div
    class="rounded-lg border p-3 space-y-2"
    :class="operatorColors[group.operator] || operatorColors.AND"
  >
    <!-- Header -->
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-1">
        <button
          v-for="op in ['AND', 'OR', 'NOT']"
          :key="op"
          class="px-2 py-0.5 rounded text-[11px] font-semibold transition-colors"
          :class="group.operator === op ? operatorBtnColors[op].active : operatorBtnColors[op].inactive"
          @click="updateOperator(op)"
        >
          {{ op === 'AND' ? t('UND') : op === 'OR' ? t('ODER') : t('NICHT') }}
        </button>
      </div>

      <div class="flex items-center gap-1">
        <button class="pim-btn pim-btn-secondary text-[11px] py-0.5 px-2" @click="addRule">
          <Plus class="w-3 h-3" />
          Regel
        </button>
        <button v-if="depth < MAX_DEPTH" class="pim-btn pim-btn-secondary text-[11px] py-0.5 px-2" @click="addGroup">
          <FolderPlus class="w-3 h-3" />
          Gruppe
        </button>
        <button v-if="removable" class="p-1 rounded hover:bg-[var(--color-border)] text-[var(--color-text-tertiary)]" @click="$emit('remove')">
          <X class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <!-- Rules & nested groups -->
    <div v-if="group.rules && group.rules.length > 0" class="space-y-2">
      <template v-for="(item, index) in group.rules" :key="index">
        <!-- Connector label between rules -->
        <div v-if="index > 0" class="flex items-center gap-2 px-2">
          <div class="flex-1 h-px bg-[var(--color-border)]" />
          <span class="text-[10px] font-semibold uppercase" :class="{
            'qb-connector-and': group.operator === 'AND',
            'text-amber-400': group.operator === 'OR',
            'text-red-400': group.operator === 'NOT',
          }">
            {{ group.operator === 'AND' ? t('UND') : group.operator === 'OR' ? t('ODER') : t('NICHT') }}
          </span>
          <div class="flex-1 h-px bg-[var(--color-border)]" />
        </div>

        <!-- Nested group -->
        <QueryBuilderGroup
          v-if="item.type === 'group'"
          :group="item"
          :attributes="attributes"
          :depth="depth + 1"
          :removable="true"
          @update="updateRule(index, $event)"
          @remove="removeRule(index)"
        />

        <!-- Rule -->
        <QueryBuilderRule
          v-else
          :rule="item"
          :attributes="attributes"
          @update="updateRule(index, $event)"
          @remove="removeRule(index)"
        />
      </template>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-3 text-xs text-[var(--color-text-tertiary)]">
      <button class="text-[var(--color-accent)] hover:underline" @click="addRule">
        + Erste Regel hinzufügen
      </button>
    </div>
  </div>
</template>
