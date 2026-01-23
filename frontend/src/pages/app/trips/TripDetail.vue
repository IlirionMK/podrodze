<script setup>
import { ref, computed, watch } from "vue"
import { useRoute, useRouter } from "vue-router"
import { useI18n } from "vue-i18n"

import api from "@/composables/api/api.js"
import { fetchTrip } from "@/composables/api/trips.js"
import {
  fetchTripPlaces,
  createTripPlace,
  updateTripPlace,
  deleteTripPlace,
} from "@/composables/api/tripPlaces.js"
import { fetchTripMembers } from "@/composables/api/tripMembers.js"

import TripHeaderBar from "@/components/trips/TripHeaderBar.vue"
import TripTabs from "@/components/trips/TripTabs.vue"
import PlacesWorkspace from "@/components/trips/panels/PlacesWorkspace.vue"
import TripMembersPanel from "@/components/trips/panels/TripMembersPanel.vue"
import TripPreferencesPanel from "@/components/trips/panels/TripPreferencesPanel.vue"
import TripPlanPanel from "@/components/trips/panels/TripPlanPanel.vue"
import PlaceSearchModal from "@/components/trips/PlaceSearchModal.vue"
import PlaceDetailsDrawer from "@/components/trips/PlaceDetailsDrawer.vue"

const route = useRoute()
const router = useRouter()
const { t, te, locale } = useI18n()

function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

function getErrMessage(err) {
  return err?.response?.data?.message || err?.response?.data?.error || tr("errors.default", "Something went wrong.")
}

const tripId = computed(() => String(route.params.id || ""))

const trip = ref(null)
const members = ref([])
const places = ref([])

const loading = ref(true)
const membersLoading = ref(false)
const placesLoading = ref(false)

const errorMsg = ref("")
const activeTab = ref("places")

const placeQuery = ref("")
const categoryFilter = ref("all")
const sortKey = ref("name_asc")

const selectedTripPlaceId = ref(null)
const placeSearchOpen = ref(false)

const placeModalOpen = ref(false)
const actionBusy = ref(false)

const votesByPlaceId = ref({})

const bannerImage =
    "https://images.unsplash.com/photo-1528909514045-2fa4ac7a08ba?auto=format&fit=crop&w=1600&q=80"

const allowedTabs = new Set(["places", "plan", "team", "preferences"])

function initTabFromRoute() {
  const qTab = String(route.query.tab || "")
  activeTab.value = allowedTabs.has(qTab) ? qTab : "places"
}

function setTab(tab) {
  activeTab.value = tab
  router.replace({ query: { ...route.query, tab } })
}

function openPlaceSearch() {
  placeSearchOpen.value = true
}

function formatDate(value) {
  if (!value) return "—"
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  try {
    return new Intl.DateTimeFormat(locale.value || "en", {
      year: "numeric",
      month: "short",
      day: "2-digit",
    }).format(d)
  } catch {
    return d.toISOString().slice(0, 10)
  }
}

async function refreshPlaces(id = tripId.value) {
  if (!id) return
  placesLoading.value = true
  try {
    const res = await fetchTripPlaces(id)
    places.value = res.data.data || []
  } catch (err) {
    errorMsg.value = getErrMessage(err)
  } finally {
    placesLoading.value = false
  }
}

async function refreshMembers(id = tripId.value) {
  if (!id) return
  membersLoading.value = true
  try {
    const res = await fetchTripMembers(id)
    members.value = res.data.data || []
  } catch (err) {
    errorMsg.value = getErrMessage(err)
  } finally {
    membersLoading.value = false
  }
}

async function refreshVotes(id = tripId.value) {
  if (!id) return
  try {
    const res = await api.get(`/trips/${id}/places/votes`)
    const items = res.data?.data || []
    const map = {}
    for (const v of items) {
      if (v?.place_id != null) map[String(v.place_id)] = v
    }
    votesByPlaceId.value = map
  } catch {
    // optional
  }
}

