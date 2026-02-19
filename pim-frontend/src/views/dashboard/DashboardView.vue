<script setup>
import { Package, GitBranch, Sliders, Upload } from 'lucide-vue-next'
</script>

<template>
  <div class="space-y-6">
    <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Dashboard</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="pim-card p-5 flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] flex items-center justify-center">
          <Package class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
        </div>
        <div><p class="text-2xl font-semibold">--</p><p class="text-xs text-[var(--color-text-tertiary)]">Produkte</p></div>
      </div>
      <div class="pim-card p-5 flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-[color-mix(in_srgb,var(--color-success)_10%,transparent)] flex items-center justify-center">
          <GitBranch class="w-5 h-5 text-[var(--color-success)]" :stroke-width="1.75" />
        </div>
        <div><p class="text-2xl font-semibold">--</p><p class="text-xs text-[var(--color-text-tertiary)]">Hierarchien</p></div>
      </div>
      <div class="pim-card p-5 flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-[color-mix(in_srgb,var(--color-warning)_10%,transparent)] flex items-center justify-center">
          <Sliders class="w-5 h-5 text-[var(--color-warning)]" :stroke-width="1.75" />
        </div>
        <div><p class="text-2xl font-semibold">--</p><p class="text-xs text-[var(--color-text-tertiary)]">Attribute</p></div>
      </div>
      <div class="pim-card p-5 flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-[color-mix(in_srgb,var(--color-error)_10%,transparent)] flex items-center justify-center">
          <Upload class="w-5 h-5 text-[var(--color-error)]" :stroke-width="1.75" />
        </div>
        <div><p class="text-2xl font-semibold">--</p><p class="text-xs text-[var(--color-text-tertiary)]">Imports</p></div>
      </div>
    </div>
  </div>
</template>
