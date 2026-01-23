<script setup>
import { ref, computed, watch } from "vue"
import { useI18n } from "vue-i18n"
import {
  RefreshCw,
  Sparkles,
  GripVertical,
  RotateCcw,
  Plus,
  RefreshCcw as RefreshIcon,
  MapPin
} from "lucide-vue-next"

import { fetchTripItinerary, fetchTripItineraryFull } from "@/composables/api/itinerary.js"
import { getAiSuggestions } from "@/composables/api/tripPlaces.js"

const props = defineProps({
  tripId: { type: [String, Number], required: true },
  trip: { type: Object, required: true },
  places: { type: Array, required: true },
  placesLoading: { type: Boolean, default: false },
})

const emit = defineEmits(["error", "picked"])

const { t, te, locale } = useI18n()

function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

function getErrMessage(err) {
  return err?.response?.data?.message || tr("errors.default", "Something went wrong.")
}

const generating = ref(false)
const itinerary = ref(null)

const days = ref(2)
const radius = ref(2000)
const generatingFull = ref(false)

const originalSchedule = ref([])

const canGenerate = computed(() => !props.placesLoading && props.places.length > 0)

function cloneSchedule(schedule) {
  return (schedule || []).map((d) => ({
    day: d.day,
    places: Array.isArray(d.places) ? d.places.map((p) => ({ ...p })) : [],
  }))
}

function setItineraryData(dto) {
  itinerary.value = dto
  originalSchedule.value = cloneSchedule(dto?.schedule || [])
}

async function generatePlan() {
  if (!canGenerate.value) return
  generating.value = true
  try {
    const res = await fetchTripItinerary(props.tripId)
    setItineraryData(res.data.data)
  } catch (err) {
    emit("error", getErrMessage(err))
  } finally {
    generating.value = false
  }
}

async function generateFull() {
  if (!canGenerate.value) return
  generatingFull.value = true
  try {
    const payload = {
      days: Number(days.value || 1),
      radius: Number(radius.value || 2000),
    }
    const res = await fetchTripItineraryFull(props.tripId, payload)
    setItineraryData(res.data.data)
  } catch (err) {
    emit("error", getErrMessage(err))
  } finally {
    generatingFull.value = false
  }
}

function resetOrder() {
  if (!itinerary.value) return
  itinerary.value = {
    ...itinerary.value,
    schedule: cloneSchedule(originalSchedule.value),
  }
}

function categoryLabel(slug) {
  const s = String(slug || "other")
  const key = `trip.categories.${s}`
  return te(key) ? t(key) : s
}

function formatDistance(m) {
  const v = Number(m ?? 0)
  if (!v) return "—"
  if (v < 1000) return `${Math.round(v)} m`
  return `${(v / 1000).toFixed(1)} km`
}

function formatScore(v) {
  if (v == null) return "—"
  const n = Number(v)
  if (Number.isNaN(n)) return "—"
  return n.toFixed(2)
}

const drag = ref({
  dayIndex: null,
  fromIndex: null,
  overIndex: null,
})

function onDragStart(dayIndex, fromIndex) {
  drag.value = { dayIndex, fromIndex, overIndex: fromIndex }
}

function onDragOver(dayIndex, overIndex, e) {
  if (drag.value.dayIndex !== dayIndex) return
  e.preventDefault()
  drag.value.overIndex = overIndex
}

function moveItem(arr, from, to) {
  const list = [...arr]
  const [item] = list.splice(from, 1)
  list.splice(to, 0, item)
  return list
}

function onDrop(dayIndex, toIndex) {
  const { dayIndex: d, fromIndex } = drag.value
  if (d == null || fromIndex == null) return
  if (d !== dayIndex) return

  const sch = itinerary.value?.schedule || []
  const day = sch[dayIndex]
  if (!day || !Array.isArray(day.places)) return

  const nextPlaces = moveItem(day.places, fromIndex, toIndex)
  const nextSchedule = sch.map((x, i) => (i === dayIndex ? { ...x, places: nextPlaces } : x))

  itinerary.value = { ...itinerary.value, schedule: nextSchedule }
  drag.value = { dayIndex: null, fromIndex: null, overIndex: null }
}

function onDragEnd() {
  drag.value = { dayIndex: null, fromIndex: null, overIndex: null }
}

