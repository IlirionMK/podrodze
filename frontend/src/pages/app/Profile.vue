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

const invitesLoading = ref(false)
const invitesError = ref("")
const invitations = ref([])
const inviteBusyId = ref(null)

const PASS_ENDPOINT = "/user/password"
const PASS_METHOD = "put"

const INVITES_LIST = "/user/invitations"
const INVITES_ACCEPT = (id) => `/user/invitations/${id}/accept`
const INVITES_DECLINE = (id) => `/user/invitations/${id}/decline`

const btnBase =
    "inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
const btnPrimary =
    btnBase + " bg-gradient-to-r from-blue-600 to-purple-600 text-white hover:opacity-90 active:opacity-80 shadow"
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

async function loadMe() {
  errorMessage.value = ""
  successMessage.value = ""

  if (!token.value) {
    errorMessage.value = tr("auth.no_token", "Brak tokenu — zaloguj się ponownie.")
    return
  }

  loading.value = true
  try {
    const res = await api.get("/user")
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
    const res = await api.put("/user", { name, email })
    user.value = res.data?.data || res.data
    successMessage.value = tr("profile.saved", "Profile updated.")
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
    passError.value = tr("profile.pass_mismatch", "New passwords do not match.")
    return
  }
  if (newPassword.value.length < 8) {
    passError.value = tr("profile.pass_too_short", "Password must be at least 8 characters.")
    return
  }

  passBusy.value = true
  try {
    const payload = {
      current_password: currentPassword.value,
      password: newPassword.value,
      password_confirmation: newPassword2.value,
    }

    if (PASS_METHOD.toLowerCase() === "put") {
      await api.put(PASS_ENDPOINT, payload)
    } else {
      await api.post(PASS_ENDPOINT, payload)
    }

    closePassword()
    successMessage.value = tr("profile.pass_changed", "Password changed.")
  } catch (e) {
    passError.value = getErrMessage(e)
  } finally {
    passBusy.value = false
  }
}

async function acceptInvite(inv) {
  const id = inv?.id ?? inv?.invitation_id
  if (!id) return
  inviteBusyId.value = id
  invitesError.value = ""
  try {
    await api.post(INVITES_ACCEPT(id))
    successMessage.value = tr("profile.invites.accepted", "Invitation accepted.")
    await loadInvitations()
  } catch (e) {
    invitesError.value = getErrMessage(e)
  } finally {
    inviteBusyId.value = null
  }
}

async function declineInvite(inv) {
  const id = inv?.id ?? inv?.invitation_id
  if (!id) return
  inviteBusyId.value = id
  invitesError.value = ""
  try {
    await api.post(INVITES_DECLINE(id))
    successMessage.value = tr("profile.invites.declined", "Invitation declined.")
    await loadInvitations()
  } catch (e) {
    invitesError.value = getErrMessage(e)
  } finally {
    inviteBusyId.value = null
  }
}

function logout() {
  token.value = null
  user.value = null
  window.location.href = "/login"
}

const pendingInvites = computed(() => {
  const list = invitations.value || []
  return list.filter((x) => {
    const s = String(x?.status || "").toLowerCase()
    return !s || s === "pending"
  })
})

onMounted(async () => {
  await loadMe()
  await loadInvitations()
})
</script>