let loadSeq = 0
async function loadData(id = tripId.value) {
  if (!id) return
  const seq = ++loadSeq

  loading.value = true
  errorMsg.value = ""

  try {
    const [tripRes, membersRes, placesRes] = await Promise.all([
      fetchTrip(id),
      fetchTripMembers(id),
      fetchTripPlaces(id),
    ])

    if (seq !== loadSeq) return

    trip.value = tripRes.data.data
    members.value = membersRes.data.data || []
    places.value = placesRes.data.data || []

    await refreshVotes(id)
  } catch (err) {
    if (seq !== loadSeq) return
    errorMsg.value = getErrMessage(err)
  } finally {
    if (seq === loadSeq) loading.value = false
  }
}

const categories = computed(() => {
  const set = new Set()
  for (const p of places.value) {
    const slug = p?.place?.category_slug
    if (slug) set.add(slug)
  }
  return ["all", ...Array.from(set).sort((a, b) => a.localeCompare(b))]
})

const filteredPlaces = computed(() => {
  const q = placeQuery.value.trim().toLowerCase()
  let list = [...places.value]

  if (categoryFilter.value !== "all") {
    list = list.filter((p) => p?.place?.category_slug === categoryFilter.value)
  }

  if (q) {
    list = list.filter((p) => {
      const name = (p?.place?.name || "").toLowerCase()
      const cat = (p?.place?.category_slug || "").toLowerCase()
      return name.includes(q) || cat.includes(q)
    })
  }

  switch (sortKey.value) {
    case "name_desc":
      list.sort((a, b) => (b?.place?.name || "").localeCompare(a?.place?.name || ""))
      break
    case "cat_asc":
      list.sort((a, b) => (a?.place?.category_slug || "").localeCompare(b?.place?.category_slug || ""))
      break
    case "cat_desc":
      list.sort((a, b) => (b?.place?.category_slug || "").localeCompare(b?.place?.category_slug || ""))
      break
    default:
      list.sort((a, b) => (a?.place?.name || "").localeCompare(b?.place?.name || ""))
  }

  return list
})

const stats = computed(() => ({
  places: places.value.length,
  members: members.value.length,
  activities: 0,
}))

const selectedTripPlace = computed(() => {
  const id = selectedTripPlaceId.value
  if (!id) return null
  return places.value.find((p) => p.id === id) || places.value.find((p) => p?.place?.id === id) || null
})

const selectedPlaceId = computed(() => {
  const tp = selectedTripPlace.value
  return tp?.place?.id ?? null
})

function extractCoords(tp) {
  const lat = tp?.place?.lat ?? tp?.place?.location?.lat ?? tp?.lat ?? null
  const lon = tp?.place?.lon ?? tp?.place?.location?.lng ?? tp?.lon ?? null
  if (lat == null || lon == null) return null
  return { lat: Number(lat), lon: Number(lon) }
}

const selectedIsStart = computed(() => {
  const tp = selectedTripPlace.value
  if (!tp || !trip.value) return false
  const coords = extractCoords(tp)
  if (!coords) return false
  return Number(trip.value?.start_latitude) === coords.lat && Number(trip.value?.start_longitude) === coords.lon
})

const selectedIsFixed = computed(() => {
  const tp = selectedTripPlace.value
  if (!tp) return false
  return Boolean(tp.is_fixed ?? tp.fixed ?? tp.is_mandatory ?? false)
})

const selectedRating = computed(() => {
  const pid = selectedPlaceId.value
  if (!pid) return null
  const v = votesByPlaceId.value[String(pid)]
  return v?.my_score ?? null
})

function onSelectPlace(id) {
  selectedTripPlaceId.value = id
  placeModalOpen.value = true
}

watch(() => route.query.tab, initTabFromRoute, { immediate: true })

watch(
    tripId,
    async (id) => {
      if (!id) return
      initTabFromRoute()
      await loadData(id)
    },
    { immediate: true }
)

watch(
    () => placeModalOpen.value,
    (v) => {
      if (!v) selectedTripPlaceId.value = null
    }
)

