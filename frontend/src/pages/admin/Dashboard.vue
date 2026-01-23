<script setup>
import { ref, onMounted } from "vue"
import { useI18n } from "vue-i18n"
import api from "@/composables/api/api"

import {
  Mail,
  CalendarDays,
  Pencil,
  Save,
  X,
  RefreshCw,
  KeyRound,
  Eye,
  EyeOff,
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
} from "lucide-vue-next"

const { t, te } = useI18n({ useScope: "global" })
function tr(key, fallback) {
  return te(key) ? t(key) : fallback
}

const btnBase =
    "inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
const btnPrimary =
    btnBase + " bg-gradient-to-r from-red-600 to-orange-600 text-white hover:opacity-90 active:opacity-80 shadow"
const btnSecondary = btnBase + " border border-gray-200 bg-white text-gray-900 hover:bg-gray-50"
const btnDanger = btnBase + " border border-red-200 bg-red-50 text-red-700 hover:bg-red-100"

const getInitials = (name) => {
  const parts = (name || "").trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return "A"
  const a = parts[0]?.[0] || ""
  const b = parts.length > 1 ? parts[parts.length - 1]?.[0] : ""
  return (a + b).toUpperCase()
}

const formatDate = (dateString) => {
  if (!dateString) return "—"
  try {
    return new Intl.DateTimeFormat("pl-PL", { year: "numeric", month: "short", day: "2-digit" }).format(new Date(dateString))
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

const activeTab = ref("profile")
const loading = ref(false)
const errorMessage = ref("")
const successMessage = ref("")

const user = ref(null)

const users = ref([])
const usersLoading = ref(false)
const usersPagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const usersSearch = ref("")

const logs = ref([])
const logsLoading = ref(false)
const logsPagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const logsFilters = ref({ search: "", user_id: "", action: "", level: "", from: "", to: "" })

const availableActions = ref([])
const availableLevels = ref(["info", "warning", "error", "critical", "success"])

const editOpen = ref(false)
const editName = ref("")
const editEmail = ref("")

const passOpen = ref(false)
const currentPassword = ref("")
const newPassword = ref("")
const newPassword2 = ref("")
const showCurrent = ref(false)
const showNew = ref(false)
const showNew2 = ref(false)
const passLoading = ref(false)
const passError = ref("")

const roleModalOpen = ref(false)
const selectedUser = ref(null)
const selectedRole = ref("")
const userBusyId = ref(null)

const filtersExpanded = ref(false)

const loadMe = async () => {
  errorMessage.value = ""
  successMessage.value = ""
  loading.value = true
  try {
    const res = await api.get("/user")
    user.value = res.data?.data ?? res.data
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || e?.message || tr("errors.default", "Something went wrong.")
  } finally {
    loading.value = false
  }
}

const loadUsers = async (page = 1) => {
  usersLoading.value = true
  try {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(usersPagination.value.per_page),
    })
    if (usersSearch.value.trim()) params.append("search", usersSearch.value.trim())

    const res = await api.get(`/admin/users?${params.toString()}`)
    const payload = res.data?.data ?? res.data

    const list = Array.isArray(payload) ? payload : payload.data || []
    const meta = payload.meta || payload.pagination || {
      current_page: page,
      last_page: Math.max(1, Math.ceil(list.length / usersPagination.value.per_page)),
      per_page: usersPagination.value.per_page,
      total: list.length,
    }

    users.value = list
    usersPagination.value = {
      current_page: meta.current_page || page,
      last_page: meta.last_page || 1,
      per_page: meta.per_page || usersPagination.value.per_page,
      total: meta.total || list.length,
    }
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || e?.message || tr("admin.users.load_error", "Failed to load users.")
  } finally {
    usersLoading.value = false
  }
}

