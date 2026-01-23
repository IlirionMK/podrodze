<script setup>
import { computed, ref } from "vue"
import { useRoute } from "vue-router"
import { useI18n } from "vue-i18n"
import {
  UserRound,
  Crown,
  Pencil,
  User,
  ShieldCheck,
  Trash2,
  UserPlus,
  X,
  RefreshCw,
  Eye,
  EyeOff,
  ChevronDown,
} from "lucide-vue-next"

import { useAuth } from "@/composables/useAuth.js"
import { inviteTripMember, updateTripMember, removeTripMember } from "@/composables/api/tripMembers.js"

const props = defineProps({
  trip: { type: Object, required: true },
  members: { type: Array, required: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(["members-changed", "error"])

const route = useRoute()
const { t, te } = useI18n()
const { user: authUser } = useAuth()

function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

const btnBase =
    "inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
const btnPrimary =
    btnBase + " bg-gradient-to-r from-blue-600 to-purple-600 text-white hover:opacity-90 active:opacity-80 shadow"
const btnDanger = btnBase + " border border-red-200 bg-red-50 text-red-700 hover:bg-red-100"

const iconBtnBase =
    "shrink-0 h-9 w-9 sm:h-10 sm:w-10 rounded-xl border transition flex items-center justify-center disabled:opacity-40 disabled:cursor-not-allowed"

const inviteOpen = ref(false)
const inviteEmail = ref("")
const inviteRole = ref("member")
const inviteBusy = ref(false)

const confirmOpen = ref(false)
const confirmBusy = ref(false)
const confirmTarget = ref(null)

const showDeclined = ref(false)
const resendBusyId = ref(null)

function getRole(m) {
  return m?.role ?? m?.pivot?.role ?? (m?.is_owner ? "owner" : "member")
}

function getStatus(m) {
  return m?.status ?? m?.pivot?.status ?? (m?.is_owner ? "accepted" : "")
}

function getEmail(m) {
  return m?.email ?? m?.user?.email ?? null
}

function normalizeStatus(s) {
  if (!s) return ""
  if (s === "rejected") return "declined"
  return s
}

function roleIcon(role) {
  if (role === "owner") return Crown
  if (role === "editor") return Pencil
  return User
}

function roleLabel(role) {
  if (role === "owner") return tr("roles.owner", "Owner")
  if (role === "editor") return tr("roles.editor", "Editor")
  return tr("roles.member", "Member")
}

function statusLabel(statusRaw) {
  const status = normalizeStatus(statusRaw)
  if (status === "accepted") return tr("statuses.accepted", "Accepted")
  if (status === "pending") return tr("statuses.pending", "Pending")
  if (status === "declined") return tr("statuses.declined", "Declined")
  return String(status || "")
}

function statusPillClass(statusRaw) {
  const status = normalizeStatus(statusRaw)
  if (status === "accepted") return "bg-emerald-50 text-emerald-700 border-emerald-200"
  if (status === "pending") return "bg-amber-50 text-amber-700 border-amber-200"
  if (status === "declined") return "bg-rose-50 text-rose-700 border-rose-200"
  return "bg-gray-50 text-gray-700 border-gray-200"
}

function canManageMembers() {
  const uid = authUser.value?.id
  if (!uid || !props.trip) return false
  if (props.trip.owner_id === uid) return true

  const me = props.members.find((m) => m.id === uid)
  const meRole = getRole(me)
  const meStatus = normalizeStatus(getStatus(me))
  return meRole === "editor" && meStatus === "accepted"
}

const canManage = computed(() => canManageMembers())

function canEditTarget(target) {
  if (!canManage.value) return false
  const meId = authUser.value?.id
  if (!meId) return false
  if (target?.id === meId) return false
  if (getRole(target) === "owner") return false
  return true
}

function canChangeRole(target) {
  if (!canEditTarget(target)) return false
  return props.trip?.owner_id === authUser.value?.id
}

function canRemove(target) {
  return canEditTarget(target)
}

const acceptedMembers = computed(() =>
    props.members.filter((m) => normalizeStatus(getStatus(m)) === "accepted")
)

const pendingInvites = computed(() =>
    props.members.filter((m) => normalizeStatus(getStatus(m)) === "pending" && getRole(m) !== "owner")
)

const declinedInvites = computed(() =>
    props.members.filter((m) => normalizeStatus(getStatus(m)) === "declined" && getRole(m) !== "owner")
)

function openInvite() {
  if (!canManage.value) return
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
  try {
    await inviteTripMember(route.params.id, { email, role: inviteRole.value })
    closeInvite()
    emit("members-changed")
  } catch (e) {
    emit("error", e?.response?.data?.message || tr("errors.default", "Something went wrong."))
  } finally {
    inviteBusy.value = false
  }
}

async function toggleRole(target) {
  if (!target?.id) return
  if (!canChangeRole(target)) return

  try {
    const currentRole = getRole(target)
    const nextRole = currentRole === "editor" ? "member" : "editor"
    await updateTripMember(route.params.id, target.id, { role: nextRole })
    emit("members-changed")
  } catch (e) {
    emit("error", e?.response?.data?.message || tr("errors.default", "Something went wrong."))
  }
}

function openRemoveConfirm(target) {
  if (!target?.id) return
  if (!canRemove(target)) return
  confirmTarget.value = target
  confirmOpen.value = true
}

function closeRemoveConfirm() {
  confirmOpen.value = false
  confirmTarget.value = null
}

async function confirmRemove() {
  if (!confirmTarget.value?.id) return

  confirmBusy.value = true
  try {
    await removeTripMember(route.params.id, confirmTarget.value.id)
    closeRemoveConfirm()
    emit("members-changed")
  } catch (e) {
    emit("error", e?.response?.data?.message || tr("errors.default", "Something went wrong."))
  } finally {
    confirmBusy.value = false
  }
}

async function inviteAgain(target) {
  if (!canManage.value) return
  if (!target?.id) return

  const email = getEmail(target)
  const role = getRole(target) || "member"

  if (!email) {
    emit("error", tr("trip.team.resend_no_email", "Cannot invite again: missing email."))
    return
  }

  resendBusyId.value = target.id
  try {
    await inviteTripMember(route.params.id, { email, role })
    emit("members-changed")
  } catch (e) {
    emit("error", e?.response?.data?.message || tr("errors.default", "Something went wrong."))
  } finally {
    resendBusyId.value = null
  }
}

function rowRoleClass(role) {
  if (role === "owner") return "bg-purple-50 text-purple-700 border-purple-200"
  if (role === "editor") return "bg-blue-50 text-blue-700 border-blue-200"
  return "bg-gray-50 text-gray-700 border-gray-200"
}

function sectionTitle(key, fallback) {
  return tr(key, fallback)
}
</script>

<template>
  <section class="bg-white p-6 rounded-2xl border shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
      <h2 class="text-xl font-semibold">{{ t("trip.view.members") }}</h2>

      <div class="flex flex-wrap items-center gap-2 sm:gap-3">
        <div v-if="loading" class="text-sm text-gray-500">
          {{ t("loading") }}…
        </div>

        <button
            v-if="canManage"
            type="button"
            :class="btnPrimary + ' h-11 px-3 sm:px-4'"
            @click="openInvite"
        >
          <UserPlus class="h-4 w-4 shrink-0" />
          <span class="hidden sm:inline">{{ t("trip.view.add_member") }}</span>
        </button>
      </div>
    </div>

    <div v-if="members.length === 0" class="p-6 rounded-xl border bg-gray-50 text-gray-700">
      <div class="font-semibold mb-1">{{ t("trip.team.empty_title") }}</div>
      <div class="text-sm text-gray-600">{{ t("trip.team.empty_hint") }}</div>
    </div>

    <div v-else class="space-y-6">
      <div>
        <div class="flex items-center justify-between gap-3 mb-2">
          <div class="text-sm font-semibold text-gray-900">
            {{ sectionTitle("trip.team.sections.members", "Members") }}
            <span class="text-gray-500 font-normal">({{ acceptedMembers.length }})</span>
          </div>
        </div>

        <div class="rounded-2xl border border-gray-200 overflow-hidden">
          <div v-for="m in acceptedMembers" :key="m.id" class="px-4 py-3 hover:bg-gray-50 transition">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div
                    class="h-10 w-10 shrink-0 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-center shadow"
                    :title="m.name"
                >
                  <UserRound class="h-5 w-5 shrink-0" />
                </div>

                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2 min-w-0">
                    <div class="font-medium text-gray-900 truncate max-w-[14rem] sm:max-w-none">
                      {{ m.name }}
                    </div>

                    <span
                        class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full border"
                        :class="rowRoleClass(getRole(m))"
                    >
                      <component :is="roleIcon(getRole(m))" class="h-3.5 w-3.5 shrink-0" />
                      {{ roleLabel(getRole(m)) }}
                    </span>

                    <span
                        class="inline-flex items-center text-xs px-2 py-1 rounded-full border"
                        :class="statusPillClass(getStatus(m))"
                    >
                      {{ statusLabel(getStatus(m)) }}
                    </span>
                  </div>
                </div>
              </div>

              <div v-if="canManage" class="flex items-center gap-2 justify-end sm:justify-start">
                <button
                    type="button"
                    :class="iconBtnBase + ' border-gray-200 bg-white hover:bg-gray-50'"
                    :title="tr('trip.team.actions.change_role', 'Change role')"
                    :disabled="!canChangeRole(m)"
                    @click="toggleRole(m)"
                >
                  <ShieldCheck class="h-4 w-4 text-gray-800 shrink-0" />
                </button>

                <button
                    type="button"
                    :class="iconBtnBase + ' border-red-200 bg-red-50 hover:bg-red-100'"
                    :title="tr('trip.team.actions.remove', 'Remove from trip')"
                    :disabled="!canRemove(m)"
                    @click="openRemoveConfirm(m)"
                >
                  <Trash2 class="h-4 w-4 text-red-700 shrink-0" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div>
        <div class="flex items-center justify-between gap-3 mb-2">
          <div class="text-sm font-semibold text-gray-900">
            {{ sectionTitle("trip.team.sections.invites", "Invites") }}
            <span class="text-gray-500 font-normal">({{ pendingInvites.length }})</span>
          </div>

          <button
              v-if="canManage && declinedInvites.length > 0"
              type="button"
              class="inline-flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900"
              @click="showDeclined = !showDeclined"
          >
            <component :is="showDeclined ? EyeOff : Eye" class="h-4 w-4 shrink-0" />
            <span class="hidden sm:inline">
              {{
                showDeclined
                    ? tr("trip.team.actions.hide_declined", "Hide declined")
                    : tr("trip.team.actions.show_declined", "Show declined")
              }}
            </span>
            <span class="sm:hidden">{{ tr("trip.team.actions.declined_short", "Declined") }}</span>
            <span class="text-gray-500">({{ declinedInvites.length }})</span>
          </button>
        </div>

        <div v-if="pendingInvites.length === 0" class="text-sm text-gray-500">
          {{ tr("trip.team.no_invites", "No pending invites.") }}
        </div>

        <div v-else class="rounded-2xl border border-gray-200 overflow-hidden">
          <div v-for="m in pendingInvites" :key="m.id" class="px-4 py-3 hover:bg-gray-50 transition">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div
                    class="h-10 w-10 shrink-0 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-center shadow"
                    :title="m.name"
                >
                  <UserRound class="h-5 w-5 shrink-0" />
                </div>

                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2 min-w-0">
                    <div class="font-medium text-gray-900 truncate max-w-[14rem] sm:max-w-none">
                      {{ m.name }}
                    </div>

                    <span
                        class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full border"
                        :class="rowRoleClass(getRole(m))"
                    >
                      <component :is="roleIcon(getRole(m))" class="h-3.5 w-3.5 shrink-0" />
                      {{ roleLabel(getRole(m)) }}
                    </span>

                    <span
                        class="inline-flex items-center text-xs px-2 py-1 rounded-full border"
                        :class="statusPillClass(getStatus(m))"
                    >
                      {{ statusLabel(getStatus(m)) }}
                    </span>
                  </div>

                  <div v-if="getEmail(m)" class="text-xs text-gray-500 mt-0.5 truncate">
                    {{ getEmail(m) }}
                  </div>
                </div>
              </div>

              <div v-if="canManage" class="flex items-center gap-2 justify-end sm:justify-start">
                <button
                    type="button"
                    :class="iconBtnBase + ' border-red-200 bg-red-50 hover:bg-red-100'"
                    :title="tr('trip.team.actions.remove', 'Remove from trip')"
                    :disabled="!canRemove(m)"
                    @click="openRemoveConfirm(m)"
                >
                  <Trash2 class="h-4 w-4 text-red-700 shrink-0" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="canManage && showDeclined" class="mt-4">
          <div class="text-sm font-semibold text-gray-900 mb-2">
            {{ sectionTitle("trip.team.sections.declined", "Declined") }}
            <span class="text-gray-500 font-normal">({{ declinedInvites.length }})</span>
          </div>

          <div v-if="declinedInvites.length === 0" class="text-sm text-gray-500">
            {{ tr("trip.team.no_declined", "No declined invites.") }}
          </div>

          <div v-else class="rounded-2xl border border-gray-200 overflow-hidden">
            <div v-for="m in declinedInvites" :key="m.id" class="px-4 py-3 hover:bg-gray-50 transition">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                <div class="flex items-center gap-3 min-w-0">
                  <div
                      class="h-10 w-10 shrink-0 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 text-white flex items-center justify-center shadow"
                      :title="m.name"
                  >
                    <UserRound class="h-5 w-5 shrink-0" />
                  </div>

                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2 min-w-0">
                      <div class="font-medium text-gray-900 truncate max-w-[14rem] sm:max-w-none">
                        {{ m.name }}
                      </div>

                      <span
                          class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full border"
                          :class="rowRoleClass(getRole(m))"
                      >
                        <component :is="roleIcon(getRole(m))" class="h-3.5 w-3.5 shrink-0" />
                        {{ roleLabel(getRole(m)) }}
                      </span>

                      <span
                          class="inline-flex items-center text-xs px-2 py-1 rounded-full border"
                          :class="statusPillClass(getStatus(m))"
                      >
                        {{ statusLabel(getStatus(m)) }}
                      </span>
                    </div>

                    <div v-if="getEmail(m)" class="text-xs text-gray-500 mt-0.5 truncate">
                      {{ getEmail(m) }}
                    </div>
                  </div>
                </div>

                <div class="flex items-center gap-2 justify-end sm:justify-start">
                  <button
                      type="button"
                      :class="iconBtnBase + ' border-gray-200 bg-white hover:bg-gray-50'"
                      :title="tr('trip.team.actions.invite_again', 'Invite again')"
                      :disabled="resendBusyId === m.id || !getEmail(m)"
                      @click="inviteAgain(m)"
                  >
                    <RefreshCw class="h-4 w-4 text-gray-800 shrink-0" />
                  </button>

                  <button
                      v-if="canManage"
                      type="button"
                      :class="iconBtnBase + ' border-red-200 bg-red-50 hover:bg-red-100'"
                      :title="tr('trip.team.actions.remove', 'Remove from trip')"
                      :disabled="!canRemove(m)"
                      @click="openRemoveConfirm(m)"
                  >
                    <Trash2 class="h-4 w-4 text-red-700 shrink-0" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="canManage" class="text-xs text-gray-500">
        {{ tr("trip.team.manage_hint", "Owners and editors can manage members. Some actions may be restricted by role.") }}
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
        <div
            v-if="inviteOpen"
            class="fixed inset-0 z-50 flex items-center justify-center px-4"
            role="dialog"
            aria-modal="true"
        >
          <button class="absolute inset-0 bg-black/60" @click="closeInvite" aria-label="Close" />

          <div
              class="relative w-full max-w-lg rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white overflow-hidden"
          >
            <div class="p-6">
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <h3 class="text-xl font-semibold drop-shadow">
                    {{ tr("trip.view.add_member", "Invite member") }}
                  </h3>
                  <div class="mt-1 text-sm text-white/70">
                    {{ tr("trip.team.invite_hint", "Send an invitation.") }}
                  </div>
                </div>

                <button
                    type="button"
                    class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 hover:bg-white/15 transition flex items-center justify-center disabled:opacity-50"
                    @click="closeInvite"
                    :disabled="inviteBusy"
                    aria-label="Close"
                >
                  <X class="h-4 w-4 shrink-0" />
                </button>
              </div>

              <div class="mt-5 space-y-4">
                <div>
                  <label class="block text-sm font-medium mb-1">
                    {{ tr("common.email", "Email") }}
                  </label>
                  <input
                      v-model="inviteEmail"
                      type="email"
                      class="w-full h-11 px-4 rounded-xl border border-white/15 bg-white/10 text-white placeholder:text-white/40 outline-none focus:ring-2 focus:ring-white/20"
                      placeholder="friend@example.com"
                      :disabled="inviteBusy"
                      @keydown.enter.prevent="submitInvite"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium mb-1">
                    {{ tr("common.role", "Role") }}
                  </label>

                  <div class="relative">
                    <select
                        v-model="inviteRole"
                        class="w-full h-11 px-4 pr-11 rounded-xl border border-white/15 bg-white/10 text-white outline-none focus:ring-2 focus:ring-white/20 appearance-none"
                        :disabled="inviteBusy"
                    >
                      <option class="bg-[#0d1117]" value="member">{{ tr("roles.member", "Member") }}</option>
                      <option class="bg-[#0d1117]" value="editor">{{ tr("roles.editor", "Editor") }}</option>
                    </select>

                    <ChevronDown
                        class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 h-4 w-4 text-white/70"
                    />
                  </div>
                </div>

                <div class="pt-2 flex flex-col sm:flex-row gap-2 sm:justify-end">
                  <button
                      type="button"
                      class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-white/10 border border-white/15 hover:bg-white/15 transition disabled:opacity-50"
                      @click="closeInvite"
                      :disabled="inviteBusy"
                  >
                    {{ tr("actions.cancel", "Cancel") }}
                  </button>

                  <button
                      type="button"
                      class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-blue-500 to-purple-600 hover:opacity-90 active:opacity-80 transition shadow-lg disabled:opacity-50"
                      @click="submitInvite"
                      :disabled="inviteBusy || !inviteEmail.trim()"
                  >
                    <UserPlus class="h-4 w-4 shrink-0" />
                    {{ inviteBusy ? t("loading") : tr("actions.add", "Add") }}
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
        <div
            v-if="confirmOpen"
            class="fixed inset-0 z-50 flex items-center justify-center px-4"
            role="dialog"
            aria-modal="true"
        >
          <button class="absolute inset-0 bg-black/60" @click="closeRemoveConfirm" aria-label="Close" />

          <div
              class="relative w-full max-w-md rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white overflow-hidden"
          >
            <div class="p-6">
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <h3 class="text-xl font-semibold drop-shadow">
                    {{ tr("trip.team.remove_title", "Remove member") }}
                  </h3>
                  <div class="mt-1 text-sm text-white/70">
                    {{ tr("trip.team.remove_hint", "This user will lose access to the trip.") }}
                  </div>
                </div>

                <button
                    type="button"
                    class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 hover:bg-white/15 transition flex items-center justify-center disabled:opacity-50"
                    @click="closeRemoveConfirm"
                    :disabled="confirmBusy"
                    aria-label="Close"
                >
                  <X class="h-4 w-4 shrink-0" />
                </button>
              </div>

              <div class="mt-4 rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                <div class="font-semibold">{{ confirmTarget?.name || "—" }}</div>
                <div class="text-xs text-white/70 mt-1">
                  {{ roleLabel(getRole(confirmTarget)) }} • {{ statusLabel(getStatus(confirmTarget)) }}
                </div>
              </div>

              <div class="mt-5 flex flex-col sm:flex-row gap-2 sm:justify-end">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-white/10 border border-white/15 hover:bg-white/15 transition disabled:opacity-50"
                    @click="closeRemoveConfirm"
                    :disabled="confirmBusy"
                >
                  {{ tr("actions.cancel", "Cancel") }}
                </button>

                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-red-500/15 border border-red-400/30 hover:bg-red-500/20 transition shadow-lg disabled:opacity-50"
                    @click="confirmRemove"
                    :disabled="confirmBusy"
                >
                  <Trash2 class="h-4 w-4 shrink-0" />
                  {{ confirmBusy ? t("loading") : tr("actions.remove", "Remove") }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </section>
</template>

<style scoped>
select {
  color-scheme: dark;
}
select option {
  background-color: #0d1117;
  color: #fff;
}
select option:checked {
  background-color: rgba(99, 102, 241, 0.55);
}
</style>
