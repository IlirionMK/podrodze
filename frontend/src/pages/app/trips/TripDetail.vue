<script setup>
import { ref, computed, onMounted, watch } from "vue"
import { useRoute, useRouter } from "vue-router"
import { useI18n } from "vue-i18n"

import { useAuth } from "@/composables/useAuth.js"

import { fetchTrip } from "@/composables/api/trips.js"
import { fetchTripPlaces, voteTripPlace, updateTripPlace, deleteTripPlace } from "@/composables/api/tripPlaces.js"
import { fetchTripMembers, inviteTripMember } from "@/composables/api/tripMembers.js"
import { fetchPreferences, updateMyPreferences } from "@/composables/api/preferences.js"

import TripHeaderBar from "@/components/trips/TripHeaderBar.vue"
import TripTabs from "@/components/trips/TripTabs.vue"
import PlacesWorkspace from "@/components/trips/panels/PlacesWorkspace.vue"
import PlaceSearchModal from "@/components/trips/PlaceSearchModal.vue"
import PlaceDetailsDrawer from "@/components/trips/PlaceDetailsDrawer.vue"

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()

const { user: authUser } = useAuth()

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

const drawerOpen = ref(false)
const actionBusy = ref(false)

const inviteOpen = ref(false)
const inviteEmail = ref("")
const inviteRole = ref("member")
const inviteBusy = ref(false)

// ---- Preferences (backend-driven: categories + user scores 0..2) ----
const prefCategories = ref([]) // [{slug,name}]
const prefScores = ref({}) // { [slug]: 0|1|2 }

const prefsLoading = ref(false)
const prefsSaving = ref(false)
const prefsLoadedOnce = ref(false)
const prefsQuery = ref("")

const bannerImage =
    "https://images.unsplash.com/photo-1528909514045-2fa4ac7a08ba?auto=format&fit=crop&w=1600&q=80"

function initTabFromRoute() {
  const qTab = String(route.query.tab || "")
  const allowed = new Set(["overview", "places", "plan", "team", "preferences"])
  activeTab.value = allowed.has(qTab) ? qTab : "overview"
}

function setTab(tab) {
  activeTab.value = tab
  router.replace({ query: { ...route.query, tab } })
}

function openPlaceSearch() {
  placeSearchOpen.value = true
}