const loadLogs = async (page = 1) => {
  logsLoading.value = true
  try {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(logsPagination.value.per_page),
    })

    Object.entries(logsFilters.value).forEach(([k, v]) => {
      if (v && String(v).trim()) params.append(k, String(v).trim())
    })

    const res = await api.get(`/admin/logs/activity?${params.toString()}`)
    const payload = res.data?.data ?? res.data

    const list = Array.isArray(payload) ? payload : payload.data || []
    const meta = payload.meta || payload.pagination || {
      current_page: page,
      last_page: Math.max(1, Math.ceil(list.length / logsPagination.value.per_page)),
      per_page: logsPagination.value.per_page,
      total: list.length,
    }

    logs.value = list
    availableActions.value = Array.from(new Set(list.map((x) => x.action).filter(Boolean)))

    logsPagination.value = {
      current_page: meta.current_page || page,
      last_page: meta.last_page || 1,
      per_page: meta.per_page || logsPagination.value.per_page,
      total: meta.total || list.length,
    }
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || e?.message || tr("admin.logs.load_error", "Failed to load activity logs.")
  } finally {
    logsLoading.value = false
  }
}

const refreshAll = async () => {
  await loadMe()
  if (activeTab.value === "users") return loadUsers(usersPagination.value.current_page)
  if (activeTab.value === "logs") return loadLogs(logsPagination.value.current_page)
}

const openEditModal = () => {
  editName.value = user.value?.name || ""
  editEmail.value = user.value?.email || ""
  editOpen.value = true
}

const closeEditModal = () => {
  editOpen.value = false
  editName.value = ""
  editEmail.value = ""
}

const handleEditProfileSave = async () => {
  const name = editName.value.trim()
  const email = editEmail.value.trim()
  if (!name || !email) return

  loading.value = true
  errorMessage.value = ""
  successMessage.value = ""
  try {
    await api.put("/user/profile", { name, email })
    if (user.value) {
      user.value.name = name
      user.value.email = email
    }
    successMessage.value = tr("admin.profile.updated", "Profile updated.")
    closeEditModal()
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || tr("errors.default", "Something went wrong.")
  } finally {
    loading.value = false
  }
}

const openPasswordModal = () => {
  passOpen.value = true
}

const closePasswordModal = () => {
  passOpen.value = false
  currentPassword.value = ""
  newPassword.value = ""
  newPassword2.value = ""
  showCurrent.value = false
  showNew.value = false
  showNew2.value = false
  passError.value = ""
}

const handleChangePasswordSave = async () => {
  passError.value = ""
  if (!currentPassword.value || !newPassword.value || !newPassword2.value) return
  if (newPassword.value !== newPassword2.value) return (passError.value = tr("admin.profile.pass_mismatch", "Passwords do not match."))
  if (newPassword.value.length < 8) return (passError.value = tr("admin.profile.pass_min", "Password must be at least 8 characters."))

  passLoading.value = true
  try {
    await api.put("/user/password", {
      current_password: currentPassword.value,
      password: newPassword.value,
      password_confirmation: newPassword2.value,
    })
    successMessage.value = tr("admin.profile.pass_changed", "Password changed.")
    closePasswordModal()
  } catch (e) {
    passError.value = e?.response?.data?.message || tr("errors.default", "Something went wrong.")
  } finally {
    passLoading.value = false
  }
}

const toggleUserBan = async (targetUser) => {
  const msg = targetUser.banned
      ? tr("admin.users.confirm_unban", "Are you sure you want to unban this user?")
      : tr("admin.users.confirm_ban", "Are you sure you want to ban this user?")

  if (!confirm(msg)) return

  userBusyId.value = targetUser.id
  const prev = !!targetUser.banned
  const next = !prev

  try {
    targetUser.banned = next
    await api.patch(`/admin/users/${targetUser.id}/ban`, { banned: next })
    successMessage.value = next ? tr("admin.users.banned", "User banned.") : tr("admin.users.unbanned", "User unbanned.")
  } catch (e) {
    targetUser.banned = prev
    errorMessage.value = e?.response?.data?.message || tr("errors.default", "Something went wrong.")
  } finally {
    userBusyId.value = null
  }
}

