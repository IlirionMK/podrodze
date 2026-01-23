<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from "vue"
import { useI18n } from "vue-i18n"
import api from "@/composables/api/api"

import {
  Mail,
  CalendarDays,
  RefreshCw,
  Shield,
  Users,
  FileText,
  Ban,
  CheckCircle,
  Search,
  Filter,
  Calendar,
  Activity,
  UserCog,
  ChevronDown,
  ChevronUp,
  X,
  Save,
} from "lucide-vue-next"

const { t, te } = useI18n({ useScope: "global" })
function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

/**
 * UI helpers
 */
const btnBase =
    "inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
const btnPrimary =
    btnBase + " bg-gradient-to-r from-red-600 to-orange-600 text-white hover:opacity-90 active:opacity-80 shadow"
const btnSecondary = btnBase + " border border-gray-200 bg-white text-gray-900 hover:bg-gray-50"
const btnDanger = btnBase + " border border-red-200 bg-red-50 text-red-700 hover:bg-red-100"
const iconBase = "h-4 w-4 shrink-0 flex-none"

const formatDate = (dateString) => {
  if (!dateString) return "—"
  try {
    return new Intl.DateTimeFormat("pl-PL", { year: "numeric", month: "short", day: "2-digit" }).format(
        new Date(dateString)
    )
  } catch {
    return "—"
  }
}

const formatDateTime = (dateString) => {
  if (!dateString) return "—"
  try {
    return new Intl.DateTimeFormat("pl-PL", {
      year: "numeric",
      month: "short",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    }).format(new Date(dateString))
  } catch {
    return "—"
  }
}

const getLevelBadgeColor = (level) => {
  switch ((level || "").toLowerCase()) {
    case "critical":
    case "error":
      return "bg-red-100 text-red-700 border-red-200"
    case "warning":
      return "bg-yellow-100 text-yellow-700 border-yellow-200"
    case "info":
      return "bg-blue-100 text-blue-700 border-blue-200"
    case "success":
      return "bg-green-100 text-green-700 border-green-200"
    default:
      return "bg-gray-100 text-gray-700 border-gray-200"
  }
}

const formatAction = (action) => {
  if (!action) return "—"
  const mapping = {
    "admin.user.role_updated": tr("admin.logs.actions.role_updated", "User role updated"),
    "admin.user.ban_updated": tr("admin.logs.actions.ban_updated", "User ban updated"),
    "trip.member_added": tr("admin.logs.actions.trip_member_added", "Trip member added"),
    "trip.created": tr("admin.logs.actions.trip_created", "Trip created"),
    "user.login": tr("admin.logs.actions.user_login", "User login"),
    "user.logout": tr("admin.logs.actions.user_logout", "User logout"),
    "user.password_changed": tr("admin.logs.actions.user_password_changed", "User password changed"),
  }
  return mapping[action] || action.replace(/\./g, " ").replace(/_/g, " ")
}

const isBanned = (u) => Boolean(u?.banned_at) || Boolean(u?.banned)

/**
 * Pagination numbers with ellipsis
 */
const pageItems = (current, last) => {
  const c = Number(current || 1)
  const l = Math.max(1, Number(last || 1))
  if (l <= 7) return Array.from({ length: l }, (_, i) => i + 1)

  const items = new Set([1, l, c, c - 1, c + 1, c - 2, c + 2])
  const nums = Array.from(items)
      .filter((n) => n >= 1 && n <= l)
      .sort((a, b) => a - b)

  const out = []
  for (let i = 0; i < nums.length; i++) {
    out.push(nums[i])
    if (i < nums.length - 1 && nums[i + 1] - nums[i] > 1) out.push("…")
  }
  return out
}

const usersPageItems = () => pageItems(usersPagination.value.current_page, usersPagination.value.last_page)
const logsPageItems = () => pageItems(logsPagination.value.current_page, logsPagination.value.last_page)

/**
 * State
 */
const activeTab = ref("users")

const loading = ref(false)
const errorMessage = ref("")
const successMessage = ref("")
let successTimer = null

const setSuccess = (msg) => {
  successMessage.value = msg || ""
  if (successTimer) clearTimeout(successTimer)
  if (msg) {
    successTimer = setTimeout(() => {
      successMessage.value = ""
    }, 2500)
  }
}
const setError = (msg) => {
  errorMessage.value = msg || ""
}

onBeforeUnmount(() => {
  if (successTimer) clearTimeout(successTimer)
})

const user = ref(null)

