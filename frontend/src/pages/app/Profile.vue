<script setup>
import { ref, computed, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import {
  Mail,
  CalendarDays,
  Pencil,
  Save,
  RefreshCw,
  Check,
  Ban,
  MailOpen,
  MapPin,
  Trash2,
  UserX,
  User,
  Calendar,
  KeyRound,
} from "lucide-vue-next"

import api from "@/composables/api/api.js"
import { useAuth } from "@/composables/useAuth"
import BaseModal from "@/components/ui/BaseModal.vue"
import ConfirmModal from "@/components/ui/ConfirmModal.vue"
import BaseInput from "@/components/forms/BaseInput.vue"

const { t, te } = useI18n()
function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

const { token, user, clearAuth } = useAuth()

const loading = ref(false)
const errorMessage = ref("")
const successMessage = ref("")
const activeTab = ref("profile")

const editOpen = ref(false)
const editName = ref("")

const passOpen = ref(false)
const passBusy = ref(false)
const passError = ref("")
const currentPassword = ref("")
const newPassword = ref("")
const newPassword2 = ref("")

const invitesLoading = ref(false)
const invitesError = ref("")
const invitations = ref([])
const inviteBusyId = ref(null)

const tripsLoading = ref(false)
const myTrips = ref([])
const tripBusyId = ref(null)

const deleteAccountOpen = ref(false)
const deleteAccountBusy = ref(false)
const deleteAccountError = ref("")
const deleteAccountPassword = ref("")

const leaveTripOpen = ref(false)
const leaveTripTarget = ref(null)

const ME_SHOW = `/users/me`
const ME_UPDATE = `/users/me`
const ME_PASSWORD = `/users/me/password`
const ME_DELETE = `/users/me`

const INVITES_LIST = `/users/me/invites`
const INVITES_ACCEPT = (tripId) => `/trips/${tripId}/accept`
const INVITES_DECLINE = (tripId) => `/trips/${tripId}/decline`

const MY_TRIPS = `/trips`
const LEAVE_TRIP = (tripId, userId) => `/trips/${tripId}/members/${userId}`

const iconSm = "h-4 w-4 shrink-0 flex-none"
const iconLg = "w-12 h-12 shrink-0 flex-none"

function getErrMessage(err) {
  return err?.response?.data?.message || err?.response?.data?.error || tr("errors.default", "Something went wrong.")
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
  switch (String(role || "").toLowerCase()) {
    case "owner":
      return "bg-emerald-100 text-emerald-700 border-emerald-200"
    case "editor":
      return "bg-teal-100 text-teal-700 border-teal-200"
    case "member":
      return "bg-gray-100 text-gray-700 border-gray-200"
    default:
      return "bg-gray-100 text-gray-700 border-gray-200"
  }
}

function getRoleLabel(role) {
  switch (String(role || "").toLowerCase()) {
    case "owner":
      return tr("roles.owner", "Właściciel")
    case "editor":
      return tr("roles.editor", "Edytor")
    case "member":
      return tr("roles.member", "Członek")
    default:
      return role || "—"
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
    const res = await api.get(ME_SHOW)
    user.value = res.data?.data ?? res.data ?? null
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
  editOpen.value = true
  successMessage.value = ""
  errorMessage.value = ""
}
function closeEdit() {
  editOpen.value = false
}

async function saveProfile() {
  const name = editName.value.trim()
  if (!name) return

  loading.value = true
  errorMessage.value = ""
  successMessage.value = ""

  try {
    const res = await api.patch(ME_UPDATE, { name })
    user.value = res.data?.data ?? res.data ?? user.value
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
    await api.put(ME_PASSWORD, {
      current_password: currentPassword.value,
      new_password: newPassword.value,
      new_password_confirmation: newPassword2.value,
    })
    closePassword()
    successMessage.value = tr("user.pass_changed", "Hasło zostało zmienione.")
  } catch (e) {
    passError.value = getErrMessage(e)
  } finally {
    passBusy.value = false
  }
}

function getInviteTripId(inv) {
  return inv?.trip_id ?? inv?.trip?.id ?? inv?.id
}

async function acceptInvite(inv) {
  const tripId = getInviteTripId(inv)
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
  const tripId = getInviteTripId(inv)
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

function openLeaveTrip(trip) {
  leaveTripTarget.value = trip
  leaveTripOpen.value = true
}

const leaveTripDescription = computed(() => {
  const name = leaveTripTarget.value?.name || ""
  return tr("user.leave_confirm", `Czy na pewno chcesz opuścić podróż "${name}"?`)
})
const leaveTripBusy = computed(() => tripBusyId.value === leaveTripTarget.value?.id)

async function confirmLeaveTrip() {
  const trip = leaveTripTarget.value
  const tripId = trip?.id
  const userId = user.value?.id
  if (!tripId || !userId) return

  tripBusyId.value = tripId
  errorMessage.value = ""

  try {
    await api.delete(LEAVE_TRIP(tripId, userId))
    successMessage.value = tr("trips.left", "Opuściłeś podróż.")
    leaveTripOpen.value = false
    leaveTripTarget.value = null
    myTrips.value = (myTrips.value || []).filter((t) => t?.id !== tripId)
    await refreshAll()
  } catch (e) {
    errorMessage.value = getErrMessage(e)
  } finally {
    tripBusyId.value = null
  }
}

function openDeleteAccount() {
  deleteAccountOpen.value = true
  deleteAccountError.value = ""
  deleteAccountPassword.value = ""
}
function closeDeleteAccount() {
  deleteAccountOpen.value = false
}

async function deleteAccount() {
  deleteAccountError.value = ""

  const pwd = deleteAccountPassword.value.trim()
  if (!pwd) {
    deleteAccountError.value = tr("profile.delete_password_required", "Wymagane jest obecne hasło.")
    return
  }

  deleteAccountBusy.value = true
  try {
    await api.delete(ME_DELETE, { data: { current_password: pwd } })
    clearAuth()
    window.location.href = "/login"
  } catch (e) {
    deleteAccountError.value = getErrMessage(e)
  } finally {
    deleteAccountBusy.value = false
  }
}

async function refreshAll() {
  await Promise.all([loadMe(), loadInvitations(), loadMyTrips()])
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
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div class="min-w-0">
          <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900">
            {{ tr("user.title", "Panel użytkownika") }}
          </h1>
          <div class="mt-1 text-sm text-gray-500">
            {{ tr("user.subtitle", "Zarządzaj swoim profilem i podróżami") }}
          </div>
        </div>

        <div class="flex items-center justify-end gap-2">
          <button
              type="button"
              class="btn-back w-full sm:w-auto"
              @click="refreshAll"
              :disabled="loading || invitesLoading || tripsLoading"
          >
            <RefreshCw :class="iconSm" />
            <span class="hidden sm:inline">{{ tr("actions.refresh", "Odśwież") }}</span>
          </button>
        </div>
      </div>

      <div v-if="errorMessage" class="mb-4 p-4 rounded-xl border border-red-200 bg-red-50 text-red-700">
        {{ errorMessage }}
      </div>

      <div v-if="successMessage" class="mb-4 p-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700">
        {{ successMessage }}
      </div>

      <div class="mb-6">
        <div class="border-b border-gray-200">
          <nav class="flex gap-1 overflow-x-auto" aria-label="Tabs">
            <button
                @click="activeTab = 'profile'"
                :class="[
                'px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap',
                activeTab === 'profile'
                  ? 'border-emerald-600 text-emerald-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
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
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
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
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
              ]"
            >
              {{ tr("tabs.my_trips", "Moje podróże") }}
            </button>
          </nav>
        </div>
      </div>

      <div v-show="activeTab === 'profile'" class="space-y-4">
        <section class="card-surface overflow-hidden">
          <div class="card-pad border-b">
            <div class="flex items-start justify-between gap-4">
              <div class="flex items-center gap-4 min-w-0">
                <div
                    class="h-14 w-14 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white flex items-center justify-center shadow shrink-0"
                    :title="user?.name"
                >
                  <span class="text-lg font-semibold">{{ initials }}</span>
                </div>

                <div class="min-w-0">
                  <div class="text-xl font-semibold text-gray-900 truncate">
                    {{ user?.name || "—" }}
                  </div>

                  <div class="mt-1 flex flex-col gap-1 text-sm text-gray-500 min-w-0">
                    <span class="inline-flex items-start gap-2 min-w-0">
                      <Mail :class="iconSm" class="mt-0.5" />
                      <span class="min-w-0 break-words" :title="user?.email || ''">
                        {{ user?.email || "—" }}
                      </span>
                    </span>

                    <span v-if="createdAt" class="inline-flex items-center gap-2">
                      <CalendarDays :class="iconSm" />
                      <span class="text-gray-500">
                        {{ tr("user.joined", "Dołączono") }} {{ formatDate(createdAt) }}
                      </span>
                    </span>
                  </div>
                </div>
              </div>

              <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                <button type="button" class="btn-secondary w-full sm:w-auto" @click="openPassword" :disabled="loading || !user">
                  <KeyRound :class="iconSm" />
                  {{ tr("user.change_password", "Zmień hasło") }}
                </button>

                <button type="button" class="btn-back w-full sm:w-auto" @click="openEdit" :disabled="loading || !user">
                  <Pencil :class="iconSm" />
                  {{ tr("actions.edit", "Edytuj") }}
                </button>
              </div>
            </div>
          </div>

          <div class="card-pad">
            <div class="grid grid-cols-1 gap-4">
              <div class="p-4 rounded-2xl border bg-gray-50">
                <div class="text-xs text-gray-500">{{ tr("user.fields.email", "Email") }}</div>
                <div class="mt-1 font-semibold text-gray-900 break-words" :title="user?.email || ''">
                  {{ user?.email ?? "—" }}
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="card-surface">
          <div class="card-pad flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0">
              <div class="text-sm font-semibold text-gray-900">
                {{ tr("user.delete_account", "Usuń konto") }}
              </div>
              <div class="mt-1 text-sm text-gray-500">
                {{ tr("user.delete_hint", "Po usunięciu konta wszystkie Twoje dane zostaną trwale usunięte.") }}
              </div>
            </div>

            <button type="button" class="btn-danger w-full sm:w-auto shrink-0" @click="openDeleteAccount">
              <Trash2 :class="iconSm" />
              {{ tr("user.delete_account", "Usuń konto") }}
            </button>
          </div>
        </section>
      </div>

      <div v-show="activeTab === 'invitations'">
        <div v-if="invitesLoading" class="text-center py-12">
          <div class="text-gray-500">{{ tr("loading", "Ładowanie...") }}</div>
        </div>

        <div v-else-if="pendingInvites.length === 0" class="card-surface card-pad">
          <div class="text-center">
            <MailOpen :class="iconLg" class="text-gray-300 mx-auto mb-4" />
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
              :key="getInviteTripId(inv) || inv.id || inv.invitation_id"
              class="card-surface card-pad hover:shadow-md transition"
          >
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                    {{ tr("profile.invites.new", "Nowe zaproszenie") }}
                  </span>
                  <span v-if="inv.created_at" class="text-xs text-gray-400">
                    {{ formatDate(new Date(inv.created_at)) }}
                  </span>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 mb-1 break-words">
                  {{ inv?.trip?.name || inv?.trip_name || tr("profile.invites.trip", "Podróż") }}
                </h3>

                <div class="space-y-1 text-sm text-gray-600">
                  <p v-if="inv?.invited_by?.name || inv?.inviter_name" class="break-words">
                    <User :class="iconSm" class="inline mr-2" />
                    {{ tr("profile.invites.from", "Zaproszony przez") }}:
                    <span class="font-medium">{{ inv?.invited_by?.name || inv?.inviter_name }}</span>
                  </p>

                  <p v-if="inv?.trip?.start_location || inv?.start_location" class="break-words">
                    <MapPin :class="iconSm" class="inline mr-2" />
                    {{ tr("trip.start_location", "Miejsce startowe") }}:
                    <span class="font-medium">{{ inv?.trip?.start_location || inv?.start_location }}</span>
                  </p>

                  <p class="flex items-center gap-2">
                    <span>{{ tr("common.role", "Rola") }}:</span>
                    <span
                        :class="[
                        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border shrink-0',
                        getRoleBadgeColor(inv.role),
                      ]"
                    >
                      {{ getRoleLabel(inv.role) }}
                    </span>
                  </p>
                </div>
              </div>

              <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                <button type="button" class="btn-primary w-full sm:w-auto" :disabled="inviteBusyId === getInviteTripId(inv)" @click="acceptInvite(inv)">
                  <Check :class="iconSm" />
                  {{ tr("actions.accept", "Zaakceptuj") }}
                </button>

                <button type="button" class="btn-back w-full sm:w-auto" :disabled="inviteBusyId === getInviteTripId(inv)" @click="declineInvite(inv)">
                  <Ban :class="iconSm" />
                  {{ tr("actions.decline", "Odrzuć") }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="invitesError" class="mt-4 p-3 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm">
          {{ invitesError }}
        </div>
      </div>

      <div v-show="activeTab === 'trips'">
        <div v-if="tripsLoading" class="text-center py-12">
          <div class="text-gray-500">{{ tr("loading", "Ładowanie...") }}</div>
        </div>

        <div v-else-if="myTrips.length === 0" class="card-surface card-pad">
          <div class="text-center">
            <MapPin :class="iconLg" class="text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">
              {{ tr("trips.empty_title", "Brak podróży") }}
            </h3>
            <p class="text-sm text-gray-500">
              {{ tr("trips.empty_hint", "Nie należysz jeszcze do żadnej podróży.") }}
            </p>
          </div>
        </div>

        <div v-else class="space-y-4">
          <div v-for="trip in myTrips" :key="trip.id" class="card-surface card-pad hover:shadow-md transition">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 mb-2 min-w-0">
                  <h3 class="text-lg font-semibold text-gray-900 break-words">{{ trip.name }}</h3>
                  <span
                      :class="[
                      'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border shrink-0',
                      getRoleBadgeColor(trip.user_role || trip.role),
                    ]"
                  >
                    {{ getRoleLabel(trip.user_role || trip.role) }}
                  </span>
                </div>

                <div class="space-y-1 text-sm text-gray-600">
                  <p v-if="trip.start_date || trip.startDate || trip.trip_date" class="break-words">
                    <Calendar :class="iconSm" class="inline mr-2" />
                    {{ tr("trip.start_date", "Data rozpoczęcia") }}:
                    <span class="font-medium">
                      {{ formatDate(new Date(trip.start_date || trip.startDate || trip.trip_date)) }}
                    </span>
                  </p>

                  <p v-if="trip.members_count || trip.members" class="break-words">
                    <User :class="iconSm" class="inline mr-2" />
                    {{ tr("trip.members", "Liczba uczestników") }}:
                    <span class="font-medium">
                      {{ trip.members_count ?? (Array.isArray(trip.members) ? trip.members.length : "—") }}
                    </span>
                  </p>
                </div>
              </div>

              <div class="flex flex-col gap-2 shrink-0 w-full sm:w-auto">
                <router-link :to="{ name: 'app.trips.show', params: { id: trip.id } }" class="btn-secondary w-full sm:w-auto">
                  <MapPin :class="iconSm" />
                  {{ tr("actions.view_details", "Zobacz szczegóły") }}
                </router-link>

                <button
                    v-if="(trip.user_role || trip.role) !== 'owner'"
                    type="button"
                    class="btn-danger w-full sm:w-auto"
                    @click="openLeaveTrip(trip)"
                    :disabled="tripBusyId === trip.id"
                >
                  <UserX :class="iconSm" />
                  {{ tr("actions.leave", "Opuść podróż") }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <BaseModal
          v-model="editOpen"
          :title="tr('profile.edit_title', 'Edytuj profil')"
          :description="tr('profile.edit_hint', 'Zaktualizuj swoje imię i nazwisko.')"
          :busy="loading"
      >
        <div class="space-y-4">
          <BaseInput v-model="editName" :label="tr('profile.fields.name', 'Imię i nazwisko')" name="name" autocomplete="name" />
        </div>

        <template #footer>
          <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeEdit" :disabled="loading">
            {{ tr("actions.cancel", "Anuluj") }}
          </button>

          <button type="button" class="btn-primary w-full sm:w-auto" @click="saveProfile" :disabled="loading || !editName.trim()">
            <Save :class="iconSm" />
            {{ tr("actions.save", "Zapisz") }}
          </button>
        </template>
      </BaseModal>

      <BaseModal
          v-model="passOpen"
          :title="tr('profile.password_title', 'Zmiana hasła')"
          :description="tr('profile.password_hint', 'Wprowadź obecne hasło i wybierz nowe.')"
          :busy="passBusy"
      >
        <div class="space-y-4">
          <div v-if="passError" class="p-4 rounded-xl bg-red-50 text-red-700 border border-red-200 text-sm">
            {{ passError }}
          </div>

          <BaseInput v-model="currentPassword" type="password" :label="tr('profile.current_password', 'Obecne hasło')" autocomplete="current-password" />
          <BaseInput v-model="newPassword" type="password" :label="tr('profile.new_password', 'Nowe hasło')" autocomplete="new-password" />
          <BaseInput v-model="newPassword2" type="password" :label="tr('profile.new_password2', 'Potwierdź nowe hasło')" autocomplete="new-password" />
        </div>

        <template #footer>
          <button type="button" class="btn-secondary w-full sm:w-auto" @click="closePassword" :disabled="passBusy">
            {{ tr("actions.cancel", "Anuluj") }}
          </button>

          <button
              type="button"
              class="btn-primary w-full sm:w-auto"
              @click="changePassword"
              :disabled="passBusy || !currentPassword || !newPassword || !newPassword2"
          >
            <Save :class="iconSm" />
            {{ tr("actions.save", "Zapisz") }}
          </button>
        </template>
      </BaseModal>

      <BaseModal
          v-model="deleteAccountOpen"
          :title="tr('profile.delete_confirm_title', 'Usunąć konto?')"
          :description="tr('profile.delete_confirm_hint', 'Ta operacja jest nieodwracalna.')"
          :busy="deleteAccountBusy"
          max-width-class="max-w-md"
      >
        <div class="space-y-4">
          <div v-if="deleteAccountError" class="p-4 rounded-xl bg-red-50 text-red-700 border border-red-200 text-sm">
            {{ deleteAccountError }}
          </div>

          <p class="text-sm text-gray-600">
            {{
              tr(
                  "profile.delete_warning",
                  "Wszystkie Twoje dane, w tym podróże i ustawienia, zostaną trwale usunięte. Tej operacji nie można cofnąć."
              )
            }}
          </p>

          <BaseInput
              v-model="deleteAccountPassword"
              type="password"
              :label="tr('profile.current_password', 'Obecne hasło')"
              autocomplete="current-password"
          />
        </div>

        <template #footer>
          <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeDeleteAccount" :disabled="deleteAccountBusy">
            {{ tr("actions.cancel", "Anuluj") }}
          </button>

          <button
              type="button"
              class="btn-danger w-full sm:w-auto"
              @click="deleteAccount"
              :disabled="deleteAccountBusy || !deleteAccountPassword.trim()"
          >
            <Trash2 :class="iconSm" />
            {{ tr("user.delete_account", "Usuń konto") }}
          </button>
        </template>
      </BaseModal>

      <ConfirmModal
          v-model="leaveTripOpen"
          :title="tr('user.leave_title', 'Opuścić podróż?')"
          :description="leaveTripDescription"
          :confirm-text="tr('actions.leave', 'Opuść podróż')"
          :cancel-text="tr('actions.cancel', 'Anuluj')"
          :busy="leaveTripBusy"
          tone="danger"
          @confirm="confirmLeaveTrip"
      />
    </div>
  </div>
</template>
