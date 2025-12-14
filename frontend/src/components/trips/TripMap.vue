<script setup>
import { ref, onMounted, watch } from "vue"
import { useRoute } from "vue-router"

import { loadGoogleMaps } from "@/utils/loadGoogleMaps"
import { fetchGoogleMapsKey } from "@/composables/api/google"
import { createTripPlace } from "@/composables/api/tripPlaces"

import AddPlaceModal from "@/components/trips/AddPlaceModal.vue"

const props = defineProps({
  trip: Object,
  places: Array,
})

const emit = defineEmits(["places-changed"])
const route = useRoute()

const map = ref(null)
const mapElement = ref(null)

const showModal = ref(false)
const modalLat = ref(null)
const modalLng = ref(null)

let google = null
let MapClass = null
let markers = []

function toNumber(v) {
  if (v == null) return null
  if (typeof v === "number") return Number.isFinite(v) ? v : null
  if (typeof v === "string") {
    const n = Number(v.replace(",", "."))
    return Number.isFinite(n) ? n : null
  }
  return null
}

async function initMap() {
  console.log("[TripMap] initMap")

  const { data } = await fetchGoogleMapsKey()
  google = await loadGoogleMaps(data.key)

  const mapsLib = await google.maps.importLibrary("maps")
  MapClass = mapsLib.Map

  const center =
      props.trip?.start_latitude && props.trip?.start_longitude
          ? { lat: Number(props.trip.start_latitude), lng: Number(props.trip.start_longitude) }
          : { lat: 51.1079, lng: 17.0385 }

  map.value = new MapClass(mapElement.value, {
    center,
    zoom: 12,
  })

  renderMarkers(props.places)

  map.value.addListener("click", (event) => {
    modalLat.value = event.latLng.lat()
    modalLng.value = event.latLng.lng()
    showModal.value = true
  })
}

function clearMarkers() {
  markers.forEach((m) => m.setMap(null))
  markers = []
}

function renderMarkers(places = []) {
  if (!map.value || !google) return

  console.log("[TripMap] renderMarkers places:", places)

  clearMarkers()

  const bounds = new google.maps.LatLngBounds()

  places.forEach((tp) => {
    console.log("[TripMap] TripPlace:", tp)

    const lat = toNumber(tp?.place?.lat)
    const lng = toNumber(tp?.place?.lon)

    console.log("[TripMap] coords:", lat, lng)

    if (lat == null || lng == null) {
      console.warn("[TripMap] missing/invalid coords for place", tp)
      return
    }

    const position = { lat, lng }
    bounds.extend(position)

    const marker = new google.maps.Marker({
      map: map.value,
      position,
      title: tp?.place?.name || "",
      label: tp?.place?.name ? tp.place.name.slice(0, 1).toUpperCase() : undefined,
    })

    markers.push(marker)
  })

  console.log("[TripMap] markers rendered:", markers.length)

  if (markers.length > 0) {
    map.value.fitBounds(bounds, 60)
  }
}

watch(
    () => props.places,
    (newPlaces) => {
      console.log("[TripMap] places changed", newPlaces)
      if (!map.value) return
      renderMarkers(newPlaces)
    },
    { deep: true }
)

async function handleSubmit(payload) {
  await createTripPlace(route.params.id, payload)
  emit("places-changed")
}

onMounted(initMap)
</script>

<template>
  <div class="relative">
    <div ref="mapElement" class="w-full h-72 md:h-96 rounded-xl border overflow-hidden"></div>

    <AddPlaceModal v-model="showModal" :lat="modalLat" :lng="modalLng" @submit="handleSubmit" />
  </div>
</template>