const users = ref([])
const usersLoading = ref(false)
const usersPagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const usersSearch = ref("")
const usersFiltersExpanded = ref(false)
const usersFilters = ref({
  user_id: "",
  role: "",
  banned: "", // "", "1", "0"
})

const logs = ref([])
const logsLoading = ref(false)
const logsPagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const logsFilters = ref({ search: "", user_id: "", action: "", from: "", to: "" })
const filtersExpanded = ref(false)

const availableActions = ref([])

const roleModalOpen = ref(false)
const selectedUser = ref(null)
const selectedRole = ref("")
const userBusyId = ref(null)

const banModalOpen = ref(false)
const banTargetUser = ref(null)
const banNextValue = ref(false)

/**
 * Data loaders
 */
const loadMe = async () => {
  loading.value = true
  try {
    const res = await api.get("/user")
    user.value = res.data?.data ?? res.data
  } catch (e) {
    setError(e?.response?.data?.message || e?.message || tr("errors.default", "Something went wrong."))
  } finally {
    loading.value = false
  }
}

const loadUsers = async (page = 1) => {
  usersLoading.value = true
  setError("")
  try {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(usersPagination.value.per_page),
    })

    if (usersSearch.value.trim()) params.append("search", usersSearch.value.trim())
    if (usersFilters.value.user_id.trim()) params.append("user_id", usersFilters.value.user_id.trim())
    if (usersFilters.value.role.trim()) params.append("role", usersFilters.value.role.trim())
    if (usersFilters.value.banned !== "") params.append("banned", usersFilters.value.banned)

    const res = await api.get(`/admin/users?${params.toString()}`)
    const payload = res.data

    const list = Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : []
    const meta = payload?.meta || payload?.pagination || {
      current_page: page,
      last_page: Math.max(1, Math.ceil(list.length / usersPagination.value.per_page)),
      per_page: usersPagination.value.per_page,
      total: list.length,
    }

    users.value = list.map((u) => ({
      ...u,
      banned: typeof u.banned === "boolean" ? u.banned : Boolean(u.banned_at),
    }))

    usersPagination.value = {
      current_page: Number(meta.current_page || page),
      last_page: Number(meta.last_page || 1),
      per_page: Number(meta.per_page || usersPagination.value.per_page),
      total: Number(meta.total || list.length),
    }
  } catch (e) {
    setError(e?.response?.data?.message || e?.message || tr("admin.users.load_error", "Failed to load users."))
  } finally {
    usersLoading.value = false
  }
}

const loadLogs = async (page = 1) => {
  logsLoading.value = true
  setError("")
  try {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(logsPagination.value.per_page),
    })

    Object.entries(logsFilters.value).forEach(([k, v]) => {
      if (v && String(v).trim()) params.append(k, String(v).trim())
    })

    const res = await api.get(`/admin/logs/activity?${params.toString()}`)
    const payload = res.data

    const list = Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : []
    const meta = payload?.meta || payload?.pagination || {
      current_page: page,
      last_page: Math.max(1, Math.ceil(list.length / logsPagination.value.per_page)),
      per_page: logsPagination.value.per_page,
      total: list.length,
    }

    logs.value = list
    availableActions.value = Array.from(new Set(list.map((x) => x.action).filter(Boolean)))

    logsPagination.value = {
      current_page: Number(meta.current_page || page),
      last_page: Number(meta.last_page || 1),
      per_page: Number(meta.per_page || logsPagination.value.per_page),
      total: Number(meta.total || list.length),
    }
  } catch (e) {
    setError(e?.response?.data?.message || e?.message || tr("admin.logs.load_error", "Failed to load activity logs."))
  } finally {
    logsLoading.value = false
  }
}

const refreshAll = async () => {
  await loadMe()
  if (activeTab.value === "users") return loadUsers(usersPagination.value.current_page || 1)
  return loadLogs(logsPagination.value.current_page || 1)
}

/**
 * Users filters
 */
const clearUsersFilters = () => {
  usersSearch.value = ""
  usersFilters.value = { user_id: "", role: "", banned: "" }
  loadUsers(1)
}

/**
 * Role modal
 */
const openRoleModal = (targetUser) => {
  if (user.value?.id === targetUser.id) {
    setError(tr("admin.users.self_role_block", "You cannot change your own role."))
    return
  }
  selectedUser.value = targetUser
  selectedRole.value = targetUser.role || "user"
  roleModalOpen.value = true
}

const closeRoleModal = () => {
  roleModalOpen.value = false
  selectedUser.value = null
  selectedRole.value = ""
}