<template>
  <div class="w-full">
    <div class="max-w-6xl mx-auto px-4 py-10">
      <div class="flex items-start justify-between gap-4 mb-6">
        <div class="min-w-0">
          <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900">
            {{ tr("profile.title", "Profile") }}
          </h1>
          <div class="mt-1 text-sm text-gray-500">
            {{ tr("profile.subtitle", "Manage your account details.") }}
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button type="button" :class="btnSecondary" @click="() => { loadMe(); loadInvitations() }" :disabled="loading || invitesLoading">
            <RefreshCw class="h-4 w-4" />
            {{ tr("actions.refresh", "Refresh") }}
          </button>

          <button type="button" :class="btnDanger" @click="logout">
            <LogOut class="h-4 w-4" />
            {{ tr("actions.logout", "Logout") }}
          </button>
        </div>
      </div>

      <div v-if="errorMessage" class="mb-4 p-4 rounded-xl border border-red-200 bg-red-50 text-red-700">
        {{ errorMessage }}
      </div>

      <div v-if="successMessage" class="mb-4 p-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700">
        {{ successMessage }}
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
        <div class="lg:col-span-7">
          <section class="bg-white rounded-2xl border shadow-sm overflow-hidden">
            <div class="p-6 border-b">
              <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-4 min-w-0">
                  <div
                      class="h-14 w-14 rounded-2xl bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-center shadow"
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
                        {{ tr("profile.joined", "Joined") }} {{ formatDate(createdAt) }}
                      </span>
                    </div>
                  </div>
                </div>

                <button type="button" :class="btnSecondary" @click="openEdit" :disabled="loading || !user">
                  <Pencil class="h-4 w-4" />
                  {{ tr("actions.edit", "Edit") }}
                </button>
              </div>
            </div>

            <div class="p-6">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 rounded-2xl border bg-gray-50">
                  <div class="text-xs text-gray-500">{{ tr("profile.fields.id", "User ID") }}</div>
                  <div class="mt-1 font-semibold text-gray-900">{{ user?.id ?? "—" }}</div>
                </div>

                <div class="p-4 rounded-2xl border bg-gray-50">
                  <div class="text-xs text-gray-500">{{ tr("profile.fields.email", "Email") }}</div>
                  <div class="mt-1 font-semibold text-gray-900">{{ user?.email ?? "—" }}</div>
                </div>
              </div>

              <div class="mt-6 flex flex-col sm:flex-row gap-2 sm:justify-end">
                <button type="button" :class="btnSecondary" @click="openPassword" :disabled="loading">
                  <KeyRound class="h-4 w-4" />
                  {{ tr("profile.change_password", "Change password") }}
                </button>
              </div>
            </div>
          </section>
        </div>

        <div class="lg:col-span-5">
          <section class="bg-white p-6 rounded-2xl border shadow-sm">
            <div class="flex items-center justify-between gap-3 mb-4">
              <h2 class="text-xl font-semibold text-gray-900">
                {{ tr("profile.invites.title", "Trip invitations") }}
              </h2>

              <div class="text-sm text-gray-500">
                {{ pendingInvites.length }}
              </div>
            </div>

            <div v-if="invitesError" class="mb-4 p-3 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm">
              {{ invitesError }}
            </div>

            <div v-if="invitesLoading" class="text-sm text-gray-500">
              {{ tr("loading", "Loading…") }}
            </div>

            <div v-else-if="pendingInvites.length === 0" class="p-4 rounded-xl border bg-gray-50 text-gray-700">
              <div class="font-semibold">{{ tr("profile.invites.empty_title", "No invitations") }}</div>
              <div class="text-sm text-gray-600 mt-1">
                {{ tr("profile.invites.empty_hint", "When someone invites you to a trip, it will appear here.") }}
              </div>
            </div>

            <div v-else class="space-y-3">
              <div
                  v-for="inv in pendingInvites"
                  :key="inv.id || inv.invitation_id"
                  class="rounded-2xl border border-gray-200 bg-white p-4"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="flex items-center gap-2 min-w-0">
                      <MailOpen class="h-4 w-4 text-gray-700" />
                      <div class="font-semibold text-gray-900 truncate">
                        {{ inv?.trip?.name || inv?.trip_name || tr("profile.invites.trip", "Trip") }}
                      </div>
                    </div>

                    <div class="mt-1 text-sm text-gray-600">
                      <span v-if="inv?.role">{{ tr("common.role", "Role") }}: <span class="font-medium">{{ inv.role }}</span></span>
                      <span v-if="inv?.invited_by?.name || inv?.inviter_name" class="ml-2">
                        <span class="text-gray-300">•</span>
                        {{ tr("profile.invites.from", "From") }}:
                        <span class="font-medium">{{ inv?.invited_by?.name || inv?.inviter_name }}</span>
                      </span>
                    </div>
                  </div>

                  <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-medium border border-gray-200 bg-white text-gray-900 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="inviteBusyId === (inv.id || inv.invitation_id)"
                        @click="declineInvite(inv)"
                    >
                      <Ban class="h-4 w-4 text-gray-700" />
                      {{ tr("actions.decline", "Decline") }}
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-medium bg-gradient-to-r from-blue-600 to-purple-600 text-white hover:opacity-90 active:opacity-80 shadow disabled:opacity-50"
                        :disabled="inviteBusyId === (inv.id || inv.invitation_id)"
                        @click="acceptInvite(inv)"
                    >
                      <Check class="h-4 w-4" />
                      {{ tr("actions.accept", "Accept") }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>

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
                      {{ tr("profile.edit_title", "Edit profile") }}
                    </h3>
                    <div class="mt-1 text-sm text-white/70">
                      {{ tr("profile.edit_hint", "Update your name and email.") }}
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
                      {{ tr("profile.fields.name", "Name") }}
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
                      {{ tr("actions.cancel", "Cancel") }}
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-blue-500 to-purple-600 hover:opacity-90 active:opacity-80 transition shadow-lg disabled:opacity-50"
                        @click="saveProfile"
                        :disabled="loading || !editName.trim() || !editEmail.trim()"
                    >
                      <Save class="h-4 w-4" />
                      {{ tr("actions.save", "Save") }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>

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
                      {{ tr("profile.password_title", "Change password") }}
                    </h3>
                    <div class="mt-1 text-sm text-white/70">
                      {{ tr("profile.password_hint", "Enter your current password and choose a new one.") }}
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
                      {{ tr("profile.current_password", "Current password") }}
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
                      {{ tr("profile.new_password", "New password") }}
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
                      {{ tr("profile.new_password2", "Confirm new password") }}
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
                      {{ tr("actions.cancel", "Cancel") }}
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-blue-500 to-purple-600 hover:opacity-90 active:opacity-80 transition shadow-lg disabled:opacity-50"
                        @click="changePassword"
                        :disabled="passBusy || !currentPassword || !newPassword || !newPassword2"
                    >
                      <Save class="h-4 w-4" />
                      {{ tr("actions.save", "Save") }}
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