function handlePickedPlace(payload) {
  console.log("picked place:", payload)
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

async function refreshPlaces() {
  placesLoading.value = true
  try {
    const res = await fetchTripPlaces(route.params.id)
    places.value = res.data.data || []
  } finally {
    placesLoading.value = false
  }
}

async function refreshMembers() {
  membersLoading.value = true
  try {
    const res = await fetchTripMembers(route.params.id)
    members.value = res.data.data || []
  } finally {
    membersLoading.value = false
  }
}

async function loadData() {
  loading.value = true
  errorMsg.value = ""

  try {
    const tripRes = await fetchTrip(route.params.id)
    trip.value = tripRes.data.data

    await Promise.all([refreshMembers(), refreshPlaces()])
  } catch (err) {
    errorMsg.value = err.response?.data?.message || t("errors.default")
  } finally {
    loading.value = false
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

const myMember = computed(() => {
  const uid = authUser.value?.id
  if (!uid) return null
  return members.value.find((m) => m.id === uid) || null
})

const canManageMembers = computed(() => {
  const uid = authUser.value?.id
  if (!uid || !trip.value) return false
  if (trip.value.owner_id === uid) return true
  return myMember.value?.role === "editor" && myMember.value?.status === "accepted"
})

function openInvite() {
  if (!canManageMembers.value) return
  inviteOpen.value = true
}

function closeInvite() {
  inviteOpen.value = false
  inviteEmail.value = ""
  inviteRole.value = "member"
}

async function submitInvite() {
  const email = inviteEmail.value.trim()
  if (!email) return

  inviteBusy.value = true
  errorMsg.value = ""

  try {
    await inviteTripMember(route.params.id, {
      email,
      role: inviteRole.value,
    })

    await refreshMembers()
    closeInvite()
  } catch (e) {
    errorMsg.value = e?.response?.data?.error || e?.response?.data?.message || t("errors.default")
  } finally {
    inviteBusy.value = false
  }
}

// ---- Preferences helpers ----
function clampScore(v) {
  const n = Number.parseInt(v, 10)
  if (Number.isNaN(n)) return 0
  return Math.max(0, Math.min(2, n))
}

function normalizePreferenceResponse(payload) {
  const root = payload?.data ?? payload ?? {}
  const categories = Array.isArray(root.categories) ? root.categories : []
  const user = root.user && typeof root.user === "object" ? root.user : {}
  return { categories, user }
}

async function loadPreferences() {
  if (prefsLoadedOnce.value) return
  prefsLoading.value = true
  errorMsg.value = ""

  try {
    const res = await fetchPreferences()
    const { categories, user } = normalizePreferenceResponse(res?.data)

    prefCategories.value = categories

    // backend update() expects each allowed slug to exist, so send full set
    const nextScores = {}
    for (const c of categories) {
      const slug = c?.slug
      if (!slug) continue
      nextScores[slug] = clampScore(user?.[slug] ?? 0)
    }

    prefScores.value = nextScores
    prefsLoadedOnce.value = true
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || t("errors.default")
  } finally {
    prefsLoading.value = false
  }
}

function setPrefScore(slug, score) {
  if (!slug) return
  prefScores.value = {
    ...prefScores.value,
    [slug]: clampScore(score),
  }
}

const filteredPrefCategories = computed(() => {
  const list = Array.isArray(prefCategories.value) ? prefCategories.value : []
  const q = prefsQuery.value.trim().toLowerCase()
  if (!q) return list

  return list.filter((c) => {
    const name = String(c?.name ?? "").toLowerCase()
    const slug = String(c?.slug ?? "").toLowerCase()
    return name.includes(q) || slug.includes(q)
  })
})

async function savePreferences() {
  prefsSaving.value = true
  errorMsg.value = ""

  try {
    await updateMyPreferences(prefScores.value)
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || e?.response?.data?.error || t("errors.default")
  } finally {
    prefsSaving.value = false
  }
}

// Load prefs when opening tab
watch(
    () => activeTab.value,
    async (tab) => {
      if (tab === "preferences") {
        await loadPreferences()
      }
    }
)

watch(
    () => route.params.id,
    async () => {
      initTabFromRoute()
      await loadData()

      prefsLoadedOnce.value = false
      prefCategories.value = []
      prefScores.value = {}
      prefsQuery.value = ""
    }
)

watch(
    () => route.query.tab,
    () => initTabFromRoute()
)

watch(
    () => selectedTripPlaceId.value,
    (v) => {
      drawerOpen.value = Boolean(v)
    }
)

watch(
    () => drawerOpen.value,
    (v) => {
      if (!v) selectedTripPlaceId.value = null
    }
)

async function doVote() {
  if (!selectedBackendId.value) return
  actionBusy.value = true
  try {
    await voteTripPlace(route.params.id, selectedBackendId.value)
    await refreshPlaces()
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || t("errors.default")
  } finally {
    actionBusy.value = false
  }
}

async function doToggleFixed() {
  if (!selectedBackendId.value) return
  actionBusy.value = true
  try {
    await updateTripPlace(route.params.id, selectedBackendId.value, {
      is_fixed: !selectedIsFixed.value,
    })
    await refreshPlaces()
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || t("errors.default")
  } finally {
    actionBusy.value = false
  }
}

async function doRemove() {
  if (!selectedBackendId.value) return
  actionBusy.value = true
  try {
    await deleteTripPlace(route.params.id, selectedBackendId.value)
    drawerOpen.value = false
    selectedTripPlaceId.value = null
    await refreshPlaces()
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || t("errors.default")
  } finally {
    actionBusy.value = false
  }
}

onMounted(async () => {
  initTabFromRoute()
  await loadData()
})
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
      <TripHeaderBar
          :trip="trip"
          :stats="stats"
          :banner-image="bannerImage"
          :format-date="formatDate"
          @add-place="openPlaceSearch"
          @invite-member="setTab('team')"
          @open-preferences="setTab('preferences')"
      />

      <div class="max-w-6xl mx-auto px-4 -mt-10 relative z-10 pb-14">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-white p-5 rounded-xl shadow border text-center">
            <div class="text-gray-600 text-sm">{{ t("trip.stats.places") }}</div>
            <div class="text-2xl font-semibold">{{ stats.places }}</div>
          </div>

          <div class="bg-white p-5 rounded-xl shadow border text-center">
            <div class="text-gray-600 text-sm">{{ t("trip.stats.activities") }}</div>
            <div class="text-2xl font-semibold">{{ stats.activities }}</div>
          </div>

          <div class="bg-white p-5 rounded-xl shadow border text-center">
            <div class="text-gray-600 text-sm">{{ t("trip.stats.members") }}</div>
            <div class="text-2xl font-semibold">{{ stats.members }}</div>
          </div>

          <div class="bg-white p-5 rounded-xl shadow border text-center">
            <div class="text-gray-600 text-sm">{{ t("trip.stats.other") }}</div>
            <div class="text-2xl font-semibold">—</div>
          </div>
        </div>

        <TripTabs :modelValue="activeTab" class="mb-6" @update:modelValue="setTab">
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
                class="px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition"
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
              :place-query="placeQuery"
              :category-filter="categoryFilter"
              :sort-key="sortKey"
              :selected-trip-place-id="selectedTripPlaceId"
              :placeholder="t('trip.places.search')"
              @update:placeQuery="placeQuery = $event"
              @update:categoryFilter="categoryFilter = $event"
              @update:sortKey="sortKey = $event"
              @select-place="selectedTripPlaceId = $event; drawerOpen = true"
              @refresh-places="refreshPlaces"
              @open-add-place="openPlaceSearch"
              @clear-selection="selectedTripPlaceId = null"
          >
            <template #title>{{ t("trip.view.places") }}</template>
            <template #addBtn>{{ t("trip.view.add_place") }}</template>
            <template #refreshBtn>{{ t("actions.refresh") }}</template>
            <template #openLabel>{{ t("trip.view.open") }}</template>

            <template #categoryLabel="{ c }">
              {{ c === "all" ? t("trip.places.category_all") : c }}
            </template>

            <template #sortNameAsc>{{ t("trip.places.sort_name_asc") }}</template>
            <template #sortNameDesc>{{ t("trip.places.sort_name_desc") }}</template>
            <template #sortCatAsc>{{ t("trip.places.sort_cat_asc") }}</template>
            <template #sortCatDesc>{{ t("trip.places.sort_cat_desc") }}</template>

            <template #loading>{{ t("loading") }}…</template>
            <template #emptyTitle>{{ t("trip.places.empty_title") }}</template>
            <template #emptyHint>{{ t("trip.places.empty_hint") }}</template>

            <template #mapTitle>{{ t("trip.view.map") }}</template>
            <template #clearSelection>{{ t("trip.map.clear_selection") }}</template>
            <template #mapCount="{ count }">
              {{ t("trip.stats.places") }}:
              <span class="font-semibold text-gray-900">{{ count }}</span>
            </template>
          </PlacesWorkspace>
        </KeepAlive>

        <section v-if="activeTab === 'plan'" class="bg-white p-6 rounded-2xl border shadow-sm">
          <h2 class="text-xl font-semibold mb-2">{{ t("trip.tabs.plan") }}</h2>
          <div class="p-6 rounded-xl border bg-gray-50 text-gray-700">
            <div class="font-semibold mb-1">{{ t("trip.plan.coming_title") }}</div>
            <div class="text-sm text-gray-600">{{ t("trip.plan.coming_hint") }}</div>
          </div>
        </section>

        <section v-if="activeTab === 'team'" class="bg-white p-6 rounded-2xl border shadow-sm">
          <div class="flex items-center justify-between gap-4 mb-4">
            <h2 class="text-xl font-semibold">{{ t("trip.view.members") }}</h2>

            <div class="flex items-center gap-3">
              <div v-if="membersLoading" class="text-sm text-gray-500">
                {{ t("loading") }}…
              </div>

              <button
                  v-if="canManageMembers"
                  type="button"
                  class="px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition"
                  @click="openInvite"
              >
                {{ t("trip.view.add_member") }}
              </button>
            </div>
          </div>

          <div v-if="members.length === 0" class="p-6 rounded-xl border bg-gray-50 text-gray-700">
            <div class="font-semibold mb-1">{{ t("trip.team.empty_title") }}</div>
            <div class="text-sm text-gray-600">{{ t("trip.team.empty_hint") }}</div>
          </div>

          <div v-else class="flex flex-wrap gap-3">
            <div v-for="m in members" :key="m.id" class="px-4 py-2 rounded-xl bg-gray-100 border">
              <div class="font-medium">{{ m.name }}</div>
              <div class="text-xs text-gray-600">{{ m.role }} • {{ m.status }}</div>
            </div>
          </div>
        </section>

        <section v-if="activeTab === 'preferences'" class="bg-white p-6 rounded-2xl border shadow-sm">
          <div class="flex items-center justify-between gap-4 mb-4">
            <div>
              <h2 class="text-xl font-semibold">{{ t("trip.tabs.preferences") }}</h2>
              <div class="text-sm text-gray-600">{{ t("trip.preferences.hint") }}</div>
            </div>

            <button
                type="button"
                class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition disabled:opacity-60"
                @click="savePreferences"
                :disabled="prefsLoading || prefsSaving"
            >
              {{ prefsSaving ? t("loading") : t("actions.save") }}
            </button>
          </div>

          <div class="mb-4">
            <input
                v-model="prefsQuery"
                type="text"
                class="w-full px-4 py-2.5 rounded-xl border bg-white focus:outline-none focus:ring-2 focus:ring-black/10"
                :placeholder="t('actions.search')"
                :disabled="prefsLoading || prefsSaving"
            />
          </div>

          <div v-if="prefsLoading" class="p-6 rounded-xl border bg-gray-50 text-gray-700">
            {{ t("loading") }}…
          </div>

          <div v-else-if="filteredPrefCategories.length === 0" class="p-6 rounded-xl border bg-gray-50 text-gray-700">
            <div class="font-semibold mb-1">{{ t("trip.preferences.empty_title") }}</div>
            <div class="text-sm text-gray-600">{{ t("trip.preferences.empty_hint") }}</div>
          </div>

          <div v-else class="space-y-3">
            <div
                v-for="c in filteredPrefCategories"
                :key="c.slug"
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 rounded-2xl border"
            >
              <div>
                <div class="font-medium">{{ c.name }}</div>
                <div class="text-xs text-gray-600">{{ c.slug }}</div>
              </div>

              <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="px-3 py-2 rounded-xl border transition"
                    :class="prefScores[c.slug] === 0 ? 'bg-blue-50 border-blue-200' : 'bg-white hover:bg-gray-50'"
                    @click="setPrefScore(c.slug, 0)"
                    :disabled="prefsSaving"
                >
                  {{ t("trip.preferences.score_0") }}
                </button>

                <button
                    type="button"
                    class="px-3 py-2 rounded-xl border transition"
                    :class="prefScores[c.slug] === 1 ? 'bg-blue-50 border-blue-200' : 'bg-white hover:bg-gray-50'"
                    @click="setPrefScore(c.slug, 1)"
                    :disabled="prefsSaving"
                >
                  {{ t("trip.preferences.score_1") }}
                </button>

                <button
                    type="button"
                    class="px-3 py-2 rounded-xl border transition"
                    :class="prefScores[c.slug] === 2 ? 'bg-blue-50 border-blue-200' : 'bg-white hover:bg-gray-50'"
                    @click="setPrefScore(c.slug, 2)"
                    :disabled="prefsSaving"
                >
                  {{ t("trip.preferences.score_2") }}
                </button>
              </div>
            </div>
          </div>
        </section>

        <PlaceSearchModal v-model="placeSearchOpen" :trip-id="route.params.id" @picked="handlePickedPlace" />

        <PlaceDetailsDrawer
            v-model="drawerOpen"
            :trip-place="selectedTripPlace"
            :busy="actionBusy"
            @vote="doVote"
            @toggle-fixed="doToggleFixed"
            @remove="doRemove"
        />

        <div v-if="inviteOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4">
          <div class="absolute inset-0 bg-black/40" @click="closeInvite"></div>

          <div class="relative w-full max-w-lg bg-white rounded-2xl border shadow-xl p-6">
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-lg font-semibold">{{ t("trip.view.add_member") }}</h3>

              <button
                  type="button"
                  class="px-3 py-1.5 rounded-xl border bg-white hover:bg-gray-50"
                  @click="closeInvite"
                  :disabled="inviteBusy"
              >
                {{ t("actions.cancel") }}
              </button>
            </div>

            <div class="mt-4 space-y-3">
              <div>
                <label class="text-sm text-gray-600">{{ t("common.email", "Email") }}</label>
                <input
                    v-model="inviteEmail"
                    type="email"
                    class="w-full mt-1 px-4 py-2.5 rounded-xl border bg-white focus:outline-none focus:ring-2 focus:ring-black/10"
                    placeholder="friend@example.com"
                    :disabled="inviteBusy"
                />
              </div>

              <div>
                <label class="text-sm text-gray-600">{{ t("common.role", "Role") }}</label>
                <select
                    v-model="inviteRole"
                    class="w-full mt-1 px-4 py-2.5 rounded-xl border bg-white focus:outline-none focus:ring-2 focus:ring-black/10"
                    :disabled="inviteBusy"
                >
                  <option value="member">{{ t("roles.member", "member") }}</option>
                  <option value="editor">{{ t("roles.editor", "editor") }}</option>
                </select>
              </div>

              <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl border bg-white hover:bg-gray-50 transition"
                    @click="closeInvite"
                    :disabled="inviteBusy"
                >
                  {{ t("actions.cancel") }}
                </button>

                <button
                    type="button"
                    class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition"
                    @click="submitInvite"
                    :disabled="inviteBusy || !inviteEmail.trim()"
                >
                  {{ inviteBusy ? t("loading") : t("actions.add") }}
                </button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