const handleUserRoleChange = async () => {
  if (!selectedUser.value) return
  userBusyId.value = selectedUser.value.id

  const prev = selectedUser.value.role
  try {
    selectedUser.value.role = selectedRole.value
    await api.patch(`/admin/users/${selectedUser.value.id}/role`, { role: selectedRole.value })
    setSuccess(tr("admin.users.role_saved", "Role updated."))
    closeRoleModal()
  } catch (e) {
    selectedUser.value.role = prev
    setError(e?.response?.data?.message || tr("errors.default", "Something went wrong."))
  } finally {
    userBusyId.value = null
  }
}

/**
 * Ban modal (no browser confirm)
 */
const openBanModal = (targetUser) => {
  if (user.value?.id === targetUser.id) return
  banTargetUser.value = targetUser
  banNextValue.value = !isBanned(targetUser)
  banModalOpen.value = true
}

const closeBanModal = () => {
  banModalOpen.value = false
  banTargetUser.value = null
  banNextValue.value = false
}

const confirmBanToggle = async () => {
  const targetUser = banTargetUser.value
  if (!targetUser) return

  userBusyId.value = targetUser.id
  const prevBannedAt = targetUser.banned_at
  const prevBanned = !!targetUser.banned
  const next = banNextValue.value

  try {
    targetUser.banned = next
    targetUser.banned_at = next ? new Date().toISOString() : null

    await api.patch(`/admin/users/${targetUser.id}/ban`, { banned: next })
    setSuccess(next ? tr("admin.users.banned", "User banned.") : tr("admin.users.unbanned", "User unbanned."))
    closeBanModal()
  } catch (e) {
    targetUser.banned_at = prevBannedAt
    targetUser.banned = prevBanned
    setError(e?.response?.data?.message || tr("errors.default", "Something went wrong."))
  } finally {
    userBusyId.value = null
  }
}

/**
 * Logs filters
 */
const clearLogFilters = () => {
  logsFilters.value = { search: "", user_id: "", action: "", from: "", to: "" }
  loadLogs(1)
}

/**
 * Tab watcher
 */
watch(activeTab, (tab) => {
  setError("")
  setSuccess("")
  if (tab === "users") loadUsers(usersPagination.value.current_page || 1)
  if (tab === "logs") loadLogs(logsPagination.value.current_page || 1)
})

onMounted(() => {
  loadMe()
  loadUsers(1)
  loadLogs(1)
})
</script>

