<script setup>
import { computed } from "vue"
import { useI18n } from "vue-i18n"
import {
  Plus,
  RefreshCw,
  Search,
  ExternalLink,
  Pin,
  Utensils,
  Landmark,
  Trees,
  Sparkles,
  MoonStar,
  HelpCircle,
  UserRound,
} from "lucide-vue-next"
import TripMap from "@/components/trips/TripMap.vue"

const props = defineProps({
  trip: { type: Object, required: true },
  places: { type: Array, required: true },
  placesLoading: { type: Boolean, default: false },

  categories: { type: Array, required: true },
  filteredPlaces: { type: Array, required: true },

  placeQuery: { type: String, required: true },
  categoryFilter: { type: String, required: true },
  sortKey: { type: String, required: true },

  selectedTripPlaceId: { type: [Number, String, null], default: null },

  labels: {
    type: Object,
    default: () => ({}),
  },

  placeholder: { type: String, default: "" },
})

const emit = defineEmits([
  "update:placeQuery",
  "update:categoryFilter",
  "update:sortKey",
  "select-place",
  "refresh-places",
  "open-add-place",
  "clear-selection",
])

const { t, te } = useI18n()
function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

const localLabels = computed(() => ({
  title: props.labels.title ?? tr("trip.view.places", "Places"),
  addBtn: props.labels.addBtn ?? tr("trip.view.add_place", "Add place"),
  refreshBtn: props.labels.refreshBtn ?? tr("actions.refresh", "Refresh"),
  openLabel: props.labels.openLabel ?? tr("trip.view.open", "Open"),
  mapTitle: props.labels.mapTitle ?? tr("trip.view.map", "Map"),
  loading: props.labels.loading ?? tr("loading", "Loading…"),
  emptyTitle: props.labels.emptyTitle ?? tr("trip.places.empty_title", "No places"),
  emptyHint: props.labels.emptyHint ?? tr("trip.places.empty_hint", "Add the first place to start planning."),
  clearSelection: props.labels.clearSelection ?? tr("trip.map.clear_selection", "Clear selection"),
  fixed: props.labels.fixed ?? tr("trip.places.fixed", "Fixed"),
  addedBy: props.labels.addedBy ?? tr("trip.places.added_by", "Added by"),
}))

const queryModel = computed({
  get: () => props.placeQuery,
  set: (v) => emit("update:placeQuery", v),
})

const categoryModel = computed({
  get: () => props.categoryFilter,
  set: (v) => emit("update:categoryFilter", v),
})

const sortModel = computed({
  get: () => props.sortKey,
  set: (v) => emit("update:sortKey", v),
})

const btnBase =
    "inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
const btnPrimary =
    btnBase + " bg-gradient-to-r from-blue-600 to-purple-600 text-white hover:opacity-90 active:opacity-80 shadow"
const btnSecondary =
    btnBase + " border border-gray-200 bg-white text-gray-900 hover:bg-gray-50"

function isFixed(pl) {
  return Boolean(pl?.is_fixed ?? pl?.fixed ?? pl?.is_mandatory ?? false)
}

function categoryIcon(slug) {
  const s = String(slug || "").toLowerCase()
  if (s === "food") return Utensils
  if (s === "museum") return Landmark
  if (s === "nature") return Trees
  if (s === "attraction") return Sparkles
  if (s === "nightlife") return MoonStar
  return HelpCircle
}

function categoryLabel(slug) {
  const s = String(slug || "")
  const key = `trip.categories.${s}`
  if (te(key)) return t(key)
  return s || "—"
}

