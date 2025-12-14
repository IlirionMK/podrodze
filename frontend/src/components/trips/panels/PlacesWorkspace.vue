<script setup>
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
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    <div class="lg:col-span-7 flex flex-col gap-8">
      <section class="bg-white p-6 rounded-2xl border shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-4">
          <h2 class="text-xl font-semibold">
            <slot name="title">Places</slot>
          </h2>

          <div class="flex gap-2">
            <button
                type="button"
                class="px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition"
                @click="$emit('open-add-place')"
            >
              <slot name="addBtn">Add place</slot>
            </button>

            <button
                type="button"
                class="px-4 py-2 bg-gray-900 text-white rounded-xl hover:bg-black transition"
                @click="$emit('refresh-places')"
            >
              <slot name="refreshBtn">Refresh</slot>
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mb-4">
          <div class="md:col-span-6">
            <input
                :value="placeQuery"
                type="text"
                class="w-full px-4 py-2.5 rounded-xl border bg-white focus:outline-none focus:ring-2 focus:ring-black/10"
                @input="$emit('update:placeQuery', $event.target.value)"
                :placeholder="$attrs.placeholder || ''"
            />
          </div>

          <div class="md:col-span-3">
            <select
                :value="categoryFilter"
                class="w-full px-4 py-2.5 rounded-xl border bg-white focus:outline-none focus:ring-2 focus:ring-black/10"
                @change="$emit('update:categoryFilter', $event.target.value)"
            >
              <option v-for="c in categories" :key="c" :value="c">
                <slot name="categoryLabel" :c="c">{{ c }}</slot>
              </option>
            </select>
          </div>

          <div class="md:col-span-3">
            <select
                :value="sortKey"
                class="w-full px-4 py-2.5 rounded-xl border bg-white focus:outline-none focus:ring-2 focus:ring-black/10"
                @change="$emit('update:sortKey', $event.target.value)"
            >
              <option value="name_asc"><slot name="sortNameAsc">Name A→Z</slot></option>
              <option value="name_desc"><slot name="sortNameDesc">Name Z→A</slot></option>
              <option value="cat_asc"><slot name="sortCatAsc">Category A→Z</slot></option>
              <option value="cat_desc"><slot name="sortCatDesc">Category Z→A</slot></option>
            </select>
          </div>
        </div>

        <div v-if="placesLoading" class="text-sm text-gray-500 py-6">
          <slot name="loading">Loading…</slot>
        </div>

        <div v-else-if="filteredPlaces.length === 0" class="p-6 rounded-xl border bg-gray-50 text-gray-700">
          <div class="font-semibold mb-1"><slot name="emptyTitle">No places</slot></div>
          <div class="text-sm text-gray-600"><slot name="emptyHint">Add the first place to start planning.</slot></div>
        </div>

        <div v-else class="flex flex-col gap-3">
          <button
              v-for="pl in filteredPlaces"
              :key="pl.id"
              type="button"
              class="text-left border rounded-xl p-4 flex items-center justify-between transition"
              :class="selectedTripPlaceId === pl.id ? 'bg-gray-900 text-white border-gray-900' : 'hover:bg-gray-50'"
              @click="$emit('select-place', pl.id)"
          >
            <div class="min-w-0">
              <h3 class="font-semibold truncate">
                {{ pl.place?.name || "—" }}
              </h3>
              <p
                  class="text-sm mt-0.5 truncate"
                  :class="selectedTripPlaceId === pl.id ? 'text-white/80' : 'text-gray-500'"
              >
                {{ pl.place?.category_slug || "—" }}
              </p>
            </div>

            <span
                class="text-sm font-medium"
                :class="selectedTripPlaceId === pl.id ? 'text-white/90' : 'text-blue-600'"
            >
              <slot name="openLabel">Open</slot>
            </span>
          </button>
        </div>
      </section>
    </div>

    <div class="lg:col-span-5">
      <div class="flex flex-col gap-8 lg:sticky lg:top-6">
        <section class="bg-white p-6 rounded-2xl border shadow-sm">
          <div class="flex items-center justify-between gap-3 mb-4">
            <h2 class="text-xl font-semibold">
              <slot name="mapTitle">Map</slot>
            </h2>

            <div class="text-sm text-gray-500">
              <slot name="mapCount" :count="places.length">{{ places.length }}</slot>
            </div>
          </div>

          <div class="w-full rounded-2xl overflow-hidden border" style="min-height: 520px;">
            <TripMap
                :trip="trip"
                :places="places"
                :selected-trip-place-id="selectedTripPlaceId"
                @places-changed="$emit('refresh-places')"
            />
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <button
                type="button"
                class="px-4 py-2 rounded-xl border bg-white hover:bg-gray-50 transition"
                @click="$emit('clear-selection')"
            >
              <slot name="clearSelection">Clear selection</slot>
            </button>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
