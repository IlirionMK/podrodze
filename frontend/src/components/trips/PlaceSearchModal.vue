<script setup>
import { ref, watch, computed } from "vue"
import { useI18n } from "vue-i18n"
import { Search, MapPin, Plus, X } from "lucide-vue-next"
import { searchExternalPlaces } from "@/composables/api/tripPlaces.js"

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  tripId: { type: [String, Number], required: true },
})

const emit = defineEmits(["update:modelValue", "picked"])
const { t } = useI18n()

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
  q.value = ""
  loading.value = false
  error.value = ""
  items.value = []
  selected.value = null
}

watch(
    () => props.modelValue,
    (v) => {
      if (!v) return
      reset()
    }
)

watch(q, (newVal) => {
  if (searchTimeout) clearTimeout(searchTimeout)

  const val = (newVal || "").trim()
  if (val.length < 2) {
    items.value = []
    selected.value = null
    error.value = ""
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
    const res = await searchExternalPlaces(query)
    const rawItems = res.data.data || []

    items.value = rawItems.map((item) => ({
      unique_key: `search_${item.google_place_id}`,
      place_id: null,
      google_place_id: item.google_place_id,
      name: item.main_text || item.description,
      address: item.secondary_text || "",
      category_slug: "other",
      rating: null,
      distance_m: null,
      reviews_count: null,
      source: "search",
    }))
  } catch (e) {
    if (e?.response?.status === 404) {
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
    const item = selected.value
    const payload = { _source: item.source }

    if (item.place_id) payload.place_id = item.place_id
    else if (item.google_place_id) payload.google_place_id = item.google_place_id

    if (!payload.place_id && !payload.google_place_id) payload.name = item.name

    emit("picked", payload)
    close()
  } catch (e) {
    error.value = t("errors.default", "Error adding place")
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition
        appear
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
    >
      <div
          v-if="modelValue"
          class="fixed inset-0 z-50 flex items-center justify-center px-4"
          role="dialog"
          aria-modal="true"
      >
        <button class="absolute inset-0 bg-black/60" @click="close" aria-label="Close" />

        <div
            class="relative w-full max-w-2xl rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white overflow-hidden flex flex-col max-h-[85vh]"
        >
          <div class="p-6 border-b border-white/10 bg-white/5">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <h2 class="text-xl font-semibold drop-shadow">
                  {{ t("trip.add_place.title_search", "Find a place") }}
                </h2>
                <div class="mt-1 text-sm text-white/70">
                  {{ t("trip.add_place.search_placeholder", "Type name of place...") }}
                </div>
              </div>

              <button
                  type="button"
                  class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 hover:bg-white/15 transition flex items-center justify-center disabled:opacity-50"
                  @click="close"
                  :disabled="loading"
                  aria-label="Close"
              >
                <X class="h-4 w-4" />
              </button>
            </div>

            <div class="mt-5">
              <div class="relative">
                <input
                    v-model="q"
                    type="text"
                    class="w-full h-11 pl-11 pr-4 rounded-xl border border-white/15 bg-white/10 text-white placeholder:text-white/40 outline-none focus:ring-2 focus:ring-white/20"
                    :placeholder="t('trip.add_place.search_placeholder', 'Type name of place...')"
                    :disabled="loading"
                    @keydown.enter.prevent="runSearch"
                />
                <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-white/50" />
              </div>
            </div>
          </div>

          <div class="flex-1 overflow-y-auto p-6 bg-black/10">
            <div v-if="loading" class="flex flex-col items-center justify-center py-14 gap-3">
              <svg class="animate-spin h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                ></path>
              </svg>
              <div class="text-sm text-white/60 animate-pulse">
                {{ t("loading", "Loading") }}
              </div>
            </div>

            <div v-else-if="error" class="p-4 rounded-xl bg-red-500/10 text-red-200 border border-red-400/20 text-sm">
              {{ error }}
            </div>

            <div v-else-if="items.length === 0" class="py-12 text-center text-white/60">
              <div v-if="q.trim().length < 2">
                {{ t("trip.add_place.search_hint", "Type at least 2 characters to search.") }}
              </div>
              <div v-else>
                {{ t("trip.add_place.no_results", "No results found") }}
              </div>
            </div>

            <div v-else class="space-y-3">
              <button
                  v-for="it in items"
                  :key="it.unique_key"
                  type="button"
                  class="w-full text-left p-4 rounded-2xl border transition relative overflow-hidden"
                  :class="selected?.unique_key === it.unique_key
                  ? 'border-white/20 bg-white/10 ring-2 ring-white/10'
                  : 'border-white/10 bg-white/5 hover:bg-white/10 hover:border-white/15'"
                  @click="pick(it)"
              >
                <div
                    v-if="selected?.unique_key === it.unique_key"
                    class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-blue-500 to-purple-600"
                />

                <div class="flex items-start gap-3">
                  <div
                      class="mt-0.5 flex-shrink-0 w-10 h-10 rounded-xl border border-white/10 bg-black/20 text-white/70 flex items-center justify-center"
                  >
                    <MapPin class="h-5 w-5" />
                  </div>

                  <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-3">
                      <div class="min-w-0">
                        <div class="font-semibold text-white truncate">{{ it.name }}</div>
                        <div class="text-sm text-white/60 mt-0.5 truncate">
                          {{ it.address || "—" }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </button>
            </div>
          </div>

          <div class="p-6 border-t border-white/10 bg-white/5 flex justify-end gap-2">
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-white/10 border border-white/15 hover:bg-white/15 transition disabled:opacity-50"
                @click="close"
                :disabled="loading"
            >
              {{ t("actions.cancel", "Cancel") }}
            </button>

            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-blue-500 to-purple-600 hover:opacity-90 active:opacity-80 transition shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="!canAdd || loading"
                @click="addSelected"
            >
              <Plus class="h-4 w-4" />
              {{ t("actions.add", "Add") }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