function addedByName(pl) {
  const v =
      pl?.added_by?.name ||
      pl?.created_by?.name ||
      pl?.addedBy?.name ||
      pl?.creator?.name ||
      pl?.user?.name ||
      pl?.created_by_name ||
      pl?.added_by_name ||
      null
  return v ? String(v) : null
}
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
    <div class="lg:col-span-7 flex flex-col gap-6 lg:gap-8">
      <section class="bg-white p-6 rounded-2xl border shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
          <h2 class="text-xl font-semibold">
            <slot name="title">{{ localLabels.title }}</slot>
          </h2>

          <div class="flex gap-2">
            <button type="button" :class="btnPrimary" @click="$emit('open-add-place')">
              <Plus class="h-4 w-4" />
              <slot name="addBtn">{{ localLabels.addBtn }}</slot>
            </button>

            <button type="button" :class="btnSecondary" @click="$emit('refresh-places')">
              <RefreshCw class="h-4 w-4" />
              <slot name="refreshBtn">{{ localLabels.refreshBtn }}</slot>
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mb-4">
          <div class="md:col-span-6">
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                  v-model="queryModel"
                  type="text"
                  class="w-full h-11 pl-10 pr-4 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-black/10"
                  :placeholder="placeholder"
              />
            </div>
          </div>

          <div class="md:col-span-3">
            <select
                v-model="categoryModel"
                class="w-full h-11 px-4 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-black/10"
            >
              <option v-for="c in categories" :key="c" :value="c">
                <slot name="categoryLabel" :c="c">{{ c }}</slot>
              </option>
            </select>
          </div>

          <div class="md:col-span-3">
            <select
                v-model="sortModel"
                class="w-full h-11 px-4 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-black/10"
            >
              <option value="name_asc"><slot name="sortNameAsc">Name A→Z</slot></option>
              <option value="name_desc"><slot name="sortNameDesc">Name Z→A</slot></option>
              <option value="cat_asc"><slot name="sortCatAsc">Category A→Z</slot></option>
              <option value="cat_desc"><slot name="sortCatDesc">Category Z→A</slot></option>
            </select>
          </div>
        </div>

        <div v-if="placesLoading" class="text-sm text-gray-500 py-6">
          <slot name="loading">{{ localLabels.loading }}</slot>
        </div>

        <div
            v-else-if="filteredPlaces.length === 0"
            class="p-6 rounded-xl border border-gray-200 bg-gray-50 text-gray-700"
        >
          <div class="font-semibold mb-1"><slot name="emptyTitle">{{ localLabels.emptyTitle }}</slot></div>
          <div class="text-sm text-gray-600"><slot name="emptyHint">{{ localLabels.emptyHint }}</slot></div>
        </div>

        <div v-else class="flex flex-col gap-3">
          <button
              v-for="pl in filteredPlaces"
              :key="pl.id"
              type="button"
              class="text-left border border-gray-200 rounded-xl p-4 flex items-center justify-between gap-4 transition hover:bg-gray-50"
              :class="selectedTripPlaceId === pl.id ? 'ring-2 ring-black/10 bg-gray-50' : ''"
              @click="$emit('select-place', pl.id)"
          >
            <div class="flex items-start gap-3 min-w-0">
              <div
                  class="h-10 w-10 rounded-2xl border border-gray-200 bg-white flex items-center justify-center shrink-0"
                  :title="categoryLabel(pl.place?.category_slug)"
              >
                <component :is="categoryIcon(pl.place?.category_slug)" class="h-5 w-5 text-gray-800" />
              </div>

              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="font-semibold truncate text-gray-900">
                    {{ pl.place?.name || "—" }}
                  </h3>

                  <span
                      v-if="isFixed(pl)"
                      class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs text-blue-700"
                      :title="localLabels.fixed"
                  >
                    <Pin class="h-3.5 w-3.5" />
                    {{ localLabels.fixed }}
                  </span>
                </div>

                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                  <span class="inline-flex items-center gap-1">
                    {{ categoryLabel(pl.place?.category_slug) }}
                  </span>

                  <span v-if="addedByName(pl)" class="inline-flex items-center gap-1">
                    <span class="text-gray-300">•</span>
                    <UserRound class="h-4 w-4" />
                    {{ localLabels.addedBy }} {{ addedByName(pl) }}
                  </span>
                </div>
              </div>
            </div>

            <span class="inline-flex items-center gap-2 text-sm font-medium text-blue-700 shrink-0">
              <ExternalLink class="h-4 w-4" />
              <slot name="openLabel">{{ localLabels.openLabel }}</slot>
            </span>
          </button>
        </div>
      </section>
    </div>

    <div class="lg:col-span-5">
      <div class="flex flex-col gap-6 lg:gap-8 lg:sticky lg:top-6">
        <section class="bg-white p-6 rounded-2xl border shadow-sm">
          <div class="flex items-center justify-between gap-3 mb-4">
            <h2 class="text-xl font-semibold">
              <slot name="mapTitle">{{ localLabels.mapTitle }}</slot>
            </h2>

            <div class="text-sm text-gray-500">
              <slot name="mapCount" :count="places.length">{{ places.length }}</slot>
            </div>
          </div>

          <div class="w-full rounded-2xl overflow-hidden border border-gray-200">
            <TripMap
                class="block w-full"
                :trip="trip"
                :places="places"
                :selected-trip-place-id="selectedTripPlaceId"
                @places-changed="$emit('refresh-places')"
            />
          </div>

          <div v-if="$slots.clearSelection" class="mt-4 flex flex-wrap gap-2">
            <button type="button" :class="btnSecondary" @click="$emit('clear-selection')">
              <slot name="clearSelection">{{ localLabels.clearSelection }}</slot>
            </button>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
