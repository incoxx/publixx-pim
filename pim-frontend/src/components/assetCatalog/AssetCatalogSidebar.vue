<script setup>
import { useI18n } from 'vue-i18n'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import { FolderOpen, Folder, ChevronRight, ChevronDown } from 'lucide-vue-next'
import { ref } from 'vue'

const { t } = useI18n()
const store = useAssetCatalogStore()
const expanded = ref(new Set())

function toggleExpand(nodeId) {
  if (expanded.value.has(nodeId)) {
    expanded.value.delete(nodeId)
  } else {
    expanded.value.add(nodeId)
  }
}

function selectFolder(node) {
  if (store.selectedFolderId === node.id) {
    store.clearFolder()
  } else {
    store.setFolder(node.id, node.name)
    // Auto-expand
    expanded.value.add(node.id)
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
      <template v-if="store.folders.length > 0">
        <div v-for="node in store.folders" :key="node.id">
          <FolderNode
            :node="node"
            :depth="0"
            :expanded="expanded"
            :selected-id="store.selectedFolderId"
            @toggle-expand="toggleExpand"
            @select="selectFolder"
          />
        </div>
      </template>

      <div v-else-if="!store.foldersLoading" class="px-3 py-4 text-xs text-base-content/30 text-center">
        Keine Ordner vorhanden
      </div>
    </div>
  </div>
</template>

<script>
// Recursive folder node component
const FolderNode = {
  name: 'FolderNode',
  props: {
    node: Object,
    depth: Number,
    expanded: Object,
    selectedId: String,
  },
  emits: ['toggle-expand', 'select'],
  setup(props, { emit }) {
    const hasChildren = props.node.children && props.node.children.length > 0
    const isExpanded = () => props.expanded.has(props.node.id)
    const isSelected = () => props.selectedId === props.node.id

    return { hasChildren, isExpanded, isSelected, emit }
  },
  template: `
    <div>
      <button
        class="w-full flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm transition-colors"
        :class="isSelected() ? 'bg-primary/10 text-primary font-medium' : 'hover:bg-base-200'"
        :style="{ paddingLeft: (depth * 16 + 12) + 'px' }"
        @click="emit('select', node)"
      >
        <button
          v-if="hasChildren"
          class="flex-none w-4 h-4 flex items-center justify-center"
          @click.stop="emit('toggle-expand', node.id)"
        >
          <component :is="isExpanded() ? 'ChevronDown' : 'ChevronRight'" class="w-3 h-3" />
        </button>
        <span v-else class="w-4"></span>
        <component :is="isExpanded() ? 'FolderOpen' : 'Folder'" class="w-4 h-4 flex-none text-base-content/50" />
        <span class="truncate">{{ node.name }}</span>
        <span v-if="node.asset_count" class="ml-auto text-xs text-base-content/40 flex-none">{{ node.asset_count }}</span>
      </button>
      <div v-if="hasChildren && isExpanded()">
        <FolderNode
          v-for="child in node.children"
          :key="child.id"
          :node="child"
          :depth="depth + 1"
          :expanded="expanded"
          :selected-id="selectedId"
          @toggle-expand="(id) => emit('toggle-expand', id)"
          @select="(n) => emit('select', n)"
        />
      </div>
    </div>
  `,
  components: { FolderOpen, Folder, ChevronRight, ChevronDown },
}

export default {
  components: { FolderNode },
}
</script>