async function onAddPlace(payload) {
  placeSearchOpen.value = false
  placesLoading.value = true
  try {
    await createTripPlace(tripId.value, payload)
    if (activeTab.value !== "places") setTab("places")
    await refreshPlaces()
    await refreshVotes()
  } catch (e) {
    errorMsg.value = getErrMessage(e)
  } finally {
    placesLoading.value = false
  }
}

function normalizeKey(payload) {
  if (payload?.place_id != null) return `db:${String(payload.place_id)}`
  if (payload?.google_place_id) return `g:${String(payload.google_place_id).replace(/^google:/, "")}`
  if (payload?.name) return `n:${String(payload.name).trim().toLowerCase()}`
  return null
}

function existingKeys() {
  const set = new Set()

  for (const tp of places.value || []) {
    const p = tp?.place || tp
    const id = p?.id ?? tp?.place_id ?? null
    const g = p?.google_place_id ?? p?.external_id ?? null
    const name = p?.name ?? null

    if (id != null) set.add(`db:${String(id)}`)
    if (g) set.add(`g:${String(g).replace(/^google:/, "")}`)
    if (name) set.add(`n:${String(name).trim().toLowerCase()}`)
  }

  return set
}

function isAlreadyAttachedMessage(msg) {
  const s = String(msg || "").toLowerCase()
  return s.includes("already attached") || s.includes("already exists") || s.includes("already added")
}

async function onAddSuggestedPlace(payload) {
  const key = normalizeKey(payload)
  if (key && existingKeys().has(key)) {
    return
  }

  placesLoading.value = true
  try {
    await createTripPlace(tripId.value, payload)
    await refreshPlaces()
    await refreshVotes()
  } catch (e) {
    const msg = getErrMessage(e)
    if (isAlreadyAttachedMessage(msg)) return
    errorMsg.value = msg
  } finally {
    placesLoading.value = false
  }
}

async function doToggleFixed() {
  if (!selectedPlaceId.value) return
  actionBusy.value = true
  try {
    await updateTripPlace(tripId.value, selectedPlaceId.value, { is_fixed: !selectedIsFixed.value })
    await refreshPlaces()
  } catch (e) {
    errorMsg.value = getErrMessage(e)
  } finally {
    actionBusy.value = false
  }
}

async function doRemove() {
  if (!selectedPlaceId.value) return
  actionBusy.value = true
  try {
    await deleteTripPlace(tripId.value, selectedPlaceId.value)
    placeModalOpen.value = false
    selectedTripPlaceId.value = null
    await refreshPlaces()
    await refreshVotes()
  } catch (e) {
    errorMsg.value = getErrMessage(e)
  } finally {
    actionBusy.value = false
  }
}

async function doRate(stars) {
  if (!selectedPlaceId.value) return
  actionBusy.value = true
  try {
    const res = await api.post(`/trips/${tripId.value}/places/${selectedPlaceId.value}/vote`, {
      score: Number(stars),
    })
    const vote = res.data?.data
    if (vote?.place_id != null) {
      votesByPlaceId.value = {
        ...votesByPlaceId.value,
        [String(vote.place_id)]: vote,
      }
    } else {
      await refreshVotes()
    }
  } catch (e) {
    errorMsg.value = getErrMessage(e)
  } finally {
    actionBusy.value = false
  }
}

async function doSetStart() {
  const tp = selectedTripPlace.value
  if (!tp) return

  const coords = extractCoords(tp)
  if (!coords) {
    errorMsg.value = tr("errors.default", "Missing coordinates for this place.")
    return
  }

  actionBusy.value = true
  try {
    await api.patch(`/trips/${tripId.value}/start-location`, {
      start_latitude: coords.lat,
      start_longitude: coords.lon,
    })

    if (trip.value) {
      trip.value = {
        ...trip.value,
        start_latitude: coords.lat,
        start_longitude: coords.lon,
      }
    }
  } catch (e) {
    errorMsg.value = getErrMessage(e)
  } finally {
    actionBusy.value = false
  }
}
</script>

