<script setup>
import { ref, computed, watch } from "vue"
import { useRoute, useRouter } from "vue-router"
import { useI18n } from "vue-i18n"
import { X, Vote, Pin, Trash2 } from "lucide-vue-next"

import { fetchTrip } from "@/composables/api/trips.js"
import {
  fetchTripPlaces,
  createTripPlace,
  voteTripPlace,
  updateTripPlace,
  deleteTripPlace
} from "@/composables/api/tripPlaces.js"
import { fetchTripMembers } from "@/composables/api/tripMembers.js"

import TripHeaderBar from "@/components/trips/TripHeaderBar.vue"
import TripTabs from "@/components/trips/TripTabs.vue"
import PlacesWorkspace from "@/components/trips/panels/PlacesWorkspace.vue"
import TripMembersPanel from "@/components/trips/panels/TripMembersPanel.vue"
import TripPreferencesPanel from "@/components/trips/panels/TripPreferencesPanel.vue"
import TripPlanPanel from "@/components/trips/panels/TripPlanPanel.vue"
import PlaceSearchModal from "@/components/trips/PlaceSearchModal.vue"

const route = useRoute()
const router = useRouter()
const { t, te, locale } = useI18n()

function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

function getErrMessage(err) {
  return err?.response?.data?.message || tr("errors.default", "Something went wrong.")
}

const tripId = computed(() => String(route.params.id || ""))

const trip = ref(null)
const members = ref([])
const places = ref([])

const loading = ref(true)
const membersLoading = ref(false)
const placesLoading = ref(false)

const errorMsg = ref("")
const activeTab = ref("overview")

const placeQuery = ref("")
const categoryFilter = ref("all")
const sortKey = ref("name_asc")

const selectedTripPlaceId = ref(null)
const placeSearchOpen = ref(false)

const placeModalOpen = ref(false)
const actionBusy = ref(false)

const bannerImage =
    "https://images.unsplash.com/photo-1528909514045-2fa4ac7a08ba?auto=format&fit=crop&w=1600&q=80"

const btnBase =
    "inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
const btnSecondary = btnBase + " border border-gray-200 bg-white text-gray-900 hover:bg-gray-50"
const btnDanger = btnBase + " border border-red-200 bg-red-50 text-red-700 hover:bg-red-100"

const allowedTabs = new Set(["overview", "places", "plan", "team", "preferences"])

function initTabFromRoute() {
  const qTab = String(route.query.tab || "")
  activeTab.value = allowedTabs.has(qTab) ? qTab : "overview"
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
      list.sort((a, b) => (b?.place?.category_slug || "").localeCompare(a?.place?.category_slug || ""))
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

const selectedBackendId = computed(() => {
  const tp = selectedTripPlace.value
  if (!tp) return null
  return tp?.place?.id ?? tp?.id ?? null
})

const selectedIsFixed = computed(() => {
  const tp = selectedTripPlace.value
  if (!tp) return false
  return Boolean(tp.is_fixed ?? tp.fixed ?? tp.is_mandatory ?? false)
})

function closePlaceModal() {
  placeModalOpen.value = false
}

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

    if (activeTab.value !== 'places') {
      setTab('places')
    }

    await refreshPlaces()
  } catch (e) {
    errorMsg.value = getErrMessage(e)
  } finally {
    placesLoading.value = false
  }
}

async function doVote() {
  if (!selectedBackendId.value) return
  actionBusy.value = true
  try {
    await voteTripPlace(tripId.value, selectedBackendId.value)
    await refreshPlaces()
  } catch (e) {
    errorMsg.value = getErrMessage(e)
  } finally {
    actionBusy.value = false
  }
}

async function doToggleFixed() {
  if (!selectedBackendId.value) return
  actionBusy.value = true
  try {
    await updateTripPlace(tripId.value, selectedBackendId.value, { is_fixed: !selectedIsFixed.value })
    await refreshPlaces()
  } catch (e) {
    errorMsg.value = getErrMessage(e)
  } finally {
    actionBusy.value = false
  }
}

async function doRemove() {
  if (!selectedBackendId.value) return
  actionBusy.value = true
  try {
    await deleteTripPlace(tripId.value, selectedBackendId.value)
    closePlaceModal()
    selectedTripPlaceId.value = null
    await refreshPlaces()
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
          <template #overview>{{ t("trip.tabs.overview") }}</template>
          <template #places>{{ t("trip.tabs.places") }}</template>
          <template #plan>{{ t("trip.tabs.plan") }}</template>
          <template #team>{{ t("trip.tabs.team") }}</template>
          <template #preferences>{{ t("trip.tabs.preferences") }}</template>
        </TripTabs>

        <section v-if="activeTab === 'overview'" class="bg-white p-6 rounded-2xl border shadow-sm">
          <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold">{{ t("trip.about") }}</h2>
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-blue-600 to-purple-600 text-white hover:opacity-90 active:opacity-80 shadow"
                @click="setTab('places'); openPlaceSearch()"
            >
              {{ t("trip.view.add_place") }}
            </button>
          </div>

          <p class="text-gray-700 leading-relaxed mt-4">
            {{ trip.description || t("trip.no_description") }}
          </p>
        </section>

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
            <div v-if="placeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
              <button class="absolute inset-0 bg-black/50" @click="closePlaceModal" aria-label="Close" />

              <div class="relative w-full max-w-lg rounded-2xl border border-gray-200 bg-white shadow-2xl overflow-hidden">
                <div class="p-6">
                  <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                      <h3 class="text-xl font-semibold text-gray-900 truncate">
                        {{
                          selectedTripPlace?.place?.name ||
                          selectedTripPlace?.name ||
                          tr("trip.place.modal.title", "Place")
                        }}
                      </h3>

                      <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs text-gray-700">
                          {{ tr("trip.place.modal.category", "Category") }}:
                          <span class="ml-1 font-semibold">
                            {{ selectedTripPlace?.place?.category_slug || "—" }}
                          </span>
                        </span>

                        <span
                            v-if="selectedIsFixed"
                            class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs text-blue-700"
                        >
                          <Pin class="h-3.5 w-3.5 mr-1" />
                          {{ tr("trip.place.modal.fixed", "Fixed") }}
                        </span>
                      </div>
                    </div>

                    <button
                        type="button"
                        class="h-10 w-10 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 flex items-center justify-center"
                        @click="closePlaceModal"
                    >
                      <X class="h-4 w-4" />
                    </button>
                  </div>

                  <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <button
                        type="button"
                        :class="btnSecondary + ' py-3'"
                        :disabled="actionBusy || !selectedBackendId"
                        @click="doVote"
                    >
                      <Vote class="h-4 w-4" />
                      {{ tr("trip.place.actions.vote", "Vote") }}
                    </button>

                    <button
                        type="button"
                        :class="btnSecondary + ' py-3'"
                        :disabled="actionBusy || !selectedBackendId"
                        @click="doToggleFixed"
                    >
                      <Pin class="h-4 w-4" />
                      {{ selectedIsFixed ? tr("trip.place.actions.unfix", "Unfix") : tr("trip.place.actions.fix", "Fix") }}
                    </button>

                    <button
                        type="button"
                        :class="btnDanger + ' py-3'"
                        :disabled="actionBusy || !selectedBackendId"
                        @click="doRemove"
                    >
                      <Trash2 class="h-4 w-4" />
                      {{ tr("actions.remove", "Remove") }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </Transition>
        </Teleport>
      </div>
    </div>
  </div>
</template>