<script setup>
import { ref, onMounted, watch } from "vue"
import { useRoute } from "vue-router"

import { loadGoogleMaps } from "@/utils/loadGoogleMaps.js"
import { fetchGoogleMapsKey } from "@/composables/api/google.js"
import { createTripPlace } from "@/composables/api/tripPlaces.js"

import AddPlaceModal from "@/components/trips/AddPlaceModal.vue"

const props = defineProps({
  trip: {
    type: Object,
    required: true,
  },
  places: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits(["places-changed"])

const route = useRoute()

const map = ref(null)
const mapElement = ref(null)

const showModal = ref(false)
const modalLat = ref(null)
const modalLng = ref(null)

// markers created from backend data
let markers = []

async function initMap() {
  const keyRes = await fetchGoogleMapsKey()
  const apiKey = keyRes.data.key

  const googleMaps = await loadGoogleMaps(apiKey)

  const center =
      props.trip?.start_latitude && props.trip?.start_longitude
          ? {
            lat: props.trip.start_latitude,
            lng: props.trip.start_longitude,
          }
          : {
            lat: 51.1079,
            lng: 17.0385,
          }

  map.value = new googleMaps.Map(mapElement.value, {
    center,
    zoom: 12,
  })

  renderMarkers(googleMaps, props.places)

  map.value.addListener("click", (event) => {
    modalLat.value = event.latLng.lat()
    modalLng.value = event.latLng.lng()
    showModal.value = true
  })
}

function renderMarkers(googleMaps, places) {
  markers.forEach((m) => (m.map = null))
  markers = []

  if (!Array.isArray(places)) return

  places.forEach((place) => {
    if (place.latitude == null || place.longitude == null) return

    const marker = new googleMaps.marker.AdvancedMarkerElement({
      map: map.value,
      position: {
        lat: place.latitude,
        lng: place.longitude,
      },
    })

    markers.push(marker)
  })
}

// when parent updates places from backend – we re-render markers
watch(
    () => props.places,
    (newPlaces) => {
      if (!map.value || !window.google || !window.google.maps) return
      renderMarkers(window.google.maps, newPlaces)
    },
    { deep: true }
)

async function handleSubmit(payload) {
  await createTripPlace(route.params.id, payload)

  // we do NOT push into props.places here – source of truth is backend
  // just notify parent that places changed
  emit("places-changed")
}

onMounted(initMap)
</script>

<template>
  <div class="relative">
    <div
        ref="mapElement"
        class="w-full h-72 md:h-96 rounded-xl border overflow-hidden"
    ></div>

    <AddPlaceModal
        v-model="showModal"
        :lat="modalLat"
        :lng="modalLng"
        @submit="handleSubmit"
    />
  </div>
</template>