const aiLoading = ref(false)
const aiError = ref("")
const aiItems = ref([])
const aiLimit = ref(6)

const canSuggest = computed(() => !props.placesLoading && props.places.length > 0)

function mapAiItem(item) {
  let gId = item.external_id || null
  if (gId && gId.startsWith("google:")) gId = gId.replace("google:", "")

  const uniqueKey = item.internal_place_id
      ? `db_${item.internal_place_id}`
      : `ext_${gId || Math.random()}`

  return {
    unique_key: uniqueKey,
    place_id: item.internal_place_id || null,
    google_place_id: gId || null,
    name: item.name,
    address: item.address || "",
    category_slug: item.category,
    ai_reason: item.reason,
    image: item.image_url,
    rating: item.rating,
    distance_m: item.distance_m,
    reviews_count: item.reviews_count,
    source: "suggestion",
  }
}

async function loadAiSuggestions() {
  if (!canSuggest.value) {
    aiItems.value = []
    return
  }

  aiLoading.value = true
  aiError.value = ""

  try {
    const res = await getAiSuggestions(props.tripId, {
      limit: Number(aiLimit.value || 6),
      locale: locale.value,
    })

    const rawItems = res.data.data || []
    aiItems.value = rawItems.map(mapAiItem)
  } catch (e) {
    aiError.value = tr("errors.suggestions_failed", "Failed to load suggestions")
  } finally {
    aiLoading.value = false
  }
}

function buildPickedPayload(it) {
  const payload = { _source: it.source }
  if (it.place_id) payload.place_id = it.place_id
  else if (it.google_place_id) payload.google_place_id = it.google_place_id
  if (!payload.place_id && !payload.google_place_id) payload.name = it.name
  return payload
}

function addAiItem(it) {
  emit("picked", buildPickedPayload(it))
}

watch(
    () => props.tripId,
    () => {
      itinerary.value = null
      originalSchedule.value = []
      aiItems.value = []
      aiError.value = ""
    }
)

watch(
    () => [props.tripId, props.placesLoading, props.places.length],
    () => {
      loadAiSuggestions()
    },
    { immediate: true }
)
</script>

