<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import { FolderOpen } from 'lucide-vue-next'
import PimTree from '@/components/shared/PimTree.vue'

const { t } = useI18n()
const store = useAssetCatalogStore()
const expandedIds = ref(new Set())

// PimTree erwartet ein "product_count"-Badge-Feld — der Asset-Katalog liefert
// stattdessen asset_count. Rekursiv mappen, ohne die geteilte Baum-Komponente
// mit einem asset-katalog-spezifischen Feldnamen zu belasten.
function mapNodes(nodes) {
  return nodes.map(node => ({
    ...node,
    product_count: node.asset_count,
    children: node.children?.length ? mapNodes(node.children) : [],
  }))
}

const treeNodes = computed(() => mapNodes(store.folders))

function toggleExpand(nodeId) {
  const s = new Set(expandedIds.value)
  if (s.has(nodeId)) s.delete(nodeId)
  else s.add(nodeId)
  expandedIds.value = s
}

function selectFolder(node) {
  if (store.selectedFolderId === node.id) {
    store.clearFolder()
  } else {
    store.setFolder(node.id, node.name)
    expandedIds.value = new Set(expandedIds.value).add(node.id)
  }
  store.fetchAssets()
}

function selectAll() {
  store.clearFolder()
  store.fetchAssets()
}
</script>

<template>
  <div class="flex flex-col h-full bg-base-100">
    <div class="p-4 border-b border-base-300">
      <h3 class="text-sm font-semibold text-base-content/70">{{ t('assetCatalog.folders') }}</h3>
    </div>

    <div class="flex-1 overflow-y-auto p-2">
      <!-- All folders -->
      <button
        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-colors"
        :class="!store.selectedFolderId ? 'bg-primary/10 text-primary font-medium' : 'hover:bg-base-200'"
        @click="selectAll"
      >
        <FolderOpen class="w-4 h-4 flex-none" />
        <span>{{ t('assetCatalog.allFolders') }}</span>
        <span class="ml-auto text-xs text-base-content/40">{{ store.meta.total }}</span>
      </button>

      <!-- Folder tree -->
      <PimTree
        v-if="treeNodes.length > 0"
        :nodes="treeNodes"
        :selectedId="store.selectedFolderId"
        :expandedIds="expandedIds"
        :draggable="false"
        :showActions="false"
        @select="selectFolder"
        @toggle="toggleExpand"
      />

      <div v-else-if="!store.foldersLoading" class="px-3 py-4 text-xs text-base-content/30 text-center">
        {{ t('assetCatalog.noFolders') }}
      </div>
    </div>
  </div>
</template>