<template>
  <div class="w-full">
    <div v-if="loading" class="max-w-6xl mx-auto px-4 py-10">
      <div class="animate-pulse space-y-6">
        <div class="h-10 w-2/3 bg-gray-200 rounded-lg"></div>
        <div class="h-5 w-1/2 bg-gray-200 rounded-lg"></div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div v-for="i in 4" :key="i" class="h-24 bg-gray-200 rounded-xl"></div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
          <div class="lg:col-span-7 h-72 bg-gray-200 rounded-xl"></div>
          <div class="lg:col-span-5 h-[520px] bg-gray-200 rounded-xl"></div>
        </div>
      </div>
    </div>

    <div v-else-if="errorMsg" class="max-w-6xl mx-auto px-4 py-10">
      <div class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-xl max-w-xl">
        {{ errorMsg }}
      </div>
    </div>

    <div v-else-if="trip">
      <TripHeaderBar :trip="trip" :stats="stats" :banner-image="bannerImage" :format-date="formatDate" />

      <div class="max-w-6xl mx-auto px-4 mt-6 pb-14">
        <TripTabs :modelValue="activeTab" class="mb-5" @update:modelValue="setTab">
          <template #places>{{ t("trip.tabs.places") }}</template>
          <template #plan>{{ t("trip.tabs.plan") }}</template>
          <template #team>{{ t("trip.tabs.team") }}</template>
          <template #preferences>{{ t("trip.tabs.preferences") }}</template>
        </TripTabs>


        <KeepAlive>
          <PlacesWorkspace
              v-if="activeTab === 'places'"
              :key="route.params.id"
              :trip="trip"
              :places="places"
              :places-loading="placesLoading"
              :categories="categories"
              :filtered-places="filteredPlaces"
              v-model:placeQuery="placeQuery"
              v-model:categoryFilter="categoryFilter"
              v-model:sortKey="sortKey"
              :selected-trip-place-id="selectedTripPlaceId"
              :placeholder="t('trip.places.search')"
              :labels="{
              title: t('trip.view.places'),
              addBtn: t('trip.view.add_place'),
              refreshBtn: t('actions.refresh'),
              openLabel: t('trip.view.open'),
              mapTitle: t('trip.view.map')
            }"
              @select-place="onSelectPlace"
              @refresh-places="refreshPlaces"
              @open-add-place="openPlaceSearch"
          >
            <template #categoryLabel="{ c }">
              {{ c === 'all' ? t('trip.places.category_all') : c }}
            </template>

            <template #sortNameAsc>{{ t("trip.places.sort_name_asc") }}</template>
            <template #sortNameDesc>{{ t("trip.places.sort_name_desc") }}</template>
            <template #sortCatAsc>{{ t("trip.places.sort_cat_asc") }}</template>
            <template #sortCatDesc>{{ t("trip.places.sort_cat_desc") }}</template>

            <template #mapCount="{ count }">
              {{ t("trip.stats.places") }}:
              <span class="font-semibold text-gray-900">{{ count }}</span>
            </template>
          </PlacesWorkspace>
        </KeepAlive>

        <TripPlanPanel
            v-if="activeTab === 'plan'"
            :trip-id="tripId"
            :trip="trip"
            :places="places"
            :places-loading="placesLoading"
            @error="errorMsg = $event"
            @picked="onAddSuggestedPlace"
        />

        <TripMembersPanel
            v-if="activeTab === 'team'"
            :trip="trip"
            :members="members"
            :loading="membersLoading"
            @members-changed="refreshMembers"
            @error="errorMsg = $event"
        />

        <TripPreferencesPanel
            v-if="activeTab === 'preferences'"
            :excluded-slugs="['hotel', 'airport', 'station', 'other']"
            @error="errorMsg = $event"
        />

        <PlaceSearchModal
            v-model="placeSearchOpen"
            :trip-id="route.params.id"
            @picked="onAddPlace"
        />

        <PlaceDetailsDrawer
            v-model="placeModalOpen"
            :trip-place="selectedTripPlace"
            :busy="actionBusy"
            :rating="selectedRating"
            :is-start="selectedIsStart"
            @rate="doRate"
            @toggle-fixed="doToggleFixed"
            @remove="doRemove"
            @set-start="doSetStart"
        />
      </div>
    </div>
  </div>
</template>
