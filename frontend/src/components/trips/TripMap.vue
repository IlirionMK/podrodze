<script setup>
import { ref, onMounted, watch, nextTick, onBeforeUnmount, onActivated } from "vue"
import { useRoute } from "vue-router"

import { loadGoogleMaps } from "@/utils/loadGoogleMaps"
import { fetchGoogleMapsKey } from "@/composables/api/google"
import { createTripPlace } from "@/composables/api/tripPlaces"

import AddPlaceModal from "@/components/trips/AddPlaceModal.vue"

const props = defineProps({
  trip: Object,
  places: Array,
  selectedTripPlaceId: { type: [Number, String, null], default: null },
})

const emit = defineEmits(["places-changed"])
const route = useRoute()

const map = ref(null)
const mapElement = ref(null)

const showModal = ref(false)
const modalLat = ref(null)
const modalLng = ref(null)

let google = null
let destroyed = false
let markers = []
let markerByTripPlaceId = new Map()
let lastBounds = null
let resizeObserver = null
let initPromise = null

const mapId = (import.meta.env.VITE_GOOGLE_MAPS_MAP_ID || "").trim() || undefined

function toNumber(v) {
  if (v == null) return null
  if (typeof v === "number") return Number.isFinite(v) ? v : null
  if (typeof v === "string") {
    const n = Number(v.replace(",", "."))
    return Number.isFinite(n) ? n : null
  }
  return null
}

function isValidLatLng(lat, lng) {
  return lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180
}

function extractCoords(tp) {
  const p = tp?.place ?? tp
  const lat = toNumber(p?.lat) ?? toNumber(p?.latitude)
  const lng = toNumber(p?.lon) ?? toNumber(p?.lng) ?? toNumber(p?.longitude)
  if (lat == null || lng == null) return null
  if (!isValidLatLng(lat, lng)) return null
  return { lat, lng }
}

function getCenter() {
  const lat = toNumber(props.trip?.start_latitude)
  const lng = toNumber(props.trip?.start_longitude)
  if (lat != null && lng != null && isValidLatLng(lat, lng)) return { lat, lng }
  return { lat: 51.1079, lng: 17.0385 }
}

function clearMarkers() {
  for (const m of markers) {
    if (!m) continue
    if (typeof m.setMap === "function") m.setMap(null)
  }
  markers = []
  markerByTripPlaceId = new Map()
}

function triggerResizeAndRefit() {
  if (!google || !map.value) return
  if (google.maps?.event?.trigger) google.maps.event.trigger(map.value, "resize")
  if (lastBounds && markers.length > 0) {
    map.value.fitBounds(lastBounds, 60)
  }
}

async function ensureMap() {
  if (map.value) return
  if (initPromise) return initPromise

  initPromise = (async () => {
    destroyed = false
    await nextTick()
    if (destroyed || !mapElement.value) return

    const { data } = await fetchGoogleMapsKey()
    if (destroyed || !mapElement.value) return

    google = await loadGoogleMaps(data.key)
    if (destroyed || !mapElement.value) return

    const mapsLib = await google.maps.importLibrary("maps")
    if (destroyed || !mapElement.value) return

    map.value = new mapsLib.Map(mapElement.value, {
      center: getCenter(),
      zoom: 12,
      mapId,
      gestureHandling: "greedy",
    })

    map.value.addListener("click", (event) => {
      if (!event?.latLng) return
      modalLat.value = event.latLng.lat()
      modalLng.value = event.latLng.lng()
      showModal.value = true
    })

    resizeObserver = new ResizeObserver(() => triggerResizeAndRefit())
    resizeObserver.observe(mapElement.value)
  })()

  return initPromise
}

async function renderMarkers(list = []) {
  if (!map.value || !google) return

  clearMarkers()
  lastBounds = new google.maps.LatLngBounds()

  for (const tp of list) {
    const coords = extractCoords(tp)
    if (!coords) continue

    const name = tp?.place?.name || ""
    const letter = name ? name.slice(0, 1).toUpperCase() : undefined

    lastBounds.extend(coords)

    const marker = new google.maps.Marker({
      map: map.value,
      position: coords,
      title: name,
      label: letter,
    })

    markers.push(marker)
    if (tp?.id != null) markerByTripPlaceId.set(Number(tp.id), marker)
  }

  if (markers.length > 0) {
    map.value.fitBounds(lastBounds, 60)
  }
}

function focusSelected(id) {
  if (!map.value || !google) return
  const n = id == null ? null : Number(id)
  if (!n || !markerByTripPlaceId.has(n)) return

  const marker = markerByTripPlaceId.get(n)
  const pos = marker?.getPosition?.()
  if (!pos) return

  map.value.panTo(pos)
  const z = map.value.getZoom?.()
  if (typeof z === "number" && z < 14) map.value.setZoom(14)
}

watch(
    () => props.places,
    async (newPlaces) => {
      await ensureMap()
      if (!map.value) return
      await renderMarkers(newPlaces || [])
      focusSelected(props.selectedTripPlaceId)
    },
    { deep: true }
)

watch(
    () => props.selectedTripPlaceId,
    (id) => {
      focusSelected(id)
    }
)

async function handleSubmit(payload) {
  await createTripPlace(route.params.id, payload)
  emit("places-changed")
}

onMounted(async () => {
  await ensureMap()
  if (!map.value) return
  await renderMarkers(props.places || [])
  focusSelected(props.selectedTripPlaceId)
  requestAnimationFrame(() => triggerResizeAndRefit())
})

onActivated(() => {
  requestAnimationFrame(() => triggerResizeAndRefit())
  focusSelected(props.selectedTripPlaceId)
})

onBeforeUnmount(() => {
  destroyed = true
  if (resizeObserver && mapElement.value) resizeObserver.unobserve(mapElement.value)
  resizeObserver = null
  clearMarkers()
  map.value = null
  initPromise = null
})
</script>

<template>
  <div class="relative">
    <div ref="mapElement" class="w-full h-72 md:h-96 rounded-xl border overflow-hidden"></div>
    <AddPlaceModal v-model="showModal" :lat="modalLat" :lng="modalLng" @submit="handleSubmit" />
  </div>
</template>
