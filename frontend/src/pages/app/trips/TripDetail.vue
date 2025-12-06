<script setup>
import { ref, onMounted, watch } from "vue"
import { useRoute } from "vue-router"
import { useI18n } from "vue-i18n"

import { fetchTrip } from "@/composables/api/trips"
import { fetchGoogleMapsKey } from "@/composables/api/google"
import { loadGoogleMaps } from "@/utils/loadGoogleMaps"

const { t } = useI18n()
const route = useRoute()

const trip = ref(null)
const loading = ref(true)
const error = ref(null)

// MAP state
const map = ref(null)
const googleLib = ref(null)
const markers = ref([])

const formatDate = (dateString) => {
  if (!dateString) return "-"
  return new Intl.DateTimeFormat("pl-PL", {
    year: "numeric",
    month: "long",
    day: "numeric"
  }).format(new Date(dateString))
}

onMounted(async () => {
  try {
    const res = await fetchTrip(route.params.id)
    trip.value = res.data.data ?? res.data
  } catch (err) {
    error.value = t("errors.tripLoad")
  } finally {
    loading.value = false
  }
})

// initialize Google Map once trip loaded
watch(trip, async (v) => {
  if (!v) return

  // load key
  const keyResponse = await fetchGoogleMapsKey()
  const apiKey = keyResponse.data.key

  googleLib.value = await loadGoogleMaps(apiKey)

  initMap()
})

function initMap() {
  if (!trip.value || !googleLib.value) return

  const google = googleLib.value

  const lat = parseFloat(trip.value.start_latitude ?? 51.11)
  const lng = parseFloat(trip.value.start_longitude ?? 17.03)

  map.value = new google.Map(document.getElementById("trip-map"), {
    center: { lat, lng },
    zoom: 12,
    mapTypeControl: false,
    streetViewControl: false
  })

  // existing places
  if (trip.value.places) {
    trip.value.places.forEach((p) => {
      if (!p.lat || !p.lon) return

      const marker = new google.Marker({
        position: { lat: parseFloat(p.lat), lng: parseFloat(p.lon) },
        map: map.value,
        title: p.name
      })

      markers.value.push(marker)
    })
  }

  // add place on click
  map.value.addListener("click", (e) => {
    const lat = e.latLng.lat()
    const lng = e.latLng.lng()

    console.log("New point:", lat, lng)

    new google.Marker({
      position: { lat, lng },
      map: map.value
    })

    // TODO: запрос на backend для добавления места:
    // axios.post(`/api/v1/trips/${trip.value.id}/places`, { lat, lng })
  })
}
</script>

<template>
  <div class="p-6 space-y-6">

    <div v-if="loading" class="text-gray-500 text-center">Loading...</div>
    <div v-else-if="error" class="text-red-500 text-center">{{ error }}</div>

    <div v-else>

      <!-- HEADER -->
      <div class="relative h-56 rounded-xl overflow-hidden bg-gray-200 shadow">
        <div class="absolute left-6 bottom-6 text-white drop-shadow">

          <h1 class="text-3xl font-bold">
            {{ trip.name }}
          </h1>

          <p class="text-sm opacity-90">
            {{ formatDate(trip.start_date) }} — {{ formatDate(trip.end_date) }}
          </p>

        </div>
      </div>

      <!-- MAIN GRID -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-6">
          <!-- about trip -->
          <div class="rounded-xl border p-6 bg-white shadow-sm">
            <h2 class="text-lg font-semibold mb-2">
              {{ t("trip.about") }}
            </h2>

            <p class="text-gray-600 mb-4">
              {{ trip.description || t("trip.descriptionMissing") }}
            </p>

            <div class="grid grid-cols-2 gap-4 text-sm text-gray-500">
              <div>
                <div class="font-medium">{{ t("trip.startDate") }}</div>
                <div>{{ formatDate(trip.start_date) }}</div>
              </div>

              <div>
                <div class="font-medium">{{ t("trip.endDate") }}</div>
                <div>{{ formatDate(trip.end_date) }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- MAP -->
        <div>
          <div class="rounded-xl border p-6 bg-white shadow-sm">
            <h2 class="text-lg font-semibold mb-3">
              {{ t("trip.map") }}
            </h2>

            <div id="trip-map" class="w-full h-64 rounded-lg bg-gray-200"></div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
