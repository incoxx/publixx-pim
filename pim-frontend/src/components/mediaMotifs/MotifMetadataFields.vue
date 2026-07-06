<script setup>
defineProps({
  modelValue: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div class="space-y-3">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Titel (DE)</label>
        <input
          class="pim-input"
          :value="modelValue.title_de"
          @input="$emit('update:modelValue', { ...modelValue, title_de: $event.target.value })"
        />
        <p v-if="errors.title_de" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ errors.title_de }}</p>
      </div>
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Titel (EN)</label>
        <input
          class="pim-input"
          :value="modelValue.title_en"
          @input="$emit('update:modelValue', { ...modelValue, title_en: $event.target.value })"
        />
      </div>
    </div>

    <div class="border-t border-[var(--color-border)] pt-3">
      <p class="text-[11px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wide mb-2">Rechte &amp; Herkunft</p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Rechteinhaber</label>
          <input
            class="pim-input"
            :value="modelValue.rights_holder"
            @input="$emit('update:modelValue', { ...modelValue, rights_holder: $event.target.value })"
          />
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Urheber / Fotograf</label>
          <input
            class="pim-input"
            :value="modelValue.creator"
            @input="$emit('update:modelValue', { ...modelValue, creator: $event.target.value })"
          />
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Bildnachweis</label>
          <input
            class="pim-input"
            :value="modelValue.credit_line"
            @input="$emit('update:modelValue', { ...modelValue, credit_line: $event.target.value })"
          />
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Lizenztyp</label>
          <input
            class="pim-input"
            placeholder="z.B. exklusiv, redaktionell"
            :value="modelValue.license_type"
            @input="$emit('update:modelValue', { ...modelValue, license_type: $event.target.value })"
          />
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Lizenz gültig bis</label>
          <input
            type="date"
            class="pim-input"
            :value="modelValue.license_valid_until"
            @input="$emit('update:modelValue', { ...modelValue, license_valid_until: $event.target.value })"
          />
        </div>
      </div>
      <div class="mt-3">
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Copyright-Vermerk</label>
        <input
          class="pim-input"
          placeholder="© 2026 Fotostudio Muster GmbH"
          :value="modelValue.copyright_notice"
          @input="$emit('update:modelValue', { ...modelValue, copyright_notice: $event.target.value })"
        />
      </div>
      <div class="mt-3">
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Nutzungsbeschränkungen</label>
        <textarea
          class="pim-input"
          rows="2"
          placeholder="z.B. nur Web, keine Social-Media-Nutzung"
          :value="modelValue.usage_restrictions"
          @input="$emit('update:modelValue', { ...modelValue, usage_restrictions: $event.target.value })"
        />
      </div>
    </div>

    <div class="border-t border-[var(--color-border)] pt-3">
      <p class="text-[11px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wide mb-2">Schlagworte &amp; Gültigkeitszeitraum (optional)</p>
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Schlagworte</label>
        <input
          class="pim-input"
          placeholder="kommagetrennt, z.B. akkubohrer, werkzeug, profi"
          :value="modelValue.keywordsInput"
          @input="$emit('update:modelValue', { ...modelValue, keywordsInput: $event.target.value })"
        />
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Gültig ab</label>
          <input
            type="date"
            class="pim-input"
            :value="modelValue.valid_from"
            @input="$emit('update:modelValue', { ...modelValue, valid_from: $event.target.value })"
          />
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Gültig bis</label>
          <input
            type="date"
            class="pim-input"
            :value="modelValue.valid_until"
            @input="$emit('update:modelValue', { ...modelValue, valid_until: $event.target.value })"
          />
          <p v-if="errors.valid_until" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ errors.valid_until }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
