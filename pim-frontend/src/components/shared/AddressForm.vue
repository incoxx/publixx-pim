<script setup>
// Generische Adress-Eingabe (Firma/Ansprechpartner/Strasse/PLZ+Ort/Land/E-Mail/Telefon).
// Erste Verwendung: Collection-Adresse fuer ein Angebot. Bewusst neu statt eine bestehende
// Komponente wiederverwendet -- im E-Commerce-Checkout (CatalogCartDrawer.vue) ist das
// Adressformular hartcodiertes Markup ohne Land-Feld, keine wiederverwendbare Komponente.
const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

const fields = [
  { key: 'company', label: 'Firma' },
  { key: 'contact_name', label: 'Ansprechpartner' },
  { key: 'street', label: 'Straße' },
]

function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}
</script>

<template>
  <div class="space-y-3">
    <div v-for="f in fields" :key="f.key">
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">{{ f.label }}</label>
      <input
        type="text"
        class="pim-input text-xs w-full"
        :value="modelValue?.[f.key] || ''"
        @input="update(f.key, $event.target.value)"
      />
    </div>

    <div class="grid grid-cols-3 gap-2">
      <div>
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">PLZ</label>
        <input
          type="text"
          class="pim-input text-xs w-full"
          :value="modelValue?.postal_code || ''"
          @input="update('postal_code', $event.target.value)"
        />
      </div>
      <div class="col-span-2">
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Ort</label>
        <input
          type="text"
          class="pim-input text-xs w-full"
          :value="modelValue?.city || ''"
          @input="update('city', $event.target.value)"
        />
      </div>
    </div>

    <div>
      <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Land</label>
      <input
        type="text"
        class="pim-input text-xs w-full"
        :value="modelValue?.country || ''"
        @input="update('country', $event.target.value)"
      />
    </div>

    <div class="grid grid-cols-2 gap-2">
      <div>
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">E-Mail</label>
        <input
          type="email"
          class="pim-input text-xs w-full"
          :value="modelValue?.email || ''"
          @input="update('email', $event.target.value)"
        />
      </div>
      <div>
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Telefon</label>
        <input
          type="text"
          class="pim-input text-xs w-full"
          :value="modelValue?.phone || ''"
          @input="update('phone', $event.target.value)"
        />
      </div>
    </div>
  </div>
</template>