<template>
  <div class="w-full min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-10">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div class="min-w-0">
          <div class="flex items-center gap-3">
            <Shield class="w-8 h-8 text-red-600 shrink-0" />
            <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 truncate">
              {{ tr("admin.title", "Admin panel") }}
            </h1>
          </div>
          <div class="mt-1 text-sm text-gray-500">
            {{ tr("admin.subtitle", "Manage system and users") }}
          </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
          <button type="button" :class="btnSecondary" @click="refreshAll" :disabled="loading || usersLoading || logsLoading">
            <RefreshCw :class="iconBase" />
            {{ tr("admin.actions.refresh", "Refresh") }}
          </button>
        </div>
      </div>

      <!-- Alerts -->
      <div v-if="errorMessage" class="mb-4 p-4 rounded-xl border border-red-200 bg-red-50 text-red-700">
        {{ errorMessage }}
      </div>

      <div v-if="successMessage" class="mb-4 p-4 rounded-xl border border-orange-200 bg-orange-50 text-orange-700">
        {{ successMessage }}
      </div>

      <!-- Tabs -->
      <div class="mb-6">
        <div class="border-b border-gray-200">
          <nav class="flex gap-1 overflow-x-auto" aria-label="Tabs">
            <button
                @click="activeTab = 'users'"
                :class="`px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap ${
                activeTab === 'users'
                  ? 'border-red-600 text-red-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`"
            >
              <Users class="w-4 h-4 inline mr-2 align-[-2px] shrink-0" />
              {{ tr("admin.tabs.users", "Users") }}
            </button>

            <button
                @click="activeTab = 'logs'"
                :class="`px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap ${
                activeTab === 'logs'
                  ? 'border-red-600 text-red-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`"
            >
              <FileText class="w-4 h-4 inline mr-2 align-[-2px] shrink-0" />
              {{ tr("admin.tabs.logs", "Activity logs") }}
            </button>
          </nav>
        </div>
      </div>

      <!-- Users -->
      <div v-show="activeTab === 'users'">
        <!-- Users search + filters -->
        <div class="mb-6 bg-white rounded-2xl border shadow-sm overflow-hidden">
          <div class="p-4">
            <div class="flex flex-col sm:flex-row gap-3">
              <div class="flex-1 min-w-0">
                <div class="relative">
                  <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 shrink-0" />
                  <input
                      v-model="usersSearch"
                      type="text"
                      :placeholder="tr('admin.users.search_placeholder', 'Search users...')"
                      class="w-full h-11 pl-10 pr-4 rounded-xl border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                      @keydown.enter="loadUsers(1)"
                  />
                </div>
              </div>

              <div class="flex gap-2 flex-wrap">
                <button type="button" :class="btnPrimary" @click="loadUsers(1)" :disabled="usersLoading">
                  <Search :class="iconBase" />
                  {{ tr("admin.users.search", "Search") }}
                </button>

                <button type="button" :class="btnSecondary" @click="clearUsersFilters" :disabled="usersLoading">
                  <X :class="iconBase" />
                  {{ tr("actions.clear", "Clear") }}
                </button>

                <button
                    type="button"
                    :class="btnSecondary"
                    @click="usersFiltersExpanded = !usersFiltersExpanded"
                    :disabled="usersLoading"
                >
                  <Filter :class="iconBase" />
                  {{ tr("admin.users.filters", "Filters") }}
                  <ChevronUp v-if="usersFiltersExpanded" class="h-4 w-4 shrink-0" />
                  <ChevronDown v-else class="h-4 w-4 shrink-0" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="usersFiltersExpanded" class="p-4 pt-0 border-t">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.users.user_id", "User ID") }}</label>
                <input
                    v-model="usersFilters.user_id"
                    type="text"
                    :placeholder="tr('admin.users.user_id_placeholder', 'e.g. 123')"
                    class="w-full h-10 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm"
                    @keydown.enter="loadUsers(1)"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.users.role", "Role") }}</label>
                <div class="relative">
                  <select
                      v-model="usersFilters.role"
                      class="w-full h-10 px-3 pr-8 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm appearance-none bg-white"
                  >
                    <option value="">{{ tr("admin.users.all_roles", "All roles") }}</option>
                    <option value="user">{{ tr("admin.users.role_user", "User") }}</option>
                    <option value="admin">{{ tr("admin.users.role_admin", "Admin") }}</option>
                  </select>
                  <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none shrink-0" />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.users.banned", "Banned") }}</label>
                <div class="relative">
                  <select
                      v-model="usersFilters.banned"
                      class="w-full h-10 px-3 pr-8 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm appearance-none bg-white"
                  >
                    <option value="">{{ tr("admin.users.banned_any", "Any") }}</option>
                    <option value="1">{{ tr("admin.users.banned_only", "Banned only") }}</option>
                    <option value="0">{{ tr("admin.users.not_banned_only", "Not banned only") }}</option>
                  </select>
                  <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none shrink-0" />
                </div>
              </div>
            </div>

            <div class="flex gap-2 mt-4 flex-wrap">
              <button type="button" :class="btnPrimary" @click="loadUsers(1)" :disabled="usersLoading">
                <Filter :class="iconBase" />
                {{ tr("admin.users.apply", "Apply") }}
              </button>
              <button type="button" :class="btnSecondary" @click="clearUsersFilters" :disabled="usersLoading">
                <X :class="iconBase" />
                {{ tr("actions.clear", "Clear") }}
              </button>
            </div>
          </div>
        </div>

        <!-- Users list -->
        <div v-if="usersLoading" class="text-center py-12">
          <div class="text-gray-500">{{ tr("actions.loading", "Loading...") }}</div>
        </div>

        <div v-else-if="users.length === 0" class="bg-white rounded-2xl border shadow-sm p-12">
          <div class="text-center">
            <Users class="w-12 h-12 text-gray-300 mx-auto mb-4 shrink-0" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ tr("admin.users.empty_title", "No users") }}</h3>
            <p class="text-sm text-gray-500">{{ tr("admin.users.empty_subtitle", "No users found.") }}</p>
          </div>
        </div>

        <div v-else class="space-y-4">
          <div
              v-for="targetUser in users"
              :key="targetUser.id"
              class="bg-white rounded-2xl border shadow-sm p-6 hover:shadow-md transition"
          >
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
              <div class="flex items-center gap-4 flex-1 min-w-0">
                <div
                    class="w-12 h-12 rounded-2xl bg-gradient-to-r from-gray-600 to-gray-700 text-white flex items-center justify-center shadow shrink-0"
                >
                  <span class="text-lg font-semibold">
                    {{ targetUser.name?.charAt(0)?.toUpperCase() || "U" }}
                  </span>
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1 flex-wrap min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900 truncate min-w-0">
                      {{ targetUser.name }}
                    </h3>

                    <span
                        v-if="isBanned(targetUser)"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 border border-red-200 whitespace-nowrap"
                    >
                      <Ban class="w-3 h-3 mr-1 shrink-0" />
                      {{ tr("admin.users.banned_badge", "Banned") }}
                    </span>

                    <span
                        v-if="targetUser.role"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 border border-blue-200 whitespace-nowrap"
                    >
                      {{ targetUser.role }}
                    </span>

                    <span
                        v-if="user?.id === targetUser.id"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200 whitespace-nowrap"
                    >
                      {{ tr("admin.users.you", "You") }}
                    </span>
                  </div>

                  <div class="space-y-1 text-sm text-gray-600 min-w-0">
                    <p class="min-w-0">
                      <Mail class="w-4 h-4 inline mr-2 shrink-0 align-[-2px]" />
                      <span class="break-words">{{ targetUser.email }}</span>
                    </p>

                    <p v-if="targetUser.created_at" class="whitespace-nowrap">
                      <CalendarDays class="w-4 h-4 inline mr-2 shrink-0 align-[-2px]" />
                      {{ tr("admin.users.member_since", "Member since") }}: {{ formatDate(targetUser.created_at) }}
                    </p>

                    <p v-if="targetUser.banned_at" class="text-xs text-gray-400 whitespace-nowrap">
                      {{ tr("admin.users.banned_at", "Banned at") }}: {{ formatDateTime(targetUser.banned_at) }}
                    </p>
                  </div>
                </div>
              </div>

              <div class="flex flex-wrap gap-2 justify-end">
                <button
                    type="button"
                    :class="btnSecondary"
                    @click="openRoleModal(targetUser)"
                    :disabled="userBusyId === targetUser.id || user?.id === targetUser.id"
                >
                  <UserCog :class="iconBase" />
                  {{ tr("admin.users.change_role", "Change role") }}
                </button>

                <button
                    v-if="!isBanned(targetUser)"
                    type="button"
                    :class="btnDanger"
                    @click="openBanModal(targetUser)"
                    :disabled="userBusyId === targetUser.id || user?.id === targetUser.id"
                >
                  <Ban :class="iconBase" />
                  {{ tr("admin.users.ban", "Ban") }}
                </button>

                <button
                    v-else
                    type="button"
                    :class="btnSecondary + ' border-green-200 bg-green-50 text-green-700 hover:bg-green-100'"
                    @click="openBanModal(targetUser)"
                    :disabled="userBusyId === targetUser.id || user?.id === targetUser.id"
                >
                  <CheckCircle :class="iconBase" />
                  {{ tr("admin.users.unban", "Unban") }}
                </button>
              </div>
            </div>
          </div>

          <!-- Users pagination -->
          <div v-if="usersPagination.last_page > 1" class="mt-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <div class="text-sm text-gray-600">
                {{ tr("pagination.total", "Total") }}:
                <span class="font-medium">{{ usersPagination.total }}</span>
                <span class="text-gray-300 mx-2">•</span>
                {{ tr("pagination.per_page", "Per page") }}:
                <span class="font-medium">{{ usersPagination.per_page }}</span>
              </div>

              <div class="flex items-center justify-center sm:justify-end gap-2 flex-wrap">
                <button
                    type="button"
                    :class="btnSecondary"
                    :disabled="usersPagination.current_page === 1 || usersLoading"
                    @click="loadUsers(1)"
                >
                  {{ tr("pagination.first", "First") }}
                </button>

                <button
                    type="button"
                    :class="btnSecondary"
                    :disabled="usersPagination.current_page === 1 || usersLoading"
                    @click="loadUsers(usersPagination.current_page - 1)"
                >
                  {{ tr("pagination.prev", "Previous") }}
                </button>

                <template v-for="it in usersPageItems()" :key="`u-${it}`">
                  <span v-if="it === '…'" class="px-2 text-gray-400 select-none">…</span>

                  <button
                      v-else
                      type="button"
                      class="h-10 min-w-10 px-3 rounded-xl border text-sm font-medium transition whitespace-nowrap"
                      :class="it === usersPagination.current_page
                      ? 'border-red-600 bg-red-50 text-red-700'
                      : 'border-gray-200 bg-white text-gray-900 hover:bg-gray-50'"
                      :disabled="usersLoading"
                      @click="loadUsers(Number(it))"
                  >
                    {{ it }}
                  </button>
                </template>

                <button
                    type="button"
                    :class="btnSecondary"
                    :disabled="usersPagination.current_page === usersPagination.last_page || usersLoading"
                    @click="loadUsers(usersPagination.current_page + 1)"
                >
                  {{ tr("pagination.next", "Next") }}
                </button>

                <button
                    type="button"
                    :class="btnSecondary"
                    :disabled="usersPagination.current_page === usersPagination.last_page || usersLoading"
                    @click="loadUsers(usersPagination.last_page)"
                >
                  {{ tr("pagination.last", "Last") }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Logs -->
      <div v-show="activeTab === 'logs'">
        <div class="mb-6 bg-white rounded-2xl border shadow-sm overflow-hidden">
          <button
              @click="filtersExpanded = !filtersExpanded"
              class="w-full p-4 flex items-center justify-between hover:bg-gray-50 transition"
          >
            <div class="flex items-center gap-3 min-w-0">
              <Filter class="w-5 h-5 text-gray-500 shrink-0" />
              <h3 class="font-semibold text-gray-900 truncate">{{ tr("admin.logs.filters", "Filters") }}</h3>
              <span
                  v-if="logsFilters.search || logsFilters.user_id || logsFilters.action || logsFilters.from || logsFilters.to"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 border border-red-200 whitespace-nowrap"
              >
                {{ tr("admin.logs.active", "Active") }}
              </span>
            </div>
            <ChevronUp v-if="filtersExpanded" class="w-5 h-5 text-gray-500 shrink-0" />
            <ChevronDown v-else class="w-5 h-5 text-gray-500 shrink-0" />
          </button>

          <div v-if="filtersExpanded" class="p-4 pt-0 border-t">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.logs.search", "Search") }}</label>
                <input
                    v-model="logsFilters.search"
                    type="text"
                    :placeholder="tr('admin.logs.search_placeholder', 'Search logs...')"
                    class="w-full h-10 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm"
                    @keydown.enter="loadLogs(1)"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.logs.user_id", "User ID") }}</label>
                <input
                    v-model="logsFilters.user_id"
                    type="text"
                    :placeholder="tr('admin.logs.user_id_placeholder', 'e.g. 123')"
                    class="w-full h-10 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm"
                    @keydown.enter="loadLogs(1)"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.logs.action", "Action") }}</label>
                <div class="relative">
                  <select
                      v-model="logsFilters.action"
                      class="w-full h-10 px-3 pr-8 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm appearance-none bg-white"
                  >
                    <option value="">{{ tr("admin.logs.all_actions", "All actions") }}</option>
                    <option v-for="action in availableActions" :key="action" :value="action">
                      {{ formatAction(action) }}
                    </option>
                  </select>
                  <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none shrink-0" />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.logs.from", "From") }}</label>
                <input
                    v-model="logsFilters.from"
                    type="date"
                    class="w-full h-10 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.logs.to", "To") }}</label>
                <input
                    v-model="logsFilters.to"
                    type="date"
                    class="w-full h-10 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm"
                />
              </div>
            </div>

            <div class="flex gap-2 mt-4 flex-wrap">
              <button type="button" :class="btnPrimary" @click="loadLogs(1)" :disabled="logsLoading">
                <Filter :class="iconBase" />
                {{ tr("admin.logs.apply", "Apply") }}
              </button>
              <button type="button" :class="btnSecondary" @click="clearLogFilters" :disabled="logsLoading">
                <X :class="iconBase" />
                {{ tr("actions.clear", "Clear") }}
              </button>
            </div>
          </div>
        </div>

        <div v-if="logsLoading" class="text-center py-12">
          <div class="text-gray-500">{{ tr("actions.loading", "Loading...") }}</div>
        </div>

        <div v-else-if="logs.length === 0" class="bg-white rounded-2xl border shadow-sm p-12">
          <div class="text-center">
            <FileText class="w-12 h-12 text-gray-300 mx-auto mb-4 shrink-0" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ tr("admin.logs.empty_title", "No logs") }}</h3>
            <p class="text-sm text-gray-500">{{ tr("admin.logs.empty_subtitle", "No activity logs found.") }}</p>
          </div>
        </div>

        <div v-else class="space-y-3">
          <div v-for="log in logs" :key="log.id" class="bg-white rounded-2xl border shadow-sm p-4 hover:shadow-md transition">
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2 flex-wrap">
                  <span :class="`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border ${getLevelBadgeColor(log.level)}`">
                    {{ log.level || "info" }}
                  </span>
                  <span class="text-xs text-gray-400 whitespace-nowrap">
                    <Calendar class="w-3 h-3 inline mr-1 shrink-0 align-[-2px]" />
                    {{ formatDateTime(log.created_at) }}
                  </span>
                </div>

                <div class="space-y-1 min-w-0">
                  <div class="font-medium text-gray-900 break-words">
                    <Activity class="w-4 h-4 inline mr-2 text-gray-500 shrink-0 align-[-2px]" />
                    {{ formatAction(log.action) || log.description || "—" }}
                  </div>

                  <div v-if="log.user || log.user_id" class="text-sm text-gray-600 break-words">
                    {{ tr("admin.logs.user", "User") }}:
                    <span class="font-medium">
                      {{ log.user?.name || `${tr("admin.logs.user_id_short", "ID")}: ${log.user_id}` }}
                    </span>
                  </div>

                  <div v-if="log.description && log.action !== log.description" class="text-sm text-gray-500 break-words">
                    {{ log.description }}
                  </div>

                  <div v-if="log.ip_address" class="text-xs text-gray-400 break-words">
                    IP: {{ log.ip_address }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Logs pagination -->
          <div v-if="logsPagination.last_page > 1" class="mt-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <div class="text-sm text-gray-600">
                {{ tr("pagination.total", "Total") }}:
                <span class="font-medium">{{ logsPagination.total }}</span>
                <span class="text-gray-300 mx-2">•</span>
                {{ tr("pagination.per_page", "Per page") }}:
                <span class="font-medium">{{ logsPagination.per_page }}</span>
              </div>

              <div class="flex items-center justify-center sm:justify-end gap-2 flex-wrap">
                <button
                    type="button"
                    :class="btnSecondary"
                    :disabled="logsPagination.current_page === 1 || logsLoading"
                    @click="loadLogs(1)"
                >
                  {{ tr("pagination.first", "First") }}
                </button>

                <button
                    type="button"
                    :class="btnSecondary"
                    :disabled="logsPagination.current_page === 1 || logsLoading"
                    @click="loadLogs(logsPagination.current_page - 1)"
                >
                  {{ tr("pagination.prev", "Previous") }}
                </button>

                <template v-for="it in logsPageItems()" :key="`l-${it}`">
                  <span v-if="it === '…'" class="px-2 text-gray-400 select-none">…</span>

                  <button
                      v-else
                      type="button"
                      class="h-10 min-w-10 px-3 rounded-xl border text-sm font-medium transition whitespace-nowrap"
                      :class="it === logsPagination.current_page
                      ? 'border-red-600 bg-red-50 text-red-700'
                      : 'border-gray-200 bg-white text-gray-900 hover:bg-gray-50'"
                      :disabled="logsLoading"
                      @click="loadLogs(Number(it))"
                  >
                    {{ it }}
                  </button>
                </template>

                <button
                    type="button"
                    :class="btnSecondary"
                    :disabled="logsPagination.current_page === logsPagination.last_page || logsLoading"
                    @click="loadLogs(logsPagination.current_page + 1)"
                >
                  {{ tr("pagination.next", "Next") }}
                </button>

                <button
                    type="button"
                    :class="btnSecondary"
                    :disabled="logsPagination.current_page === logsPagination.last_page || logsLoading"
                    @click="loadLogs(logsPagination.last_page)"
                >
                  {{ tr("pagination.last", "Last") }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Role modal -->
      <div v-if="roleModalOpen && selectedUser" class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
        <button class="absolute inset-0 bg-black/60" @click="closeRoleModal" aria-label="Close" :disabled="userBusyId === selectedUser.id" />
        <div class="relative w-full max-w-lg rounded-2xl border bg-white shadow-2xl text-gray-900 overflow-hidden">
          <div class="p-6">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <h3 class="text-xl font-semibold">{{ tr("admin.users.role_title", "Change user role") }}</h3>
                <div class="mt-1 text-sm text-gray-500">
                  {{ tr("admin.users.role_for", "Change role for") }}:
                  <strong class="break-words">{{ selectedUser?.name }}</strong>
                </div>
              </div>
              <button
                  type="button"
                  class="h-10 w-10 rounded-xl bg-gray-50 border hover:bg-gray-100 transition flex items-center justify-center disabled:opacity-50 shrink-0"
                  @click="closeRoleModal"
                  :disabled="userBusyId === selectedUser.id"
              >
                <X :class="iconBase" />
              </button>
            </div>

            <div class="mt-5 space-y-3">
              <label
                  v-for="role in [
                  { value: 'user', label: tr('admin.users.role_user', 'User'), desc: tr('admin.users.role_user_desc', 'Basic user without special permissions') },
                  { value: 'admin', label: tr('admin.users.role_admin', 'Admin'), desc: tr('admin.users.role_admin_desc', 'Full access to the system') },
                ]"
                  :key="role.value"
                  :class="`block p-4 rounded-xl border-2 cursor-pointer transition ${
                  selectedRole === role.value ? 'border-gray-900 bg-gray-50' : 'border-gray-200 bg-white hover:bg-gray-50'
                } ${userBusyId === selectedUser.id ? 'opacity-50 cursor-not-allowed' : ''}`"
              >
                <div class="flex items-start gap-3">
                  <input
                      type="radio"
                      :value="role.value"
                      v-model="selectedRole"
                      :disabled="userBusyId === selectedUser.id"
                      class="mt-1 h-4 w-4 shrink-0"
                  />
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                      <UserCog class="h-4 w-4 shrink-0" />
                      <span class="font-semibold">{{ role.label }}</span>
                    </div>
                    <div class="mt-1 text-sm text-gray-500 break-words">{{ role.desc }}</div>
                  </div>
                </div>
              </label>
            </div>

            <div class="pt-4 flex flex-col sm:flex-row gap-2 sm:justify-end mt-4">
              <button type="button" :class="btnSecondary" @click="closeRoleModal" :disabled="userBusyId === selectedUser.id">
                {{ tr("actions.cancel", "Cancel") }}
              </button>
              <button type="button" :class="btnPrimary" @click="handleUserRoleChange" :disabled="userBusyId === selectedUser.id || !selectedRole">
                <Save :class="iconBase" />
                {{ tr("actions.save", "Save") }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Ban modal -->
      <div v-if="banModalOpen && banTargetUser" class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
        <button class="absolute inset-0 bg-black/60" @click="closeBanModal" aria-label="Close" :disabled="userBusyId === banTargetUser.id" />
        <div class="relative w-full max-w-lg rounded-2xl border bg-white shadow-2xl text-gray-900 overflow-hidden">
          <div class="p-6">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <h3 class="text-xl font-semibold">
                  {{ banNextValue ? tr("admin.users.ban_title", "Ban user") : tr("admin.users.unban_title", "Unban user") }}
                </h3>
                <div class="mt-1 text-sm text-gray-500 break-words">
                  {{ tr("admin.users.ban_confirm_for", "Confirm action for") }}:
                  <strong>{{ banTargetUser?.name }}</strong>
                </div>
              </div>
              <button
                  type="button"
                  class="h-10 w-10 rounded-xl bg-gray-50 border hover:bg-gray-100 transition flex items-center justify-center disabled:opacity-50 shrink-0"
                  @click="closeBanModal"
                  :disabled="userBusyId === banTargetUser.id"
              >
                <X :class="iconBase" />
              </button>
            </div>

            <div class="mt-4 text-sm text-gray-700">
              <p v-if="banNextValue">
                {{ tr("admin.users.ban_confirm_text", "This user will be blocked from accessing the application.") }}
              </p>
              <p v-else>
                {{ tr("admin.users.unban_confirm_text", "This user will regain access to the application.") }}
              </p>
            </div>

            <div class="pt-5 flex flex-col sm:flex-row gap-2 sm:justify-end">
              <button type="button" :class="btnSecondary" @click="closeBanModal" :disabled="userBusyId === banTargetUser.id">
                {{ tr("actions.cancel", "Cancel") }}
              </button>
              <button
                  type="button"
                  :class="banNextValue ? btnDanger : (btnSecondary + ' border-green-200 bg-green-50 text-green-700 hover:bg-green-100')"
                  @click="confirmBanToggle"
                  :disabled="userBusyId === banTargetUser.id"
              >
                <Ban v-if="banNextValue" :class="iconBase" />
                <CheckCircle v-else :class="iconBase" />
                {{ banNextValue ? tr("admin.users.ban", "Ban") : tr("admin.users.unban", "Unban") }}
              </button>
            </div>
          </div>
        </div>
      </div>
      <!-- /modals -->
    </div>
  </div>
</template>
