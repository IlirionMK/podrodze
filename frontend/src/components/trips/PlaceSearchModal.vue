<script setup>
import { ref, watch, computed } from "vue"
import { useI18n } from "vue-i18n"

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

watch(
    () => props.modelValue,
    async (v) => {
      if (!v) return
      reset()
      await loadSuggested()
    }
)

function makeMockSuggested() {
  return [
    {
      place_id: "mock_1",
      name: "Muzeum Narodowe",
      lat: 51.1098,
      lon: 17.0355,
      rating: 4.6,
      category_slug: "museum",
      meta: { address: "Wrocław", user_ratings_total: 1200, types: ["museum"] },
    },
    {
      place_id: "mock_2",
      name: "Panorama Sky Bar",
      lat: 51.1102,
      lon: 17.0235,
      rating: 4.7,
      category_slug: "bar",
      meta: { address: "Wrocław", user_ratings_total: 600, types: ["bar"] },
    },
    {
      place_id: "mock_3",
      name: "Park Szczytnicki",
      lat: 51.1148,
      lon: 17.0832,
      rating: 4.8,
      category_slug: "park",
      meta: { address: "Wrocław", user_ratings_total: 9000, types: ["park"] },
    },
  ]
}

function makeMockSearch(query) {
  const base = makeMockSuggested()
  const qq = query.trim().toLowerCase()
  return base
      .map((x, i) => ({ ...x, place_id: `mock_search_${i}_${x.place_id}` }))
      .filter((x) => x.name.toLowerCase().includes(qq))
}

async function loadSuggested() {
  loading.value = true
  error.value = ""
  selected.value = null

  try {
    if (MOCK) {
      items.value = makeMockSuggested()
      return
    }

    items.value = makeMockSuggested()
  } catch (e) {
    error.value = e?.message || t("errors.default")
  } finally {
    loading.value = false
  }
}

async function runSearch() {
  const query = q.value.trim()
  if (query.length < 2) return

  loading.value = true
  error.value = ""
  selected.value = null

  try {
    if (MOCK) {
      items.value = makeMockSearch(query)
      return
    }

    items.value = makeMockSearch(query)
  } catch (e) {
    error.value = e?.message || t("errors.default")
  } finally {
    loading.value = false
  }
}

function pick(item) {
  selected.value = item
}

function addSelected() {
  if (!selected.value) return

  emit("picked", {
    place_id: selected.value.place_id,
    name: selected.value.name,
    lat: selected.value.lat,
    lon: selected.value.lon,
    category_slug: selected.value.category_slug,
    meta: selected.value.meta || {},
  })

  close()
}
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/50" @click="close"></div>

    <div class="relative max-w-2xl mx-auto mt-10 bg-white rounded-2xl shadow-xl border overflow-hidden">
      <div class="p-5 border-b flex items-center justify-between">
        <h2 class="text-lg font-semibold">{{ t("trip.add_place.title_search") }}</h2>
        <button type="button" class="px-3 py-1 rounded-xl hover:bg-gray-100" @click="close">✕</button>
      </div>

      <div class="p-5 space-y-4">
        <div class="flex gap-2">
          <button
              type="button"
              class="px-4 py-2 rounded-xl font-medium"
              :class="tab === 'suggested' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
              @click="tab='suggested'; loadSuggested()"
          >
            {{ t("trip.add_place.tabs.suggested") }}
          </button>

          <button
              type="button"
              class="px-4 py-2 rounded-xl font-medium"
              :class="tab === 'search' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
              @click="tab='search'; items=[]; selected=null"
          >
            {{ t("trip.add_place.tabs.search") }}
          </button>

          <div v-if="MOCK" class="ml-auto text-xs text-gray-500 self-center">MOCK</div>
        </div>

        <div v-if="tab === 'search'" class="flex gap-2">
          <input
              v-model="q"
              type="text"
              class="flex-1 px-4 py-2.5 rounded-xl border bg-white focus:outline-none focus:ring-2 focus:ring-black/10"
              :placeholder="t('trip.add_place.search_placeholder')"
              @keydown.enter.prevent="runSearch"
          />
          <button
              type="button"
              class="px-4 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
              :disabled="loading || q.trim().length < 2"
              @click="runSearch"
          >
            {{ t("actions.search") }}
          </button>
        </div>

        <div v-if="error" class="p-3 rounded-xl bg-red-100 text-red-700 border">{{ error }}</div>
        <div v-if="loading" class="text-sm text-gray-500">{{ t("loading") }}…</div>

        <div v-else class="space-y-2">
          <button
              v-for="it in items"
              :key="it.place_id"
              type="button"
              class="w-full text-left p-4 border rounded-xl hover:bg-gray-50 transition"
              :class="selected?.place_id === it.place_id ? 'bg-gray-900 text-white border-gray-900' : ''"
              @click="pick(it)"
          >
            <div class="font-semibold">{{ it.name }}</div>
            <div class="text-sm" :class="selected?.place_id === it.place_id ? 'text-white/80' : 'text-gray-500'">
              {{ it.meta?.address || "—" }} · {{ it.category_slug || "—" }}
              <span v-if="it.rating"> · ★ {{ it.rating }}</span>
            </div>
          </button>

          <div v-if="items.length === 0" class="text-sm text-gray-500">
            {{ tab === 'search' ? t("trip.add_place.no_results") : t("trip.add_place.no_suggestions") }}
          </div>
        </div>

        <div class="pt-2 flex justify-end gap-2">
          <button type="button" class="px-4 py-2 rounded-xl border hover:bg-gray-50" @click="close">
            {{ t("actions.cancel") }}
          </button>
          <button
              type="button"
              class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
              :disabled="!canAdd"
              @click="addSelected"
          >
            {{ t("actions.add") }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
