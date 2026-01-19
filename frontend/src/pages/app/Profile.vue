<script setup>
import { ref, computed, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import {
  Mail,
  CalendarDays,
  Pencil,
  Save,
  X,
  LogOut,
  RefreshCw,
  KeyRound,
  Eye,
  EyeOff,
  Check,
  Ban,
  MailOpen,
  MapPin,
  Route,
  Trash2,
  UserX,
  User,
  Calendar,
} from "lucide-vue-next"

import api from "@/composables/api/api.js"
import { useAuth } from "@/composables/useAuth"

const { t, te } = useI18n()
function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

const { token, user } = useAuth()

const loading = ref(false)
const errorMessage = ref("")
const successMessage = ref("")

// Current tab
const activeTab = ref("trip")

// Profile editing
const editOpen = ref(false)
const editName = ref("")
const editEmail = ref("")


const passOpen = ref(false)
const passBusy = ref(false)
const passError = ref("")
const currentPassword = ref("")
const newPassword = ref("")
const newPassword2 = ref("")
const showCurrent = ref(false)
const showNew = ref(false)
const showNew2 = ref(false)

// Invitations
const invitesLoading = ref(false)
const invitesError = ref("")
const invitations = ref([])
const inviteBusyId = ref(null)

// Current trip
const currentTripLoading = ref(false)
const currentTrip = ref(null)

// My trips
const tripsLoading = ref(false)
const myTrips = ref([])
const tripBusyId = ref(null)

// Delete account
const deleteAccountOpen = ref(false)
const deleteAccountBusy = ref(false)
const deleteAccountError = ref("")

// --- ZAKTUALIZOWANE ENDPOINTY (Zgodnie z plikiem PHP) ---
const PASS_ENDPOINT = `/user/password` 
const PASS_METHOD = "put"
const PROFILE_ENDPOINT = `/user/profile`
const PROFILE_METHOD = "put" 
const INVITES_LIST = `/users/me/invites`
const INVITES_ACCEPT = (tripId) => `/trips/${tripId}/accept`
const INVITES_DECLINE = (tripId) => `/trips/${tripId}/decline`
const CURRENT_TRIP = `/user/current-trip` 
const MY_TRIPS = `/trips`
const LEAVE_TRIP = (tripId) => `/trips/${tripId}/leave`
const DELETE_ACCOUNT = `/user` 
const LOGOUT_ENDPOINT = `/logout`

const btnBase =
    "inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
const btnPrimary =
    btnBase + " bg-gradient-to-r from-emerald-600 to-teal-600 text-white hover:opacity-90 active:opacity-80 shadow"
const btnSecondary = btnBase + " border border-gray-200 bg-white text-gray-900 hover:bg-gray-50"
const btnDanger = btnBase + " border border-red-200 bg-red-50 text-red-700 hover:bg-red-100"

function getErrMessage(err) {
  return err?.response?.data?.message || tr("errors.default", "Something went wrong.")
}

const initials = computed(() => {
  const name = user.value?.name || ""
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return "U"
  const a = parts[0]?.[0] || ""
  const b = parts.length > 1 ? parts[parts.length - 1]?.[0] : ""
  return (a + b).toUpperCase()
})

const createdAt = computed(() => {
  const v = user.value?.created_at || user.value?.createdAt
  if (!v) return null
  const d = new Date(v)
  if (Number.isNaN(d.getTime())) return null
  return d
})

function formatDate(d) {
  if (!d) return "—"
  try {
    return new Intl.DateTimeFormat(undefined, { year: "numeric", month: "short", day: "2-digit" }).format(d)
  } catch {
    return d.toISOString().slice(0, 10)
  }
}

function getRoleBadgeColor(role) {
  switch (role?.toLowerCase()) {
    case 'owner': return 'bg-emerald-100 text-emerald-700 border-emerald-200'
    case 'editor': return 'bg-teal-100 text-teal-700 border-teal-200'
    case 'member': return 'bg-gray-100 text-gray-700 border-gray-200'
    default: return 'bg-gray-100 text-gray-700 border-gray-200'
  }
}

function getRoleLabel(role) {
  switch (role?.toLowerCase()) {
    case 'owner': return tr("roles.owner", "Właściciel")
    case 'editor': return tr("roles.editor", "Edytor")
    case 'member': return tr("roles.member", "Członek")
    default: return role || "—"
  }
}

async function loadMe() {
  errorMessage.value = ""
  successMessage.value = ""

  if (!token.value) {
    errorMessage.value = tr("auth.no_token", "Brak tokenu — zaloguj się ponownie.")
    return
  }

  loading.value = true
  try {
    const res = await api.get(`/user`)
    user.value = res.data
  } catch (e) {
    errorMessage.value = getErrMessage(e)
  } finally {
    loading.value = false
  }
}

async function loadInvitations() {
  invitesError.value = ""
  invitesLoading.value = true
  try {
    const res = await api.get(INVITES_LIST)
    invitations.value = res.data?.data ?? res.data ?? []
  } catch (e) {
    invitesError.value = getErrMessage(e)
  } finally {
    invitesLoading.value = false
  }
}

async function loadCurrentTrip() {
  currentTripLoading.value = true
  try {
    const res = await api.get(CURRENT_TRIP)
    currentTrip.value = res.data?.data ?? res.data ?? null
  } catch (e) {
    currentTrip.value = null
  } finally {
    currentTripLoading.value = false
  }
}

async function loadMyTrips() {
  tripsLoading.value = true
  try {
    const res = await api.get(MY_TRIPS)
    myTrips.value = res.data?.data ?? res.data ?? []
  } catch (e) {
    errorMessage.value = getErrMessage(e)
  } finally {
    tripsLoading.value = false
  }
}

function openEdit() {
  editName.value = user.value?.name || ""
  editEmail.value = user.value?.email || ""
  editOpen.value = true
  successMessage.value = ""
  errorMessage.value = ""
}

function closeEdit() {
  editOpen.value = false
}

async function saveProfile() {
  const name = editName.value.trim()
  const email = editEmail.value.trim()
  if (!name || !email) return

  loading.value = true
  errorMessage.value = ""
  successMessage.value = ""

  try {
    // Laravel route używa PUT dla /user/profile
    const res = await api.put(PROFILE_ENDPOINT, { name, email })
    user.value = res.data?.data || res.data
    successMessage.value = tr("user.saved", "Profil został zaktualizowany.")
    closeEdit()
  } catch (e) {
    errorMessage.value = getErrMessage(e)
  } finally {
    loading.value = false
  }
}

function openPassword() {
  passOpen.value = true
  passError.value = ""
  currentPassword.value = ""
  newPassword.value = ""
  newPassword2.value = ""
  showCurrent.value = false
  showNew.value = false
  showNew2.value = false
}

function closePassword() {
  passOpen.value = false
}

async function changePassword() {
  passError.value = ""
  if (!currentPassword.value || !newPassword.value || !newPassword2.value) return
  if (newPassword.value !== newPassword2.value) {
    passError.value = tr("user.pass_mismatch", "Nowe hasła nie są zgodne.")
    return
  }
  if (newPassword.value.length < 8) {
    passError.value = tr("user.pass_too_short", "Hasło musi mieć minimum 8 znaków.")
    return
  }

  passBusy.value = true
  try {
    const payload = {
      current_password: currentPassword.value,
      password: newPassword.value,
      password_confirmation: newPassword2.value,
    }

    await api.put(PASS_ENDPOINT, payload)
    closePassword()
    successMessage.value = tr("user.pass_changed", "Hasło zostało zmienione.")
  } catch (e) {
    passError.value = getErrMessage(e)
  } finally {
    passBusy.value = false
  }
}

async function acceptInvite(inv) {
  // Ważne: W Laravelu ID w URL to ID wycieczki (trip)
  const tripId = inv?.trip_id ?? inv?.id
  if (!tripId) return
  inviteBusyId.value = tripId
  invitesError.value = ""
  try {
    await api.post(INVITES_ACCEPT(tripId))
    successMessage.value = tr("user.invitesaccepted", "Zaproszenie zostało zaakceptowane.")
    await refreshAll()
  } catch (e) {
    invitesError.value = getErrMessage(e)
  } finally {
    inviteBusyId.value = null
  }
}

async function declineInvite(inv) {
  const tripId = inv?.trip_id ?? inv?.id
  if (!tripId) return
  inviteBusyId.value = tripId
  invitesError.value = ""
  try {
    await api.post(INVITES_DECLINE(tripId))
    successMessage.value = tr("user.invitesdeclined", "Zaproszenie zostało odrzucone.")
    await loadInvitations()
  } catch (e) {
    invitesError.value = getErrMessage(e)
  } finally {
    inviteBusyId.value = null
  }
}

async function leaveTrip(trip) {
  const id = trip?.id
  if (!id) return
  
  if (!confirm(tr("user.leave_confirm", `Czy na pewno chcesz opuścić podróż "${trip.name}"?`))) {
    return
  }

  tripBusyId.value = id
  errorMessage.value = ""
  try {
    await api.post(LEAVE_TRIP(id))
    successMessage.value = tr("trips.left", "Opuściłeś podróż.")
    await loadMyTrips()
    await loadCurrentTrip()
  } catch (e) {
    errorMessage.value = getErrMessage(e)
  } finally {
    tripBusyId.value = null
  }
}

function openDeleteAccount() {
  deleteAccountOpen.value = true
  deleteAccountError.value = ""
}

function closeDeleteAccount() {
  deleteAccountOpen.value = false
}

async function deleteAccount() {
  deleteAccountError.value = ""
  deleteAccountBusy.value = true

  try {
    await api.delete(DELETE_ACCOUNT)
    logout()
  } catch (e) {
    deleteAccountError.value = getErrMessage(e)
  } finally {
    deleteAccountBusy.value = false
  }
}

async function logout() {
  try {
    await api.post(LOGOUT_ENDPOINT)
  } catch (e) {
    console.error("Logout failed", e)
  } finally {
    token.value = null
    user.value = null
    window.location.href = "/login"
  }
}

async function refreshAll() {
  await Promise.all([
    loadMe(),
    loadInvitations(),
    loadCurrentTrip(),
    loadMyTrips(),
  ])
}

const pendingInvites = computed(() => {
  const list = invitations.value || []
  return list.filter((x) => {
    const s = String(x?.status || "").toLowerCase()
    return !s || s === "pending"
  })
})

onMounted(() => {
  refreshAll()
})
</script>

<template>
  <div class="w-full">
    <div class="max-w-7xl mx-auto px-4 py-10">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4 mb-6">
        <div class="min-w-0">
          <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900">
            {{ tr("user.title", "Panel użytkownika") }}
          </h1>
          <div class="mt-1 text-sm text-gray-500">
            {{ tr("user.subtitle", "Zarządzaj swoim profilem i podróżami") }}
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button 
            type="button" 
            :class="btnSecondary" 
            @click="refreshAll" 
            :disabled="loading || invitesLoading || currentTripLoading || tripsLoading"
          >
            <RefreshCw class="h-4 w-4" />
            {{ tr("actions.refresh", "Odśwież") }}
          </button>
        </div>
      </div>

      <!-- Global Messages -->
      <div v-if="errorMessage" class="mb-4 p-4 rounded-xl border border-red-200 bg-red-50 text-red-700">
        {{ errorMessage }}
      </div>

      <div v-if="successMessage" class="mb-4 p-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700">
        {{ successMessage }}
      </div>

      <!-- Tabs -->
      <div class="mb-6">
        <div class="border-b border-gray-200">
          <nav class="flex gap-1 overflow-x-auto" aria-label="Tabs">
            <button
              @click="activeTab = 'trip'"
              :class="[
                'px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap',
                activeTab === 'trip'
                  ? 'border-emerald-600 text-emerald-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              {{ tr("tabs.current_trip", "Bieżąca podróż") }}
            </button>

            <button
              @click="activeTab = 'profile'"
              :class="[
                'px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap',
                activeTab === 'profile'
                  ? 'border-emerald-600 text-emerald-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              {{ tr("user.profile", "Profil") }}
            </button>

            <button
              @click="activeTab = 'invitations'"
              :class="[
                'px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap relative',
                activeTab === 'invitations'
                  ? 'border-emerald-600 text-emerald-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              {{ tr("tabs.invitations", "Zaproszenia") }}
              <span 
                v-if="pendingInvites.length > 0" 
                class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-red-500 text-white"
              >
                {{ pendingInvites.length }}
              </span>
            </button>

            <button
              @click="activeTab = 'trips'"
              :class="[
                'px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap',
                activeTab === 'trips'
                  ? 'border-emerald-600 text-emerald-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              {{ tr("tabs.my_trips", "Moje podróże") }}
            </button>
          </nav>
        </div>
      </div>

      <!-- Tab Content: Current Trip -->
      <div v-show="activeTab === 'trip'" class="space-y-6">
        <div v-if="currentTripLoading" class="text-center py-12">
          <div class="text-gray-500">{{ tr("loading", "Ładowanie...") }}</div>
        </div>

        <div v-else-if="!currentTrip" class="bg-white rounded-2xl border shadow-sm p-12">
          <div class="text-center">
            <MapPin class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">
              {{ tr("trip.stats.no_current_trip", "Brak bieżącej podróży") }}
            </h3>
            <p class="text-sm text-gray-500">
              {{ tr("trip.stats.no_current_hint", "Nie jesteś obecnie przypisany do żadnej podróży.") }}
            </p>
          </div>
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- User Card -->
          <div class="bg-white rounded-2xl border shadow-sm p-6">
            <div class="flex items-start justify-between mb-6">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white flex items-center justify-center shadow">
                  <span class="text-lg font-semibold">{{ initials }}</span>
                </div>
                <div>
                  <h3 class="font-semibold text-gray-900">{{ user?.name }}</h3>
                  <p class="text-sm text-gray-500">{{ user?.email }}</p>
                  <p v-if="createdAt" class="text-xs text-gray-400 mt-1">
                    {{ tr("user.joined", "Dołączono") }} {{ formatDate(createdAt) }}
                  </p>
                </div>
              </div>
            </div>

            <div class="space-y-3">
              <div>
                <label class="text-xs font-medium text-gray-500 uppercase">User ID</label>
                <div class="mt-1 px-3 py-2 border border-gray-200 rounded-md bg-gray-50">
                  <p class="text-sm text-gray-900">{{ user?.id ?? "—" }}</p>
                </div>
              </div>

              <div>
                <label class="text-xs font-medium text-gray-500 uppercase">
                  {{ tr("user.rola", "Rola") }}
                </label>
                <div class="mt-1">
                  <span 
                    :class="[
                      'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border',
                      getRoleBadgeColor(currentTrip?.user_role || currentTrip?.role)
                    ]"
                  >
                    {{ getRoleLabel(currentTrip?.user_role || currentTrip?.role) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Trip Details -->
          <div class="lg:col-span-2 bg-white rounded-2xl border shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
              {{ tr("trip.details", "Szczegóły podróży") }}
            </h2>

            <div class="space-y-6">
              <!-- Start Location -->
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                  <MapPin class="w-5 h-5 text-green-600" />
                </div>
                <div>
                  <h3 class="text-sm font-medium text-gray-700">
                    {{ tr("trip.start_location", "Miejsce startowe") }}
                  </h3>
                  <p class="text-base text-gray-900 mt-0.5">
                    {{ currentTrip?.start_location || currentTrip?.startLocation || "—" }}
                  </p>
                </div>
              </div>

              <!-- Trip Date -->
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                  <Calendar class="w-5 h-5 text-blue-600" />
                </div>
                <div>
                  <h3 class="text-sm font-medium text-gray-700">
                    {{ tr("trip.date", "Data podróży") }}
                  </h3>
                  <p class="text-base text-gray-900 mt-0.5">
                    {{ currentTrip?.trip_date || currentTrip?.start_date ? formatDate(new Date(currentTrip.trip_date || currentTrip.start_date)) : "—" }}
                  </p>
                </div>
              </div>

              <!-- Places -->
              <div v-if="currentTrip?.places && currentTrip.places.length > 0">
                <div class="flex items-center gap-3 mb-3">
                  <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0">
                    <MapPin class="w-5 h-5 text-purple-600" />
                  </div>
                  <h3 class="text-sm font-medium text-gray-700">
                    {{ tr("trip.places", "Dodane miejsca") }}
                  </h3>
                </div>

                <div class="ml-13 space-y-2">
                  <div
                    v-for="place in currentTrip.places"
                    :key="place.id"
                    class="p-3 border border-gray-200 rounded-lg hover:border-emerald-300 transition-colors"
                  >
                    <div class="flex items-center justify-between">
                      <div>
                        <p class="font-medium text-gray-900 text-sm">{{ place.name }}</p>
                        <p v-if="place.description" class="text-xs text-gray-500 mt-0.5">
                          {{ place.description }}
                        </p>
                      </div>
                      <span
                        v-if="place.status"
                        :class="[
                          'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border',
                          place.status === 'visited'
                            ? 'border-green-200 text-green-700 bg-green-50'
                            : 'border-orange-200 text-orange-700 bg-orange-50'
                        ]"
                      >
                        {{ place.status === 'visited' ? tr("trip.visited", "Odwiedzone") : tr("trip.planned", "Zaplanowane") }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Route -->
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                  <Route class="w-5 h-5 text-indigo-600" />
                </div>
                <div>
                  <h3 class="text-sm font-medium text-gray-700">
                    {{ tr("trip.route", "Trasa podróży") }}
                  </h3>
                  <div v-if="currentTrip?.route_created || currentTrip?.routeCreated" class="mt-1">
                    <p class="text-base text-gray-900">
                      {{ tr("trip.route_created", "Trasa została utworzona") }}
                    </p>
                    <p v-if="currentTrip?.route_distance || currentTrip?.routeDistance" class="text-sm text-gray-500 mt-0.5">
                      {{ tr("trip.distance", "Całkowity dystans") }}: {{ currentTrip.route_distance || currentTrip.routeDistance }}
                    </p>
                  </div>
                  <p v-else class="text-sm text-gray-500 mt-1">
                    {{ tr("trip.no_route", "Trasa nie została jeszcze utworzona") }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Content: Profile -->
      <div v-show="activeTab === 'profile'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
        <div class="lg:col-span-7">
          <section class="bg-white rounded-2xl border shadow-sm overflow-hidden">
            <div class="p-6 border-b">
              <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-4 min-w-0">
                  <div
                    class="h-14 w-14 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white flex items-center justify-center shadow"
                    :title="user?.name"
                  >
                    <span class="text-lg font-semibold">{{ initials }}</span>
                  </div>

                  <div class="min-w-0">
                    <div class="text-xl font-semibold text-gray-900 truncate">
                      {{ user?.name || "—" }}
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                      <span class="inline-flex items-center gap-1">
                        <Mail class="h-4 w-4" />
                        {{ user?.email || "—" }}
                      </span>

                      <span v-if="createdAt" class="inline-flex items-center gap-1">
                        <span class="text-gray-300">•</span>
                        <CalendarDays class="h-4 w-4" />
                        {{ tr("user.joined", "Dołączono") }} {{ formatDate(createdAt) }}
                      </span>
                    </div>
                  </div>
                </div>

                <button type="button" :class="btnSecondary" @click="openEdit" :disabled="loading || !user">
                  <Pencil class="h-4 w-4" />
                  {{ tr("admin", "Edytuj") }}
                </button>
              </div>
            </div>

            <div class="p-6">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 rounded-2xl border bg-gray-50">
                  <div class="text-xs text-gray-500">{{ tr("user.fields.id", "User ID") }}</div>
                  <div class="mt-1 font-semibold text-gray-900">{{ user?.id ?? "—" }}</div>
                </div>

                <div class="p-4 rounded-2xl border bg-gray-50">
                  <div class="text-xs text-gray-500">{{ tr("user.fields.email", "Email") }}</div>
                  <div class="mt-1 font-semibold text-gray-900">{{ user?.email ?? "—" }}</div>
                </div>
              </div>

              <div class="mt-6 flex flex-col sm:flex-row gap-2 sm:justify-end">
                <button type="button" :class="btnSecondary" @click="openPassword" :disabled="loading">
                  <KeyRound class="h-4 w-4" />
                  {{ tr("user.change_password", "Zmień hasło") }}
                </button>
              </div>
            </div>
          </section>

          <!-- Danger Zone -->
          <section class="mt-6 bg-white rounded-2xl border border-red-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold text-red-700 mb-4">
              {{ tr("profile.danger_zone", "Strefa niebezpieczna") }}
            </h2>
            <div class="flex items-center justify-between p-4 border border-red-200 rounded-lg bg-red-50">
              <div>
                <h3 class="font-medium text-gray-900">
                  {{ tr("user.delete_account", "Usuń konto") }}
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                  {{ tr("user.delete_hint", "Po usunięciu konta wszystkie Twoje dane zostaną trwale usunięte.") }}
                </p>
              </div>
              <button
                type="button"
                :class="btnDanger + ' flex-shrink-0 ml-4'"
                @click="openDeleteAccount"
              >
                <Trash2 class="h-4 w-4" />
                {{ tr("actions.remove", "Usuń") }}
              </button>
            </div>
          </section>
        </div>

        <div class="lg:col-span-5">
          <section class="bg-white p-6 rounded-2xl border shadow-sm">
            <div class="flex items-center justify-between gap-3 mb-4">
              <h2 class="text-xl font-semibold text-gray-900">
                {{ tr("user.invites", "Zaproszenia") }}
              </h2>

              <div class="text-sm text-gray-500">
                {{ pendingInvites.length }}
              </div>
            </div>

            <div v-if="invitesError" class="mb-4 p-3 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm">
              {{ invitesError }}
            </div>

            <div v-if="invitesLoading" class="text-sm text-gray-500">
              {{ tr("loading", "Ładowanie...") }}
            </div>

            <div v-else-if="pendingInvites.length === 0" class="p-4 rounded-xl border bg-gray-50 text-gray-700">
              <div class="font-semibold">{{ tr("profile.invites.empty_title", "Brak zaproszeń") }}</div>
              <div class="text-sm text-gray-600 mt-1">
                {{ tr("profile.invites.empty_hint", "Kiedy ktoś cię zaprosi do podróży, pojawi się to tutaj.") }}
              </div>
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="inv in pendingInvites"
                :key="inv.id || inv.invitation_id"
                class="rounded-2xl border border-gray-200 bg-white p-4 hover:shadow-md transition"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="flex items-center gap-2 min-w-0">
                      <MailOpen class="h-4 w-4 text-gray-700 shrink-0" />
                      <div class="font-semibold text-gray-900 truncate">
                        {{ inv?.trip?.name || inv?.trip_name || tr("profile.invites.trip", "Podróż") }}
                      </div>
                    </div>

                    <div class="mt-1 text-sm text-gray-600">
                      <span v-if="inv?.role">
                        {{ tr("common.role", "Rola") }}: 
                        <span class="font-medium">{{ getRoleLabel(inv.role) }}</span>
                      </span>
                      <span v-if="inv?.invited_by?.name || inv?.inviter_name" class="ml-2">
                        <span class="text-gray-300">•</span>
                        {{ tr("profile.invites.from", "Od") }}:
                        <span class="font-medium">{{ inv?.invited_by?.name || inv?.inviter_name }}</span>
                      </span>
                    </div>
                  </div>

                  <div class="flex items-center gap-2 shrink-0">
                    <button
                      type="button"
                      class="inline-flex items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium border border-gray-200 bg-white text-gray-900 hover:bg-gray-50 disabled:opacity-50"
                      :disabled="inviteBusyId === (inv.id || inv.invitation_id)"
                      @click="declineInvite(inv)"
                    >
                      <Ban class="h-4 w-4 text-gray-700" />
                      {{ tr("actions.decline", "Odrzuć") }}
                    </button>

                    <button
                      type="button"
                      class="inline-flex items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium bg-gradient-to-r from-emerald-600 to-teal-600 text-white hover:opacity-90 active:opacity-80 shadow disabled:opacity-50"
                      :disabled="inviteBusyId === (inv.id || inv.invitation_id)"
                      @click="acceptInvite(inv)"
                    >
                      <Check class="h-4 w-4" />
                      {{ tr("actions.accept", "Akceptuj") }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>

      <!-- Tab Content: Invitations -->
      <div v-show="activeTab === 'invitations'">
        <div v-if="invitesLoading" class="text-center py-12">
          <div class="text-gray-500">{{ tr("loading", "Ładowanie...") }}</div>
        </div>

        <div v-else-if="pendingInvites.length === 0" class="bg-white rounded-2xl border shadow-sm p-12">
          <div class="text-center">
            <MailOpen class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">
              {{ tr("profile.invites.empty_title", "Brak zaproszeń") }}
            </h3>
            <p class="text-sm text-gray-500">
              {{ tr("profile.invites.empty_hint", "Kiedy ktoś cię zaprosi do podróży, pojawi się to tutaj.") }}
            </p>
          </div>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="inv in pendingInvites"
            :key="inv.id || inv.invitation_id"
            class="bg-white rounded-2xl border shadow-sm p-6 hover:shadow-md transition"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                    {{ tr("profile.invites.new", "Nowe zaproszenie") }}
                  </span>
                  <span v-if="inv.created_at" class="text-xs text-gray-400">
                    {{ formatDate(new Date(inv.created_at)) }}
                  </span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-1">
                  {{ inv?.trip?.name || inv?.trip_name || tr("profile.invites.trip", "Podróż") }}
                </h3>
                <div class="space-y-1 text-sm text-gray-600">
                  <p v-if="inv?.invited_by?.name || inv?.inviter_name">
                    <User class="w-4 h-4 inline mr-2" />
                    {{ tr("profile.invites.from", "Zaproszony przez") }}: 
                    <span class="font-medium">{{ inv?.invited_by?.name || inv?.inviter_name }}</span>
                  </p>
                  <p v-if="inv?.trip?.start_location || inv?.start_location">
                    <MapPin class="w-4 h-4 inline mr-2" />
                    {{ tr("trip.start_location", "Miejsce startowe") }}: 
                    <span class="font-medium">{{ inv?.trip?.start_location || inv?.start_location }}</span>
                  </p>
                  <p class="flex items-center gap-2">
                    <span>{{ tr("common.role", "Rola") }}:</span>
                    <span 
                      :class="[
                        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border',
                        getRoleBadgeColor(inv.role)
                      ]"
                    >
                      {{ getRoleLabel(inv.role) }}
                    </span>
                  </p>
                </div>
              </div>
              <div class="flex flex-col gap-2 ml-4">
                <button
                  type="button"
                  :class="btnPrimary + ' bg-green-600 hover:bg-green-700'"
                  @click="acceptInvite(inv)"
                  :disabled="inviteBusyId === (inv.id || inv.invitation_id)"
                >
                  <Check class="h-4 w-4" />
                  {{ tr("actions.accept", "Zaakceptuj") }}
                </button>
                <button
                  type="button"
                  :class="btnSecondary"
                  @click="declineInvite(inv)"
                  :disabled="inviteBusyId === (inv.id || inv.invitation_id)"
                >
                  <X class="h-4 w-4" />
                  {{ tr("actions.decline", "Odrzuć") }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Content: My Trips -->
      <div v-show="activeTab === 'trips'">
        <div v-if="tripsLoading" class="text-center py-12">
          <div class="text-gray-500">{{ tr("loading", "Ładowanie...") }}</div>
        </div>

        <div v-else-if="myTrips.length === 0" class="bg-white rounded-2xl border shadow-sm p-12">
          <div class="text-center">
            <MapPin class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">
              {{ tr("trips.empty_title", "Brak podróży") }}
            </h3>
            <p class="text-sm text-gray-500">
              {{ tr("trips.empty_hint", "Nie należysz jeszcze do żadnej podróży.") }}
            </p>
          </div>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="trip in myTrips"
            :key="trip.id"
            class="bg-white rounded-2xl border shadow-sm p-6 hover:shadow-md transition"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                  <h3 class="text-lg font-semibold text-gray-900">{{ trip.name }}</h3>
                  <span 
                    :class="[
                      'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border',
                      getRoleBadgeColor(trip.user_role || trip.role)
                    ]"
                  >
                    {{ getRoleLabel(trip.user_role || trip.role) }}
                  </span>
                </div>
                <div class="space-y-1 text-sm text-gray-600">
                  <p v-if="trip.start_date || trip.startDate">
                    <Calendar class="w-4 h-4 inline mr-2" />
                    {{ tr("trip.start_date", "Data rozpoczęcia") }}: 
                    <span class="font-medium">{{ formatDate(new Date(trip.start_date || trip.startDate)) }}</span>
                  </p>
                  <p v-if="trip.members_count || trip.members">
                    <User class="w-4 h-4 inline mr-2" />
                    {{ tr("trip.members", "Liczba uczestników") }}: 
                    <span class="font-medium">{{ trip.members.length }}</span>
                  </p>
                </div>
              </div>
              <div class="flex flex-col gap-2 ml-4">
                <router-link
                :to="{ name: 'app.trips.show', params: { id: trip.id } }"
                :class="btnSecondary + ' flex items-center gap-1'"
                >
                  <MapPin class="h-4 w-4" />
                  {{ tr("actions.view_details", "Zobacz szczegóły") }}
                </router-link>                
                <button
                  v-if="(trip.user_role || trip.role) !== 'owner'"
                  type="button"
                  :class="btnDanger"
                  @click="leaveTrip(trip)"
                  :disabled="tripBusyId === trip.id"
                >
                  <UserX class="h-4 w-4" />
                  {{ tr("actions.leave", "Opuść podróż") }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Profile Modal -->
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
          <div v-if="editOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
            <button class="absolute inset-0 bg-black/60" @click="closeEdit" aria-label="Close" />

            <div class="relative w-full max-w-lg rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white overflow-hidden">
              <div class="p-6">
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0">
                    <h3 class="text-xl font-semibold drop-shadow">
                      {{ tr("profile.edit_title", "Edytuj profil") }}
                    </h3>
                    <div class="mt-1 text-sm text-white/70">
                      {{ tr("profile.edit_hint", "Zaktualizuj swoje imię i email.") }}
                    </div>
                  </div>

                  <button
                    type="button"
                    class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 hover:bg-white/15 transition flex items-center justify-center disabled:opacity-50"
                    @click="closeEdit"
                    :disabled="loading"
                    aria-label="Close"
                  >
                    <X class="h-4 w-4" />
                  </button>
                </div>

                <div class="mt-5 space-y-4">
                  <div>
                    <label class="block text-sm font-medium mb-1">
                      {{ tr("profile.fields.name", "Imię i nazwisko") }}
                    </label>
                    <input
                      v-model="editName"
                      type="text"
                      class="w-full h-11 px-4 rounded-xl border border-white/15 bg-white/10 text-white placeholder:text-white/40 outline-none focus:ring-2 focus:ring-white/20"
                      :disabled="loading"
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">
                      {{ tr("profile.fields.email", "Email") }}
                    </label>
                    <input
                      v-model="editEmail"
                      type="email"
                      class="w-full h-11 px-4 rounded-xl border border-white/15 bg-white/10 text-white placeholder:text-white/40 outline-none focus:ring-2 focus:ring-white/20"
                      :disabled="loading"
                    />
                  </div>

                  <div class="pt-2 flex flex-col sm:flex-row gap-2 sm:justify-end">
                    <button
                      type="button"
                      class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-white/10 border border-white/15 hover:bg-white/15 transition disabled:opacity-50"
                      @click="closeEdit"
                      :disabled="loading"
                    >
                      {{ tr("actions.cancel", "Anuluj") }}
                    </button>

                    <button
                      type="button"
                      class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-emerald-500 to-teal-600 hover:opacity-90 active:opacity-80 transition shadow-lg disabled:opacity-50"
                      @click="saveProfile"
                      :disabled="loading || !editName.trim() || !editEmail.trim()"
                    >
                      <Save class="h-4 w-4" />
                      {{ tr("actions.save", "Zapisz") }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>

      <!-- Change Password Modal -->
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
          <div v-if="passOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
            <button class="absolute inset-0 bg-black/60" @click="closePassword" aria-label="Close" />

            <div class="relative w-full max-w-lg rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white overflow-hidden">
              <div class="p-6">
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0">
                    <h3 class="text-xl font-semibold drop-shadow">
                      {{ tr("profile.password_title", "Zmiana hasła") }}
                    </h3>
                    <div class="mt-1 text-sm text-white/70">
                      {{ tr("profile.password_hint", "Wprowadź obecne hasło i wybierz nowe.") }}
                    </div>
                  </div>

                  <button
                    type="button"
                    class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 hover:bg-white/15 transition flex items-center justify-center disabled:opacity-50"
                    @click="closePassword"
                    :disabled="passBusy"
                    aria-label="Close"
                  >
                    <X class="h-4 w-4" />
                  </button>
                </div>

                <div class="mt-5 space-y-4">
                  <div v-if="passError" class="p-3 rounded-xl border border-red-400/25 bg-red-500/10 text-red-200 text-sm">
                    {{ passError }}
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">
                      {{ tr("profile.current_password", "Obecne hasło") }}
                    </label>
                    <div class="relative">
                      <input
                        v-model="currentPassword"
                        :type="showCurrent ? 'text' : 'password'"
                        class="w-full h-11 px-4 pr-11 rounded-xl border border-white/15 bg-white/10 text-white placeholder:text-white/40 outline-none focus:ring-2 focus:ring-white/20"
                        :disabled="passBusy"
                      />
                      <button
                        type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 p-2 rounded-lg hover:bg-white/10"
                        @click="showCurrent = !showCurrent"
                        :disabled="passBusy"
                      >
                        <component :is="showCurrent ? EyeOff : Eye" class="h-4 w-4 text-white/70" />
                      </button>
                    </div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">
                      {{ tr("profile.new_password", "Nowe hasło") }}
                    </label>
                    <div class="relative">
                      <input
                        v-model="newPassword"
                        :type="showNew ? 'text' : 'password'"
                        class="w-full h-11 px-4 pr-11 rounded-xl border border-white/15 bg-white/10 text-white placeholder:text-white/40 outline-none focus:ring-2 focus:ring-white/20"
                        :disabled="passBusy"
                      />
                      <button
                        type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 p-2 rounded-lg hover:bg-white/10"
                        @click="showNew = !showNew"
                        :disabled="passBusy"
                      >
                        <component :is="showNew ? EyeOff : Eye" class="h-4 w-4 text-white/70" />
                      </button>
                    </div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">
                      {{ tr("profile.new_password2", "Potwierdź nowe hasło") }}
                    </label>
                    <div class="relative">
                      <input
                        v-model="newPassword2"
                        :type="showNew2 ? 'text' : 'password'"
                        class="w-full h-11 px-4 pr-11 rounded-xl border border-white/15 bg-white/10 text-white placeholder:text-white/40 outline-none focus:ring-2 focus:ring-white/20"
                        :disabled="passBusy"
                      />
                      <button
                        type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 p-2 rounded-lg hover:bg-white/10"
                        @click="showNew2 = !showNew2"
                        :disabled="passBusy"
                      >
                        <component :is="showNew2 ? EyeOff : Eye" class="h-4 w-4 text-white/70" />
                      </button>
                    </div>
                  </div>

                  <div class="pt-2 flex flex-col sm:flex-row gap-2 sm:justify-end">
                    <button
                      type="button"
                      class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-white/10 border border-white/15 hover:bg-white/15 transition disabled:opacity-50"
                      @click="closePassword"
                      :disabled="passBusy"
                    >
                      {{ tr("actions.cancel", "Anuluj") }}
                    </button>

                    <button
                      type="button"
                      class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-emerald-500 to-teal-600 hover:opacity-90 active:opacity-80 transition shadow-lg disabled:opacity-50"
                      @click="changePassword"
                      :disabled="passBusy || !currentPassword || !newPassword || !newPassword2"
                    >
                      <Save class="h-4 w-4" />
                      {{ tr("actions.save", "Zapisz") }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>

      <!-- Delete Account Modal -->
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
          <div v-if="deleteAccountOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
            <button class="absolute inset-0 bg-black/60" @click="closeDeleteAccount" aria-label="Close" />

            <div class="relative w-full max-w-lg rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white overflow-hidden">
              <div class="p-6">
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0">
                    <h3 class="text-xl font-semibold drop-shadow">
                      {{ tr("profile.delete_confirm_title", "Usunąć konto?") }}
                    </h3>
                    <div class="mt-1 text-sm text-white/70">
                      {{ tr("profile.delete_confirm_hint", "Ta operacja jest nieodwracalna.") }}
                    </div>
                  </div>

                  <button
                    type="button"
                    class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 hover:bg-white/15 transition flex items-center justify-center disabled:opacity-50"
                    @click="closeDeleteAccount"
                    :disabled="deleteAccountBusy"
                    aria-label="Close"
                  >
                    <X class="h-4 w-4" />
                  </button>
                </div>

                <div class="mt-5 space-y-4">
                  <div v-if="deleteAccountError" class="p-3 rounded-xl border border-red-400/25 bg-red-500/10 text-red-200 text-sm">
                    {{ deleteAccountError }}
                  </div>

                  <p class="text-white/80 text-sm">
                    {{ tr("profile.delete_warning", "Wszystkie Twoje dane, w tym podróże i ustawienia, zostaną trwale usunięte. Tej operacji nie można cofnąć.") }}
                  </p>

                  <div class="pt-2 flex flex-col sm:flex-row gap-2 sm:justify-end">
                    <button
                      type="button"
                      class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-white/10 border border-white/15 hover:bg-white/15 transition disabled:opacity-50"
                      @click="closeDeleteAccount"
                      :disabled="deleteAccountBusy"
                    >
                      {{ tr("actions.cancel", "Anuluj") }}
                    </button>

                    <button
                      type="button"
                      class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-red-600 to-red-700 hover:opacity-90 active:opacity-80 transition shadow-lg disabled:opacity-50"
                      @click="deleteAccount"
                      :disabled="deleteAccountBusy"
                    >
                      <Trash2 class="h-4 w-4" />
                      {{ tr("actions.delete_confirm", "Tak, usuń konto") }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>
    </div>
  </div>
</template>