<template>
  <section class="bg-white p-6 rounded-2xl border shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div class="min-w-0">
        <h2 class="text-xl font-semibold">{{ t("trip.tabs.plan") }}</h2>
        <p class="text-sm text-gray-600 mt-1">
          {{ tr("trip.plan.hint", "Generate an itinerary based on selected places and group preferences.") }}
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium border border-gray-200 bg-white text-gray-900 hover:bg-gray-50 transition disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="generating || generatingFull"
            @click="itinerary = null"
        >
          <RefreshCw class="h-4 w-4" />
          {{ tr("actions.refresh", "Refresh") }}
        </button>

        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-blue-600 to-purple-600 text-white hover:opacity-90 active:opacity-80 shadow disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="!canGenerate || generating"
            @click="generatePlan"
        >
          <Sparkles class="h-4 w-4" />
          {{ generating ? tr("trip.plan.generating", "Generating...") : tr("trip.plan.generate", "Generate plan") }}
        </button>
      </div>
    </div>

    <div class="mt-6 rounded-2xl border border-gray-200 p-5">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="font-semibold mb-1">{{ tr("trip.plan.ai_title", "AI recommendations") }}</div>
          <div class="text-sm text-gray-600">
            {{ tr("trip.plan.ai_hint", "Suggested places based on your trip context and preferences.") }}
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input
              v-model.number="aiLimit"
              type="number"
              min="1"
              max="20"
              class="w-20 h-10 px-3 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-black/10 text-sm"
          />
          <button
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium border border-gray-200 bg-white text-gray-900 hover:bg-gray-50 transition disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="aiLoading || !canSuggest"
              @click="loadAiSuggestions"
          >
            <RefreshIcon class="h-4 w-4" />
            {{ tr("actions.refresh", "Refresh") }}
          </button>
        </div>
      </div>

      <div v-if="!canSuggest" class="mt-4 p-4 rounded-xl border bg-gray-50 text-gray-700">
        <div class="font-semibold mb-1">{{ tr("trip.plan.ai_empty_title", "Add places to get suggestions") }}</div>
        <div class="text-sm text-gray-600">
          {{ tr("trip.plan.ai_empty_hint", "AI needs at least one place in the trip to suggest more.") }}
        </div>
      </div>

      <div v-else class="mt-4">
        <div v-if="aiLoading" class="p-4 rounded-xl border bg-gray-50 text-gray-700">
          {{ tr("loading", "Loading...") }}
        </div>

        <div v-else-if="aiError" class="p-4 rounded-xl bg-red-100 text-red-700 border border-red-200 text-sm">
          {{ aiError }}
        </div>

        <div v-else-if="aiItems.length === 0" class="p-4 rounded-xl border bg-gray-50 text-gray-700">
          {{ tr("trip.add_place.no_suggestions", "No suggestions found for this trip.") }}
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div
              v-for="it in aiItems"
              :key="it.unique_key"
              class="rounded-2xl border border-gray-200 p-4 bg-white hover:bg-gray-50 transition"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="font-semibold text-gray-900 truncate">{{ it.name }}</div>
                <div class="text-sm text-gray-600 mt-0.5 truncate">{{ it.address || "—" }}</div>

                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-600">
                  <span
                      v-if="it.category_slug"
                      class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs text-gray-700"
                  >
                    {{ categoryLabel(it.category_slug) }}
                  </span>

                  <span v-if="it.rating" class="inline-flex items-center gap-1">
                    <MapPin class="h-3.5 w-3.5 text-gray-500" />
                    <span class="font-semibold text-gray-900">{{ it.rating }}★</span>
                  </span>

                  <span v-if="it.distance_m" class="inline-flex items-center gap-1">
                    <span class="text-gray-400">•</span>
                    <span>{{ formatDistance(it.distance_m) }}</span>
                  </span>

                  <span v-if="it.reviews_count" class="inline-flex items-center gap-1">
                    <span class="text-gray-400">•</span>
                    <span>{{ it.reviews_count }} reviews</span>
                  </span>
                </div>
              </div>

              <button
                  type="button"
                  class="shrink-0 inline-flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-medium bg-gradient-to-r from-blue-600 to-purple-600 text-white hover:opacity-90 active:opacity-80 shadow disabled:opacity-50 disabled:cursor-not-allowed"
                  @click="addAiItem(it)"
                  :disabled="props.placesLoading"
              >
                <Plus class="h-4 w-4" />
                {{ tr("actions.add", "Add") }}
              </button>
            </div>

            <div
                v-if="it.ai_reason"
                class="mt-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800"
            >
              <div class="flex items-start gap-2">
                <Sparkles class="h-4 w-4 mt-0.5 text-gray-600" />
                <span class="leading-relaxed">{{ it.ai_reason }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-6 rounded-2xl border border-gray-200 p-5">
      <div class="font-semibold mb-1">{{ tr("trip.plan.multi_title", "Multi-day plan") }}</div>
      <div class="text-sm text-gray-600">
        {{ tr("trip.plan.multi_hint", "Generate a multi-day schedule. Fixed places may be distributed across days.") }}
      </div>

      <div class="mt-4 grid grid-cols-1 sm:grid-cols-12 gap-3">
        <div class="sm:col-span-3">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            {{ tr("trip.plan.days", "Days") }}
          </label>
          <input
              v-model.number="days"
              type="number"
              min="1"
              max="30"
              class="w-full h-11 px-4 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-black/10"
          />
        </div>

        <div class="sm:col-span-5">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            {{ tr("trip.plan.radius", "Radius (m)") }}
          </label>
          <input
              v-model.number="radius"
              type="number"
              min="100"
              max="20000"
              class="w-full h-11 px-4 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-black/10"
          />
        </div>

        <div class="sm:col-span-4 flex items-end">
          <button
              type="button"
              class="w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-blue-600 to-purple-600 text-white hover:opacity-90 active:opacity-80 shadow disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="!canGenerate || generatingFull"
              @click="generateFull"
          >
            <Sparkles class="h-4 w-4" />
            {{ generatingFull ? tr("trip.plan.generating", "Generating...") : tr("trip.plan.generate_full", "Generate full") }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="!placesLoading && places.length === 0" class="mt-6 p-5 rounded-xl border bg-gray-50 text-gray-700">
      <div class="font-semibold mb-1">{{ tr("trip.plan.empty_title", "No places yet") }}</div>
      <div class="text-sm text-gray-600">
        {{ tr("trip.plan.empty_hint", "Add places first to generate a plan.") }}
      </div>
    </div>

    <div v-else-if="placesLoading" class="mt-6 p-5 rounded-xl border bg-gray-50 text-gray-700">
      {{ tr("loading", "Loading...") }}
    </div>

    <div v-else class="mt-6">
      <div v-if="!itinerary" class="p-5 rounded-xl border bg-gray-50 text-gray-700">
        <div class="font-semibold mb-1">{{ tr("trip.plan.ready_title", "Ready to generate") }}</div>
        <div class="text-sm text-gray-600">
          {{ tr("trip.plan.ready_hint", "Click Generate plan to create a day-by-day route.") }}
        </div>
      </div>

      <div v-else class="space-y-4">
        <div class="rounded-2xl border border-gray-200 p-5">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div class="font-semibold">{{ tr("trip.plan.generated_title", "Plan generated") }}</div>
              <div class="text-sm text-gray-600 mt-1">
                {{ tr("trip.plan.generated_hint", "Below is the ordered list of places. Next: drag & drop reordering.") }}
              </div>
              <div class="text-sm text-gray-600 mt-2">
                {{ tr("trip.plan.days_label", "Days") }}: <span class="font-semibold text-gray-900">{{ itinerary.day_count }}</span>
              </div>
            </div>

            <div class="flex flex-wrap gap-2">
              <button
                  type="button"
                  class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium border border-gray-200 bg-white text-gray-900 hover:bg-gray-50 transition"
                  @click="resetOrder"
              >
                <RotateCcw class="h-4 w-4" />
                {{ tr("trip.plan.reset_order", "Reset order") }}
              </button>
            </div>
          </div>
        </div>

        <div v-for="(day, dayIndex) in itinerary.schedule" :key="day.day" class="rounded-2xl border border-gray-200 overflow-hidden">
          <div class="px-5 py-4 bg-gray-50 border-b border-gray-200">
            <div class="flex items-center justify-between gap-3">
              <div class="font-semibold">
                {{ tr("trip.plan.day", "Day") }} {{ day.day }}
              </div>
              <div class="text-sm text-gray-600">
                {{ tr("trip.plan.places", "Places") }}: <span class="font-semibold text-gray-900">{{ day.places?.length || 0 }}</span>
              </div>
            </div>
          </div>

          <ul class="divide-y divide-gray-200">
            <li
                v-for="(p, i) in day.places"
                :key="p.id"
                class="px-5 py-4 flex items-start gap-3 hover:bg-gray-50 transition"
                :class="drag.dayIndex === dayIndex && drag.overIndex === i ? 'bg-gray-50' : ''"
                draggable="true"
                @dragstart="onDragStart(dayIndex, i)"
                @dragover="onDragOver(dayIndex, i, $event)"
                @drop="onDrop(dayIndex, i)"
                @dragend="onDragEnd"
            >
              <div class="mt-0.5 h-9 w-9 rounded-xl border border-gray-200 bg-white flex items-center justify-center shrink-0 cursor-grab active:cursor-grabbing">
                <GripVertical class="h-4 w-4 text-gray-600" />
              </div>

              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <div class="font-semibold text-gray-900 truncate">
                    {{ p.name || "—" }}
                  </div>

                  <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs text-gray-700">
                    {{ categoryLabel(p.category_slug) }}
                  </span>

                  <span class="text-xs text-gray-400">•</span>

                  <span class="text-sm text-gray-700">
                    {{ tr("trip.plan.score", "Score") }}: <span class="font-semibold">{{ formatScore(p.score) }}</span>
                  </span>

                  <span class="text-xs text-gray-400">•</span>

                  <span class="text-sm text-gray-700">
                    {{ tr("trip.plan.distance", "Distance") }}: <span class="font-semibold">{{ formatDistance(p.distance_m) }}</span>
                  </span>
                </div>

                <div class="mt-1 text-xs text-gray-500">
                  #{{ p.id }}
                </div>
              </div>
            </li>
          </ul>
        </div>

        <div v-if="!itinerary.cache_info?.route_points" class="rounded-2xl border border-gray-200 p-5 text-gray-700 bg-gray-50">
          {{ tr("trip.plan.no_route_points", "No route points returned by the API yet.") }}
        </div>
      </div>
    </div>
  </section>
</template>