const openRoleModal = (targetUser) => {
  if (user.value?.id === targetUser.id) {
    errorMessage.value = tr("admin.users.self_role_block", "You cannot change your own role.")
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
    successMessage.value = tr("admin.users.role_saved", "Role updated.")
    closeRoleModal()
  } catch (e) {
    selectedUser.value.role = prev
    errorMessage.value = e?.response?.data?.message || tr("errors.default", "Something went wrong.")
  } finally {
    userBusyId.value = null
  }
}

const clearLogFilters = () => {
  logsFilters.value = { search: "", user_id: "", action: "", level: "", from: "", to: "" }
  loadLogs(1)
}

onMounted(() => {
  loadMe()
  loadUsers()
  loadLogs()
})
</script>

<template>
  <div class="w-full min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-10">
      <div class="flex items-start justify-between gap-4 mb-6">
        <div class="min-w-0">
          <div class="flex items-center gap-3">
            <Shield class="w-8 h-8 text-red-600" />
            <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900">
              {{ tr("admin.title", "Admin panel") }}
            </h1>
          </div>
          <div class="mt-1 text-sm text-gray-500">
            {{ tr("admin.subtitle", "Manage system and users") }}
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button type="button" :class="btnSecondary" @click="refreshAll" :disabled="loading || usersLoading || logsLoading">
            <RefreshCw class="h-4 w-4" />
            {{ tr("admin.actions.refresh", "Refresh") }}
          </button>
        </div>
      </div>

      <div v-if="errorMessage" class="mb-4 p-4 rounded-xl border border-red-200 bg-red-50 text-red-700">
        {{ errorMessage }}
      </div>

      <div v-if="successMessage" class="mb-4 p-4 rounded-xl border border-orange-200 bg-orange-50 text-orange-700">
        {{ successMessage }}
      </div>

      <div class="mb-6">
        <div class="border-b border-gray-200">
          <nav class="flex gap-1 overflow-x-auto" aria-label="Tabs">
            <button
                @click="activeTab = 'profile'"
                :class="`px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap ${
                activeTab === 'profile'
                  ? 'border-red-600 text-red-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`"
            >
              <Shield class="w-4 h-4 inline mr-2" />
              {{ tr("admin.tabs.profile", "Profile") }}
            </button>

            <button
                @click="activeTab = 'users'"
                :class="`px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap ${
                activeTab === 'users'
                  ? 'border-red-600 text-red-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`"
            >
              <Users class="w-4 h-4 inline mr-2" />
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
              <FileText class="w-4 h-4 inline mr-2" />
              {{ tr("admin.tabs.logs", "Activity logs") }}
            </button>
          </nav>
        </div>
      </div>

      <div v-show="activeTab === 'profile'" class="max-w-4xl">
        <section class="bg-white rounded-2xl border shadow-sm overflow-hidden">
          <div class="p-6 border-b">
            <div class="flex items-start justify-between gap-4">
              <div class="flex items-center gap-4 min-w-0">
                <div
                    class="h-14 w-14 rounded-2xl bg-gradient-to-r from-red-600 to-orange-600 text-white flex items-center justify-center shadow"
                    :title="user?.name"
                >
                  <span class="text-lg font-semibold">{{ getInitials(user?.name || '') }}</span>
                </div>

                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <div class="text-xl font-semibold text-gray-900 truncate">{{ user?.name || "—" }}</div>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700 border border-red-200">
                      <Shield class="w-3 h-3 mr-1" />
                      {{ tr("admin.badge.admin", "Admin") }}
                    </span>
                  </div>

                  <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                    <span class="inline-flex items-center gap-1">
                      <Mail class="h-4 w-4" />
                      {{ user?.email || "—" }}
                    </span>

                    <span v-if="user?.created_at" class="inline-flex items-center gap-1">
                      <span class="text-gray-300">•</span>
                      <CalendarDays class="h-4 w-4" />
                      {{ tr("admin.profile.joined", "Joined") }} {{ formatDate(user.created_at) }}
                    </span>
                  </div>
                </div>
              </div>

              <button type="button" :class="btnSecondary" @click="openEditModal" :disabled="loading || !user">
                <Pencil class="h-4 w-4" />
                {{ tr("admin.profile.edit", "Edit") }}
              </button>
            </div>
          </div>

          <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="p-4 rounded-2xl border bg-gray-50">
                <div class="text-xs text-gray-500">{{ tr("admin.profile.user_id", "User ID") }}</div>
                <div class="mt-1 font-semibold text-gray-900">{{ user?.id ?? "—" }}</div>
              </div>

              <div class="p-4 rounded-2xl border bg-gray-50">
                <div class="text-xs text-gray-500">{{ tr("admin.profile.email", "Email") }}</div>
                <div class="mt-1 font-semibold text-gray-900">{{ user?.email ?? "—" }}</div>
              </div>
            </div>

            <div class="mt-6 flex flex-col sm:flex-row gap-2 sm:justify-end">
              <button type="button" :class="btnSecondary" @click="openPasswordModal" :disabled="loading">
                <KeyRound class="h-4 w-4" />
                {{ tr("admin.profile.change_password", "Change password") }}
              </button>
            </div>
          </div>
        </section>
      </div>

      <div v-show="activeTab === 'users'">
        <div class="mb-6 bg-white rounded-2xl border shadow-sm p-4">
          <div class="flex gap-3">
            <div class="flex-1">
              <div class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                    v-model="usersSearch"
                    type="text"
                    :placeholder="tr('admin.users.search_placeholder','Search users...')"
                    class="w-full h-11 pl-10 pr-4 rounded-xl border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                    @keydown.enter="loadUsers(1)"
                />
              </div>
            </div>

            <button type="button" :class="btnPrimary" @click="loadUsers(1)" :disabled="usersLoading">
              <Search class="h-4 w-4" />
              {{ tr("admin.users.search", "Search") }}
            </button>
          </div>
        </div>

        <div v-if="usersLoading" class="text-center py-12">
          <div class="text-gray-500">{{ tr("actions.loading","Loading...") }}</div>
        </div>

        <div v-else-if="users.length === 0" class="bg-white rounded-2xl border shadow-sm p-12">
          <div class="text-center">
            <Users class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ tr("admin.users.empty_title","No users") }}</h3>
            <p class="text-sm text-gray-500">{{ tr("admin.users.empty_subtitle","No users found.") }}</p>
          </div>
        </div>

        <div v-else class="space-y-4">
          <div
              v-for="targetUser in users"
              :key="targetUser.id"
              class="bg-white rounded-2xl border shadow-sm p-6 hover:shadow-md transition"
          >
            <div class="flex items-start justify-between">
              <div class="flex items-center gap-4 flex-1">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-r from-gray-600 to-gray-700 text-white flex items-center justify-center shadow">
                  <span class="text-lg font-semibold">{{ (targetUser.name?.charAt(0)?.toUpperCase() || 'U') }}</span>
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <h3 class="text-lg font-semibold text-gray-900 truncate">{{ targetUser.name }}</h3>

                    <span
                        v-if="targetUser.banned"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 border border-red-200"
                    >
                      <Ban class="w-3 h-3 mr-1" />
                      {{ tr("admin.users.banned_badge","Banned") }}
                    </span>

                    <span
                        v-if="targetUser.role"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 border border-blue-200"
                    >
                      {{ targetUser.role }}
                    </span>
                  </div>

                  <div class="space-y-1 text-sm text-gray-600">
                    <p>
                      <Mail class="w-4 h-4 inline mr-2" />
                      {{ targetUser.email }}
                    </p>

                    <p v-if="targetUser.created_at">
                      <CalendarDays class="w-4 h-4 inline mr-2" />
                      {{ tr("admin.users.member_since","Member since") }}: {{ formatDate(targetUser.created_at) }}
                    </p>
                  </div>
                </div>
              </div>

              <div class="flex flex-col gap-2 ml-4">
                <button
                    type="button"
                    :class="btnSecondary"
                    @click="openRoleModal(targetUser)"
                    :disabled="userBusyId === targetUser.id || user?.id === targetUser.id"
                >
                  <UserCog class="h-4 w-4" />
                  {{ tr("admin.users.change_role","Change role") }}
                </button>

                <button
                    v-if="!targetUser.banned"
                    type="button"
                    :class="btnDanger"
                    @click="toggleUserBan(targetUser)"
                    :disabled="userBusyId === targetUser.id"
                >
                  <Ban class="h-4 w-4" />
                  {{ tr("admin.users.ban","Ban") }}
                </button>

                <button
                    v-else
                    type="button"
                    :class="btnSecondary + ' border-green-200 bg-green-50 text-green-700 hover:bg-green-100'"
                    @click="toggleUserBan(targetUser)"
                    :disabled="userBusyId === targetUser.id"
                >
                  <CheckCircle class="h-4 w-4" />
                  {{ tr("admin.users.unban","Unban") }}
                </button>
              </div>
            </div>
          </div>

          <div v-if="usersPagination.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
            <button
                :class="btnSecondary"
                :disabled="usersPagination.current_page === 1 || usersLoading"
                @click="loadUsers(usersPagination.current_page - 1)"
            >
              {{ tr("pagination.prev","Previous") }}
            </button>

            <span class="px-4 py-2 text-sm text-gray-600">
              {{ tr("pagination.page","Page") }} {{ usersPagination.current_page }} {{ tr("pagination.of","of") }} {{ usersPagination.last_page }}
            </span>

            <button
                :class="btnSecondary"
                :disabled="usersPagination.current_page === usersPagination.last_page || usersLoading"
                @click="loadUsers(usersPagination.current_page + 1)"
            >
              {{ tr("pagination.next","Next") }}
            </button>
          </div>
        </div>
      </div>

      <div v-show="activeTab === 'logs'">
        <div class="mb-6 bg-white rounded-2xl border shadow-sm overflow-hidden">
          <button
              @click="filtersExpanded = !filtersExpanded"
              class="w-full p-4 flex items-center justify-between hover:bg-gray-50 transition"
          >
            <div class="flex items-center gap-3">
              <Filter class="w-5 h-5 text-gray-500" />
              <h3 class="font-semibold text-gray-900">{{ tr("admin.logs.filters","Filters") }}</h3>
              <span
                  v-if="logsFilters.search || logsFilters.user_id || logsFilters.action || logsFilters.level || logsFilters.from || logsFilters.to"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 border border-red-200"
              >
                {{ tr("admin.logs.active","Active") }}
              </span>
            </div>
            <ChevronUp v-if="filtersExpanded" class="w-5 h-5 text-gray-500" />
            <ChevronDown v-else class="w-5 h-5 text-gray-500" />
          </button>

          <div v-if="filtersExpanded" class="p-4 pt-0 border-t">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.logs.search","Search") }}</label>
                <input
                    v-model="logsFilters.search"
                    type="text"
                    :placeholder="tr('admin.logs.search_placeholder','Search logs...')"
                    class="w-full h-10 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.logs.user_id","User ID") }}</label>
                <input
                    v-model="logsFilters.user_id"
                    type="text"
                    :placeholder="tr('admin.logs.user_id_placeholder','e.g. 123')"
                    class="w-full h-10 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.logs.action","Action") }}</label>
                <div class="relative">
                  <select
                      v-model="logsFilters.action"
                      class="w-full h-10 px-3 pr-8 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm appearance-none bg-white"
                  >
                    <option value="">{{ tr("admin.logs.all_actions","All actions") }}</option>
                    <option v-for="action in availableActions" :key="action" :value="action">
                      {{ formatAction(action) }}
                    </option>
                  </select>
                  <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.logs.level","Level") }}</label>
                <div class="relative">
                  <select
                      v-model="logsFilters.level"
                      class="w-full h-10 px-3 pr-8 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm appearance-none bg-white"
                  >
                    <option value="">{{ tr("admin.logs.all_levels","All levels") }}</option>
                    <option v-for="level in availableLevels" :key="level" :value="level">{{ level }}</option>
                  </select>
                  <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.logs.from","From") }}</label>
                <input
                    v-model="logsFilters.from"
                    type="date"
                    class="w-full h-10 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ tr("admin.logs.to","To") }}</label>
                <input
                    v-model="logsFilters.to"
                    type="date"
                    class="w-full h-10 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm"
                />
              </div>
            </div>

            <div class="flex gap-2 mt-4">
              <button type="button" :class="btnPrimary" @click="loadLogs(1)" :disabled="logsLoading">
                <Filter class="h-4 w-4" />
                {{ tr("admin.logs.apply","Apply") }}
              </button>
              <button type="button" :class="btnSecondary" @click="clearLogFilters" :disabled="logsLoading">
                <X class="h-4 w-4" />
                {{ tr("admin.logs.clear","Clear") }}
              </button>
            </div>
          </div>
        </div>

        <div v-if="logsLoading" class="text-center py-12">
          <div class="text-gray-500">{{ tr("actions.loading","Loading...") }}</div>
        </div>

        <div v-else-if="logs.length === 0" class="bg-white rounded-2xl border shadow-sm p-12">
          <div class="text-center">
            <FileText class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ tr("admin.logs.empty_title","No logs") }}</h3>
            <p class="text-sm text-gray-500">{{ tr("admin.logs.empty_subtitle","No activity logs found.") }}</p>
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
                  <span class="text-xs text-gray-400">
                    <Calendar class="w-3 h-3 inline mr-1" />
                    {{ formatDateTime(log.created_at) }}
                  </span>
                </div>

                <div class="space-y-1">
                  <div class="font-medium text-gray-900">
                    <Activity class="w-4 h-4 inline mr-2 text-gray-500" />
                    {{ formatAction(log.action) || log.description || "—" }}
                  </div>

                  <div v-if="log.user || log.user_id" class="text-sm text-gray-600">
                    {{ tr("admin.logs.user","User") }}:
                    <span class="font-medium">{{ log.user?.name || `${tr("admin.logs.user_id_short","ID")}: ${log.user_id}` }}</span>
                  </div>

                  <div v-if="log.description && log.action !== log.description" class="text-sm text-gray-500">
                    {{ log.description }}
                  </div>

                  <div v-if="log.ip_address" class="text-xs text-gray-400">
                    IP: {{ log.ip_address }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="logsPagination.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
            <button
                :class="btnSecondary"
                :disabled="logsPagination.current_page === 1 || logsLoading"
                @click="loadLogs(logsPagination.current_page - 1)"
            >
              {{ tr("pagination.prev","Previous") }}
            </button>

            <span class="px-4 py-2 text-sm text-gray-600">
              {{ tr("pagination.page","Page") }} {{ logsPagination.current_page }} {{ tr("pagination.of","of") }} {{ logsPagination.last_page }}
            </span>

            <button
                :class="btnSecondary"
                :disabled="logsPagination.current_page === logsPagination.last_page || logsLoading"
                @click="loadLogs(logsPagination.current_page + 1)"
            >
              {{ tr("pagination.next","Next") }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="editOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
        <button class="absolute inset-0 bg-black/60" @click="closeEditModal" aria-label="Close" :disabled="loading" />
        <div class="relative w-full max-w-lg rounded-2xl border bg-white shadow-2xl text-gray-900 overflow-hidden">
          <div class="p-6">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <h3 class="text-xl font-semibold">{{ tr("admin.profile.edit_title","Edit profile") }}</h3>
                <div class="mt-1 text-sm text-gray-500">{{ tr("admin.profile.edit_subtitle","Update your name and email.") }}</div>
              </div>
              <button type="button" class="h-10 w-10 rounded-xl bg-gray-50 border hover:bg-gray-100 transition flex items-center justify-center disabled:opacity-50" @click="closeEditModal" :disabled="loading">
                <X class="h-4 w-4" />
              </button>
            </div>

            <div class="mt-5 space-y-4">
              <div>
                <label class="block text-sm font-medium mb-1">{{ tr("admin.profile.name","Name") }}</label>
                <input v-model="editName" type="text" class="w-full h-11 px-4 rounded-xl border border-gray-200 bg-white outline-none focus:ring-2 focus:ring-red-500/20" :disabled="loading" />
              </div>

              <div>
                <label class="block text-sm font-medium mb-1">{{ tr("admin.profile.email","Email") }}</label>
                <input v-model="editEmail" type="email" class="w-full h-11 px-4 rounded-xl border border-gray-200 bg-white outline-none focus:ring-2 focus:ring-red-500/20" :disabled="loading" />
              </div>

              <div class="pt-2 flex flex-col sm:flex-row gap-2 sm:justify-end">
                <button type="button" :class="btnSecondary" @click="closeEditModal" :disabled="loading">
                  {{ tr("actions.cancel","Cancel") }}
                </button>

                <button type="button" :class="btnPrimary" @click="handleEditProfileSave" :disabled="loading || !editName.trim() || !editEmail.trim()">
                  <Save class="h-4 w-4" />
                  {{ tr("actions.save","Save") }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="passOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
        <button class="absolute inset-0 bg-black/60" @click="closePasswordModal" aria-label="Close" :disabled="passLoading" />
        <div class="relative w-full max-w-lg rounded-2xl border bg-white shadow-2xl text-gray-900 overflow-hidden">
          <div class="p-6">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <h3 class="text-xl font-semibold">{{ tr("admin.profile.pass_title","Change password") }}</h3>
                <div class="mt-1 text-sm text-gray-500">{{ tr("admin.profile.pass_subtitle","Enter current password and choose a new one.") }}</div>
              </div>
              <button type="button" class="h-10 w-10 rounded-xl bg-gray-50 border hover:bg-gray-100 transition flex items-center justify-center disabled:opacity-50" @click="closePasswordModal" :disabled="passLoading">
                <X class="h-4 w-4" />
              </button>
            </div>

            <div class="mt-5 space-y-4">
              <div v-if="passError" class="p-3 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm">
                {{ passError }}
              </div>

              <div>
                <label class="block text-sm font-medium mb-1">{{ tr("admin.profile.current_password","Current password") }}</label>
                <div class="relative">
                  <input v-model="currentPassword" :type="showCurrent ? 'text' : 'password'" class="w-full h-11 px-4 pr-11 rounded-xl border border-gray-200 bg-white outline-none focus:ring-2 focus:ring-red-500/20" :disabled="passLoading" />
                  <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 p-2 rounded-lg hover:bg-gray-50" @click="showCurrent = !showCurrent" :disabled="passLoading">
                    <EyeOff v-if="showCurrent" class="h-4 w-4 text-gray-500" />
                    <Eye v-else class="h-4 w-4 text-gray-500" />
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium mb-1">{{ tr("admin.profile.new_password","New password") }}</label>
                <div class="relative">
                  <input v-model="newPassword" :type="showNew ? 'text' : 'password'" class="w-full h-11 px-4 pr-11 rounded-xl border border-gray-200 bg-white outline-none focus:ring-2 focus:ring-red-500/20" :disabled="passLoading" />
                  <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 p-2 rounded-lg hover:bg-gray-50" @click="showNew = !showNew" :disabled="passLoading">
                    <EyeOff v-if="showNew" class="h-4 w-4 text-gray-500" />
                    <Eye v-else class="h-4 w-4 text-gray-500" />
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium mb-1">{{ tr("admin.profile.confirm_password","Confirm new password") }}</label>
                <div class="relative">
                  <input v-model="newPassword2" :type="showNew2 ? 'text' : 'password'" class="w-full h-11 px-4 pr-11 rounded-xl border border-gray-200 bg-white outline-none focus:ring-2 focus:ring-red-500/20" :disabled="passLoading" />
                  <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 p-2 rounded-lg hover:bg-gray-50" @click="showNew2 = !showNew2" :disabled="passLoading">
                    <EyeOff v-if="showNew2" class="h-4 w-4 text-gray-500" />
                    <Eye v-else class="h-4 w-4 text-gray-500" />
                  </button>
                </div>
              </div>

              <div class="pt-2 flex flex-col sm:flex-row gap-2 sm:justify-end">
                <button type="button" :class="btnSecondary" @click="closePasswordModal" :disabled="passLoading">
                  {{ tr("actions.cancel","Cancel") }}
                </button>
                <button type="button" :class="btnPrimary" @click="handleChangePasswordSave" :disabled="passLoading || !currentPassword || !newPassword || !newPassword2">
                  <Save class="h-4 w-4" />
                  {{ tr("actions.save","Save") }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="roleModalOpen && selectedUser" class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
        <button class="absolute inset-0 bg-black/60" @click="closeRoleModal" aria-label="Close" :disabled="userBusyId === selectedUser.id" />
        <div class="relative w-full max-w-lg rounded-2xl border bg-white shadow-2xl text-gray-900 overflow-hidden">
          <div class="p-6">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <h3 class="text-xl font-semibold">{{ tr("admin.users.role_title","Change user role") }}</h3>
                <div class="mt-1 text-sm text-gray-500">
                  {{ tr("admin.users.role_for","Change role for") }}: <strong>{{ selectedUser?.name }}</strong>
                </div>
              </div>
              <button type="button" class="h-10 w-10 rounded-xl bg-gray-50 border hover:bg-gray-100 transition flex items-center justify-center disabled:opacity-50" @click="closeRoleModal" :disabled="userBusyId === selectedUser.id">
                <X class="h-4 w-4" />
              </button>
            </div>

            <div class="mt-5 space-y-3">
              <label
                  v-for="role in [
                  { value: 'user', label: tr('admin.users.role_user','User'), desc: tr('admin.users.role_user_desc','Basic user without special permissions') },
                  { value: 'admin', label: tr('admin.users.role_admin','Admin'), desc: tr('admin.users.role_admin_desc','Full access to the system') },
                ]"
                  :key="role.value"
                  :class="`block p-4 rounded-xl border-2 cursor-pointer transition ${
                  selectedRole === role.value ? 'border-gray-900 bg-gray-50' : 'border-gray-200 bg-white hover:bg-gray-50'
                } ${userBusyId === selectedUser.id ? 'opacity-50 cursor-not-allowed' : ''}`"
              >
                <div class="flex items-start gap-3">
                  <input type="radio" :value="role.value" v-model="selectedRole" :disabled="userBusyId === selectedUser.id" class="mt-1 h-4 w-4" />
                  <div class="flex-1">
                    <div class="flex items-center gap-2">
                      <UserCog class="h-4 w-4" />
                      <span class="font-semibold">{{ role.label }}</span>
                    </div>
                    <div class="mt-1 text-sm text-gray-500">{{ role.desc }}</div>
                  </div>
                </div>
              </label>
            </div>

            <div class="pt-4 flex flex-col sm:flex-row gap-2 sm:justify-end mt-4">
              <button type="button" :class="btnSecondary" @click="closeRoleModal" :disabled="userBusyId === selectedUser.id">
                {{ tr("actions.cancel","Cancel") }}
              </button>
              <button type="button" :class="btnPrimary" @click="handleUserRoleChange" :disabled="userBusyId === selectedUser.id || !selectedRole">
                <Save class="h-4 w-4" />
                {{ tr("actions.save","Save") }}
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
