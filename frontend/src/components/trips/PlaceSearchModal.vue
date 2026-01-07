<script setup>
import { ref, watch, computed } from "vue"
import { useI18n } from "vue-i18n"
import { searchExternalPlaces } from "@/composables/api/tripPlaces.js"

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  tripId: { type: [String, Number], required: true },
})

const emit = defineEmits(["update:modelValue", "picked"])
const { t } = useI18n()

const MOCK = import.meta.env.VITE_PLACES_SEARCH_MOCK === "true"

const tab = ref("suggested")
const q = ref("")
const loading = ref(false)
const error = ref("")
const items = ref([])
const selected = ref(null)

let searchTimeout = null

const canAdd = computed(() => !!selected.value)

function close() {
  emit("update:modelValue", false)
}

function reset() {
  tab.value = "suggested"
  q.value = ""
  loading.value = false
  error.value = ""
  items.value = []
  selected.value = null
}

watch(() => props.modelValue, async (v) => {
  if (!v) return
  reset()
  if (MOCK) items.value = makeMockSuggested()
})

watch(q, (newVal) => {
  if (tab.value !== 'search') return

  if (searchTimeout) clearTimeout(searchTimeout)

  if (!newVal || newVal.trim().length < 2) {
    items.value = []
    return
  }

  searchTimeout = setTimeout(() => {
    runSearch()
  }, 500)
})

async function runSearch() {
  const query = q.value.trim()
  if (query.length < 2) return

  loading.value = true
  error.value = ""
  selected.value = null

  try {
    if (MOCK) {
      await new Promise(r => setTimeout(r, 600))
      items.value = makeMockSearch(query)
      return
    }

    const res = await searchExternalPlaces(query)
    // Laravel Resource usually wraps collection in "data"
    const rawItems = res.data.data || []

    items.value = rawItems.map(item => {
      return {
        // We store the Google ID in place_id for local selection logic
        place_id: item.google_place_id,
        name: item.main_text || item.description,
        address: item.secondary_text || "",
        lat: null,
        lon: null,
        category_slug: 'other',
        meta: {
          address: item.secondary_text,
          original_description: item.description,
          types: item.types
        }
      }
    })

  } catch (e) {
    console.error(e)
    if (e.response && e.response.status === 404) {
      items.value = []
    } else {
      error.value = e?.response?.data?.message || t("errors.default", "Search error")
    }
  } finally {
    loading.value = false
  }
}

function pick(item) {
  selected.value = item
}

async function addSelected() {
  if (!selected.value) return

  loading.value = true
  error.value = ""

  try {

    emit("picked", {
      google_place_id: selected.value.place_id,
      place_id: null, // explicit null to pass integer validation
    })

    close()
  } catch (e) {
    console.error(e)
    error.value = "Error adding place"
  } finally {
    loading.value = false
  }
}

function makeMockSuggested() {
  return [
    { place_id: "m1", name: "Eiffel Tower", lat: 48.8584, lon: 2.2945, category_slug: "monument", meta: { address: "Paris" } },
    { place_id: "m2", name: "Louvre Museum", lat: 48.8606, lon: 2.3376, category_slug: "museum", meta: { address: "Paris" } },
  ]
}

function makeMockSearch(query) {
  const qq = query.toLowerCase()
  return makeMockSuggested().filter(x => x.name.toLowerCase().includes(qq))
}
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="close"></div>

    <div class="relative max-w-2xl mx-auto mt-10 bg-white rounded-2xl shadow-xl border overflow-hidden flex flex-col max-h-[85vh]">

      <div class="p-4 border-b flex items-center justify-between bg-white z-10">
        <h2 class="text-lg font-semibold">{{ t("trip.add_place.title_search", "Find a place") }}</h2>
        <button type="button" class="p-2 rounded-full hover:bg-gray-100 transition" @click="close">✕</button>
      </div>

      <div class="p-4 space-y-4 bg-gray-50/50">
        <div class="flex gap-2">
          <button
              type="button"
              class="px-4 py-2 rounded-xl font-medium text-sm transition"
              :class="tab === 'suggested' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
              @click="tab='suggested'; q=''"
          >
            {{ t("trip.add_place.tabs.suggested", "Suggested") }}
          </button>
          <button
              type="button"
              class="px-4 py-2 rounded-xl font-medium text-sm transition"
              :class="tab === 'search' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
              @click="tab='search'; items=[]; selected=null"
          >
            {{ t("trip.add_place.tabs.search", "Search") }}
          </button>
        </div>

        <div v-if="tab === 'search'" class="relative">
          <input
              v-model="q"
              type="text"
              class="w-full pl-4 pr-12 py-3 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition shadow-sm"
              :placeholder="t('trip.add_place.search_placeholder', 'Start typing...')"
              @keydown.enter.prevent="runSearch"
          />
          <div v-if="loading" class="absolute right-4 top-3.5">
            <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
          </div>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto p-4 min-h-[300px]">

        <div v-if="error" class="p-4 rounded-xl bg-red-50 text-red-600 border border-red-100 text-center text-sm">
          {{ error }}
        </div>

        <div v-else-if="!loading && items.length === 0 && tab === 'search' && q.length > 1" class="text-center py-10 text-gray-500">
          {{ t("trip.add_place.no_results", "No results found") }}
        </div>

        <div v-else class="space-y-2">
          <button
              v-for="it in items"
              :key="it.place_id"
              type="button"
              class="w-full text-left p-3 rounded-xl border transition flex items-start gap-3 group"
              :class="selected?.place_id === it.place_id
                ? 'bg-blue-50 border-blue-200 ring-1 ring-blue-200'
                : 'bg-white border-gray-100 hover:border-gray-300 hover:shadow-sm'"
              @click="pick(it)"
          >
            <div class="mt-1 p-2 rounded-lg bg-gray-100 text-gray-500 group-hover:bg-white group-hover:text-blue-600 transition">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>

            <div>
              <div class="font-medium text-gray-900">{{ it.name }}</div>
              <div class="text-sm text-gray-500 flex items-center gap-2 mt-0.5">
                <span>{{ it.meta?.address || it.address || "—" }}</span>
                <span v-if="it.category_slug" class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold bg-gray-100 text-gray-600">
                  {{ it.category_slug }}
                </span>
              </div>
            </div>
          </button>
        </div>
      </div>

      <div class="p-4 border-t bg-white flex justify-end gap-3 z-10">
        <button type="button" class="px-5 py-2.5 rounded-xl border border-gray-200 font-medium hover:bg-gray-50 transition" @click="close">
          {{ t("actions.cancel", "Cancel") }}
        </button>
        <button
            type="button"
            class="px-5 py-2.5 rounded-xl bg-blue-600 text-white font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-blue-500/30 transition"
            :disabled="!canAdd"
            @click="addSelected"
        >
          {{ t("actions.add", "Add") }}
        </button>
      </div>
    </div>
  </div>
</template>