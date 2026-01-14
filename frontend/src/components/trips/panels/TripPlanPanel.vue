<script setup>
import { ref, computed, watch } from "vue"
import { useI18n } from "vue-i18n"
import { RefreshCw, Sparkles, GripVertical, RotateCcw } from "lucide-vue-next"

import { fetchTripItinerary, fetchTripItineraryFull } from "@/composables/api/itinerary.js"

const props = defineProps({
  tripId: { type: [String, Number], required: true },
  trip: { type: Object, required: true },
  places: { type: Array, required: true },
  placesLoading: { type: Boolean, default: false },
})

const emit = defineEmits(["error"])

const { t, te } = useI18n()

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

/**
 * Drag & drop (HTML5) — reorder inside a day
 */
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

watch(
    () => props.tripId,
    () => {
      itinerary.value = null
      originalSchedule.value = []
    }
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
