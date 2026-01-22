<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n' 
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
} from 'lucide-vue-next'

const { t, te } = useI18n({ useScope: "global" })

// Mock API functions - replace with your actual API
const api = {
  getHeaders: () => {
    const token = localStorage.getItem('token')
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...(token && { 'Authorization': `Bearer ${token}` }),
    }
  },
  get: async (url: string) => {
    const response = await fetch(`/api/v1${url}`, {
      method: 'GET',
      headers: api.getHeaders(),
      credentials: 'include',
    })
    if (!response.ok) throw new Error(await response.text())
    return { data: await response.json() }
  },
  post: async (url: string, data: any) => {
    const response = await fetch(`/api/v1${url}`, {
      method: 'POST',
      headers: api.getHeaders(),
      body: JSON.stringify(data),
      credentials: 'include',
    })
    if (!response.ok) throw new Error(await response.text())
    return { data: await response.json() }
  },
  put: async (url: string, data: any) => {
    const response = await fetch(`/api/v1${url}`, {
      method: 'PUT',
      headers: api.getHeaders(),
      body: JSON.stringify(data),
      credentials: 'include',
    })
    if (!response.ok) throw new Error(await response.text())
    return { data: await response.json() }
  },
  patch: async (url: string, data: any) => {
    const response = await fetch(`/api/v1${url}`, {
      method: 'PATCH',
      headers: api.getHeaders(),
      body: JSON.stringify(data),
      credentials: 'include',
    })
    if (!response.ok) throw new Error(await response.text())
    return { data: await response.json() }
  },
}

// Types
type User = {
  id: number | string
  name: string
  email: string
  role?: string
  banned?: boolean
  created_at?: string
}

type ActivityLog = {
  id: number | string
  action: string
  description?: string
  level?: 'info' | 'warning' | 'error' | 'critical' | 'success'
  user?: User
  user_id?: number | string
  ip_address?: string
  created_at: string
}

type Pagination = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

// Button styles
const btnBase =
  'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed'
const btnPrimary =
  btnBase + ' bg-gradient-to-r from-red-600 to-orange-600 text-white hover:opacity-90 active:opacity-80 shadow'
const btnSecondary = btnBase + ' border border-gray-200 bg-white text-gray-900 hover:bg-gray-50'
const btnDanger = btnBase + ' border border-red-200 bg-red-50 text-red-700 hover:bg-red-100'

// Helper functions
const getInitials = (name: string) => {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return 'A'
  const a = parts[0]?.[0] || ''
  const b = parts.length > 1 ? parts[parts.length - 1]?.[0] : ''
  return (a + b).toUpperCase()
}

const formatDate = (dateString?: string) => {
  if (!dateString) return '—'
  try {
    const date = new Date(dateString)
    return new Intl.DateTimeFormat('pl-PL', {
      year: 'numeric',
      month: 'short',
      day: '2-digit',
    }).format(date)
  } catch {
    return '—'
  }
}

const formatDateTime = (dateString?: string) => {
  if (!dateString) return '—'
  try {
    const date = new Date(dateString)
    return new Intl.DateTimeFormat('pl-PL', {
      year: 'numeric',
      month: 'short',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date)
  } catch {
    return '—'
  }
}

const getLevelBadgeColor = (level?: string) => {
  switch (level?.toLowerCase()) {
    case 'critical':
    case 'error':
      return 'bg-red-100 text-red-700 border-red-200'
    case 'warning':
      return 'bg-yellow-100 text-yellow-700 border-yellow-200'
    case 'info':
      return 'bg-blue-100 text-blue-700 border-blue-200'
    case 'success':
      return 'bg-green-100 text-green-700 border-green-200'
    default:
      return 'bg-gray-100 text-gray-700 border-gray-200'
  }
}

// Reactive state
const activeTab = ref<'profile' | 'users' | 'logs'>('profile')
const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

// Current user
const user = ref<User | null>({
  id: 1,
  name: 'Admin User',
  email: 'admin@example.com',
  role: 'admin',
  created_at: '2024-01-15T10:30:00Z',
})

// Users management
const users = ref<User[]>([])
const usersLoading = ref(false)
const usersPagination = ref<Pagination>({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
})
const usersSearch = ref('')

// Activity logs
const logs = ref<ActivityLog[]>([])
const logsLoading = ref(false)
const logsPagination = ref<Pagination>({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
})
const logsFilters = ref({
  search: '',
  user_id: '',
  action: '',
  level: '',
  from: '',
  to: '',
})

// Available options for filters
const availableActions = ref<string[]>([])
const availableLevels = ref<string[]>(['info', 'warning', 'error', 'critical', 'success'])

// Edit Profile Modal state
const editOpen = ref(false)
const editName = ref('')
const editEmail = ref('')

// Change Password Modal state
const passOpen = ref(false)
const currentPassword = ref('')
const newPassword = ref('')
const newPassword2 = ref('')
const showCurrent = ref(false)
const showNew = ref(false)
const showNew2 = ref(false)
const passLoading = ref(false)
const passError = ref('')

// Change Role Modal state
const roleModalOpen = ref(false)
const selectedUser = ref<User | null>(null)
const selectedRole = ref('')
const userBusyId = ref<number | string | null>(null)

// Logs filter state
const filtersExpanded = ref(false)

// API Methods
const loadMe = async () => {
  errorMessage.value = ''
  successMessage.value = ''
  loading.value = true
  try {
    const res = await api.get('/user')
    user.value = res.data
  } catch (e: any) {
    errorMessage.value = e?.response?.data?.message || e.message || 'Failed to load user profile.'
    console.error('Error loading user:', e)
  } finally {
    loading.value = false
  }
}

const loadUsers = async (page = 1) => {
  usersLoading.value = true
  try {
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: usersPagination.value.per_page.toString(),
    })

    if (usersSearch.value.trim()) {
      params.append('search', usersSearch.value.trim())
    }

    const res = await api.get(`/admin/users?${params.toString()}`)
    
    // Pobierz dane z API
    const responseData = res.data
    
    // Obsługuj zarówno paginated response (data + meta) jak i bezpośrednią tablicę
    let usersData = Array.isArray(responseData) ? responseData : (responseData.data || [])
    const meta = responseData.meta || responseData.pagination || {
      current_page: page,
      last_page: Math.ceil((usersData.length) / usersPagination.value.per_page),
      per_page: usersPagination.value.per_page,
      total: usersData.length,
    }

    users.value = usersData
    usersPagination.value = {
      current_page: meta.current_page || page,
      last_page: meta.last_page || 1,
      per_page: meta.per_page || usersPagination.value.per_page,
      total: meta.total || usersData.length,
    }
  } catch (e: any) {
    errorMessage.value = e?.response?.data?.message || e.message || 'Failed to load users.'
    console.error('Error loading users:', e)
  } finally {
    usersLoading.value = false
  }
}

const loadLogs = async (page = 1) => {
  logsLoading.value = true
  try {
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: logsPagination.value.per_page.toString(),
    })

    Object.entries(logsFilters.value).forEach(([key, value]) => {
      if (value && value.toString().trim()) {
        params.append(key, value.toString().trim())
      }
    })

    const res = await api.get(`/admin/logs/activity?${params.toString()}`)
    
    // Pobierz dane z API
    const responseData = res.data
    let logsData = Array.isArray(responseData) ? responseData : (responseData.data || [])
    const meta = responseData.meta || responseData.pagination || {
      current_page: page,
      last_page: Math.ceil((logsData.length) / logsPagination.value.per_page),
      per_page: logsPagination.value.per_page,
      total: logsData.length,
    }

    logs.value = logsData

    // Extract unique actions from logs
    const actions = Array.from(new Set(logsData.map((log: ActivityLog) => log.action)))
    availableActions.value = actions

    logsPagination.value = {
      current_page: meta.current_page || page,
      last_page: meta.last_page || 1,
      per_page: meta.per_page || logsPagination.value.per_page,
      total: meta.total || logsData.length,
    }
  } catch (e: any) {
    errorMessage.value = e?.response?.data?.message || e.message || 'Failed to load activity logs.'
    console.error('Error loading logs:', e)
  } finally {
    logsLoading.value = false
  }
}

const refreshAll = async () => {
  await loadMe()
  if (activeTab.value === 'users') {
    await loadUsers(usersPagination.value.current_page)
  } else if (activeTab.value === 'logs') {
    await loadLogs(logsPagination.value.current_page)
  }
}

// Modal handlers
const openEditModal = () => {
  if (user.value) {
    editName.value = user.value.name || ''
    editEmail.value = user.value.email || ''
  }
  editOpen.value = true
}

const closeEditModal = () => {
  editOpen.value = false
  editName.value = ''
  editEmail.value = ''
}

const handleEditProfileSave = async () => {
  const name = editName.value.trim()
  const email = editEmail.value.trim()
  if (!name || !email) return

  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    await api.put('/user/profile', { name, email })
    if (user.value) {
      user.value.name = name
      user.value.email = email
    }
    successMessage.value = 'Profil został zaktualizowany.'
    closeEditModal()
  } catch (e: any) {
    errorMessage.value = e?.response?.data?.message || 'Coś poszło nie tak.'
  } finally {
    loading.value = false
  }
}

const openPasswordModal = () => {
  passOpen.value = true
}

const closePasswordModal = () => {
  passOpen.value = false
  currentPassword.value = ''
  newPassword.value = ''
  newPassword2.value = ''
  showCurrent.value = false
  showNew.value = false
  showNew2.value = false
  passError.value = ''
}

const handleChangePasswordSave = async () => {
  passError.value = ''
  if (!currentPassword.value || !newPassword.value || !newPassword2.value) return

  if (newPassword.value !== newPassword2.value) {
    passError.value = 'Nowe hasła nie są zgodne.'
    return
  }

  if (newPassword.value.length < 8) {
    passError.value = 'Hasło musi mieć minimum 8 znaków.'
    return
  }

  passLoading.value = true
  try {
    await api.put('/user/password', {
      current_password: currentPassword.value,
      password: newPassword.value,
      password_confirmation: newPassword2.value,
    })
    successMessage.value = 'Hasło zostało zmienione.'
    closePasswordModal()
  } catch (e: any) {
    passError.value = e?.response?.data?.message || 'Coś poszło nie tak.'
  } finally {
    passLoading.value = false
  }
}

const toggleUserBan = async (targetUser: User) => {
  if (!confirm(`Na pewno chcesz ${targetUser.banned ? 'odblokować' : 'zablokować'} tego użytkownika?`)) return

  userBusyId.value = targetUser.id
  const originalState = targetUser.banned
  const newState = !targetUser.banned

  try {
    // Zmień UI od razu (optimistic update)
    targetUser.banned = newState

    await api.patch(`/admin/users/${targetUser.id}/ban`, { banned: newState })
    successMessage.value = `Użytkownik został ${originalState ? 'odblokowany' : 'zablokowany'}.`
  } catch (e: any) {
    // Przywróć poprzedni stan w case błędu
    targetUser.banned = originalState
    errorMessage.value = e?.response?.data?.message || 'Something went wrong.'
  } finally {
    userBusyId.value = null
  }
}
const formatAction = (action?: string) => {
  if (!action) return '—'
  const mapping: Record<string, string> = {
    'admin.user.role_updated': 'Zmiana roli użytkownika',
    'admin.user.ban_updated': 'Zmiana statusu blokady użytkownika',
    'trip.member_added': 'Dodano członka do wycieczki',
    'trip.created': 'Utworzono nową wycieczkę',
    'user.login': 'Logowanie użytkownika',
    'user.logout': 'Wylogowanie użytkownika',
    'user.password_changed': 'Zmiana hasła użytkownika',

  }
  return mapping[action] || action.replace(/\./g, ' ').replace(/_/g, ' ')
}

const openRoleModal = (targetUser: User) => {
  // Admin nie może zmieniać swojej własnej roli
  if (user.value?.id === targetUser.id) {
    errorMessage.value = 'Nie możesz zmieniać swoją rolę.'
    return
  }

  selectedUser.value = targetUser
  selectedRole.value = targetUser.role || ''
  roleModalOpen.value = true
}

const closeRoleModal = () => {
  roleModalOpen.value = false
  selectedUser.value = null
  selectedRole.value = ''
}

const handleUserRoleChange = async () => {
  if (!selectedUser.value) return

  userBusyId.value = selectedUser.value.id
  const previousRole = selectedUser.value.role
  const isCurrentUser = user.value?.id === selectedUser.value.id

  try {
    // Zmień UI od razu (optimistic update)
    selectedUser.value.role = selectedRole.value

    await api.patch(`/admin/users/${selectedUser.value.id}/role`, { role: selectedRole.value })
    
    // Jeśli zmieniłeś swoją własną rolę, przeładuj dane profilu
    if (isCurrentUser) {
      await loadMe()
    }
    
    successMessage.value = 'Rola użytkownika została zmieniona.'
    closeRoleModal()
  } catch (e: any) {
    // Przywróć poprzednią rolę w case błędu
    selectedUser.value.role = previousRole
    errorMessage.value = e?.response?.data?.message || 'Something went wrong.'
  } finally {
    userBusyId.value = null
  }
}

// Lifecycle
onMounted(() => {
  loadMe()
  loadUsers()
  loadLogs()
})
</script>

<template>
  <div class="w-full min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-10">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4 mb-6">
        <div class="min-w-0">
          <div class="flex items-center gap-3">
            <Shield class="w-8 h-8 text-red-600" />
            <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900">
              Panel Administratora
            </h1>
          </div>
          <div class="mt-1 text-sm text-gray-500">
            Zarządzanie systemem i użytkownikami
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            :class="btnSecondary"
            @click="refreshAll"
            :disabled="loading || usersLoading || logsLoading"
          >
            <RefreshCw class="h-4 w-4" />
            Odśwież
          </button>
        </div>
      </div>

      <!-- Global Messages -->
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
              @click="activeTab = 'profile'"
              :class="`px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap ${
                activeTab === 'profile'
                  ? 'border-red-600 text-red-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`"
            >
              <Shield class="w-4 h-4 inline mr-2" />
              Profil
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
              Użytkownicy
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
              Logi aktywności
            </button>
          </nav>
        </div>
      </div>

      <!-- Tab Content -->
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
                    <div class="text-xl font-semibold text-gray-900 truncate">
                      {{ user?.name || '—' }}
                    </div>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700 border border-red-200">
                      <Shield class="w-3 h-3 mr-1" />
                      Administrator
                    </span>
                  </div>
                  <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                    <span class="inline-flex items-center gap-1">
                      <Mail class="h-4 w-4" />
                      {{ user?.email || '—' }}
                    </span>

                    <span v-if="user?.created_at" class="inline-flex items-center gap-1">
                      <span class="text-gray-300">•</span>
                      <CalendarDays class="h-4 w-4" />
                      Dołączono {{ formatDate(user.created_at) }}
                    </span>
                  </div>
                </div>
              </div>

              <button
                type="button"
                :class="btnSecondary"
                @click="openEditModal"
                :disabled="loading || !user"
              >
                <Pencil class="h-4 w-4" />
                Edytuj
              </button>
            </div>
          </div>

          <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="p-4 rounded-2xl border bg-gray-50">
                <div class="text-xs text-gray-500">User ID</div>
                <div class="mt-1 font-semibold text-gray-900">{{ user?.id ?? '—' }}</div>
              </div>

              <div class="p-4 rounded-2xl border bg-gray-50">
                <div class="text-xs text-gray-500">Email</div>
                <div class="mt-1 font-semibold text-gray-900">{{ user?.email ?? '—' }}</div>
              </div>
            </div>

            <div class="mt-6 flex flex-col sm:flex-row gap-2 sm:justify-end">
              <button
                type="button"
                :class="btnSecondary"
                @click="openPasswordModal"
                :disabled="loading"
              >
                <KeyRound class="h-4 w-4" />
                Zmień hasło
              </button>
            </div>
          </div>
        </section>
      </div>

      <!-- Users Tab -->
      <div v-show="activeTab === 'users'">
        <!-- Search -->
        <div class="mb-6 bg-white rounded-2xl border shadow-sm p-4">
          <div class="flex gap-3">
            <div class="flex-1">
              <div class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                  v-model="usersSearch"
                  type="text"
                  placeholder="Szukaj użytkownika..."
                  class="w-full h-11 pl-10 pr-4 rounded-xl border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                  @keydown.enter="loadUsers(1)"
                />
              </div>
            </div>
            <button type="button" :class="btnPrimary" @click="loadUsers(1)" :disabled="usersLoading">
              <Search class="h-4 w-4" />
              Szukaj
            </button>
          </div>
        </div>

        <!-- Users List -->
        <div v-if="usersLoading" class="text-center py-12">
          <div class="text-gray-500">Ładowanie...</div>
        </div>

        <div v-else-if="users.length === 0" class="bg-white rounded-2xl border shadow-sm p-12">
          <div class="text-center">
            <Users class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">Brak użytkowników</h3>
            <p class="text-sm text-gray-500">Nie znaleziono żadnych użytkowników.</p>
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
                  <span class="text-lg font-semibold">
                    {{ targetUser.name?.charAt(0)?.toUpperCase() || 'U' }}
                  </span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <h3 class="text-lg font-semibold text-gray-900 truncate">
                      {{ targetUser.name }}
                    </h3>
                    <span v-if="targetUser.banned" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 border border-red-200">
                      <Ban class="w-3 h-3 mr-1" />
                      Zablokowany
                    </span>
                    <span v-if="targetUser.role" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 border border-blue-200">
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
                      Członek od: {{ formatDate(targetUser.created_at) }}
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
                  :title="user?.id === targetUser.id ? 'Nie możesz zmieniać swoją rolę' : ''"
                >
                  <UserCog class="h-4 w-4" />
                  Zmień rolę
                </button>
                <button
                  v-if="!targetUser.banned"
                  type="button"
                  :class="btnDanger"
                  @click="toggleUserBan(targetUser)"
                  :disabled="userBusyId === targetUser.id"
                >
                  <Ban class="h-4 w-4" />
                  Zablokuj
                </button>
                <button
                  v-else
                  type="button"
                  :class="btnSecondary + ' border-green-200 bg-green-50 text-green-700 hover:bg-green-100'"
                  @click="toggleUserBan(targetUser)"
                  :disabled="userBusyId === targetUser.id"
                >
                  <CheckCircle class="h-4 w-4" />
                  Odblokuj
                </button>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="usersPagination.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
            <button
              :class="btnSecondary"
              :disabled="usersPagination.current_page === 1 || usersLoading"
              @click="loadUsers(usersPagination.current_page - 1)"
            >
              Poprzednia
            </button>
            <span class="px-4 py-2 text-sm text-gray-600">
              Strona {{ usersPagination.current_page }} z {{ usersPagination.last_page }}
            </span>
            <button
              :class="btnSecondary"
              :disabled="usersPagination.current_page === usersPagination.last_page || usersLoading"
              @click="loadUsers(usersPagination.current_page + 1)"
            >
              Następna
            </button>
          </div>
        </div>
      </div>

      <!-- Activity Logs Tab -->
      <div v-show="activeTab === 'logs'">
        <!-- Filters -->
        <div class="mb-6 bg-white rounded-2xl border shadow-sm overflow-hidden">
          <button
            @click="filtersExpanded = !filtersExpanded"
            class="w-full p-4 flex items-center justify-between hover:bg-gray-50 transition"
          >
            <div class="flex items-center gap-3">
              <Filter class="w-5 h-5 text-gray-500" />
              <h3 class="font-semibold text-gray-900">Filtry</h3>
              <span v-if="logsFilters.search || logsFilters.user_id || logsFilters.action || logsFilters.level || logsFilters.from || logsFilters.to" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 border border-red-200">
                Aktywne
              </span>
            </div>
            <ChevronUp v-if="filtersExpanded" class="w-5 h-5 text-gray-500" />
            <ChevronDown v-else class="w-5 h-5 text-gray-500" />
          </button>

          <div v-if="filtersExpanded" class="p-4 pt-0 border-t">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Szukaj</label>
                <input
                  v-model="logsFilters.search"
                  type="text"
                  placeholder="Szukaj w logach..."
                  class="w-full h-10 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ID Użytkownika</label>
                <input
                  v-model="logsFilters.user_id"
                  type="text"
                  placeholder="np. 123"
                  class="w-full h-10 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Akcja</label>
                <div class="relative">
                  <select
                    v-model="logsFilters.action"
                    class="w-full h-10 px-3 pr-8 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm appearance-none bg-white"
                  >
                    <option value="">Wszystkie akcje</option>
                    <option
                      v-for="action in availableActions"
                      :key="action"
                      :value="action"
                    >
                      {{ formatAction(action) }}
                    </option>
                  </select>
                  <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Poziom</label>
                <div class="relative">
                  <select
                    v-model="logsFilters.level"
                    class="w-full h-10 px-3 pr-8 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm appearance-none bg-white"
                  >
                    <option value="">Wszystkie poziomy</option>
                    <option v-for="level in availableLevels" :key="level" :value="level">
                      {{ level }}
                    </option>
                  </select>
                  <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Od</label>
                <input
                  v-model="logsFilters.from"
                  type="date"
                  class="w-full h-10 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-sm"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Do</label>
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
                Zastosuj filtry
              </button>
              <button type="button" :class="btnSecondary" @click="() => { logsFilters = { search: '', user_id: '', action: '', level: '', from: '', to: '' }; loadLogs(1) }" :disabled="logsLoading">
                <X class="h-4 w-4" />
                Wyczyść
              </button>
            </div>
          </div>
        </div>

        <!-- Logs List -->
        <div v-if="logsLoading" class="text-center py-12">
          <div class="text-gray-500">Ładowanie...</div>
        </div>

        <div v-else-if="logs.length === 0" class="bg-white rounded-2xl border shadow-sm p-12">
          <div class="text-center">
            <FileText class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">Brak logów</h3>
            <p class="text-sm text-gray-500">Nie znaleziono żadnych logów aktywności.</p>
          </div>
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="log in logs"
            :key="log.id"
            class="bg-white rounded-2xl border shadow-sm p-4 hover:shadow-md transition"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2 flex-wrap">
                  <span
                    :class="`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border ${getLevelBadgeColor(log.level)}`"
                  >
                    {{ log.level || 'info' }}
                  </span>
                  <span class="text-xs text-gray-400">
                    <Calendar class="w-3 h-3 inline mr-1" />
                    {{ formatDateTime(log.created_at) }}
                  </span>
                </div>

                <div class="space-y-1">
                  <div class="font-medium text-gray-900">
                    <Activity class="w-4 h-4 inline mr-2 text-gray-500" />
                    {{ formatAction(log.action) || log.description || '—' }}
                  </div>

                  <div v-if="log.user || log.user_id" class="text-sm text-gray-600">
                    Użytkownik:
                    <span class="font-medium">
                      {{ log.user?.name || `ID: ${log.user_id}` }}
                    </span>
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

          <!-- Pagination -->
          <div v-if="logsPagination.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
            <button
              :class="btnSecondary"
              :disabled="logsPagination.current_page === 1 || logsLoading"
              @click="loadLogs(logsPagination.current_page - 1)"
            >
              Poprzednia
            </button>
            <span class="px-4 py-2 text-sm text-gray-600">
              Strona {{ logsPagination.current_page }} z {{ logsPagination.last_page }}
            </span>
            <button
              :class="btnSecondary"
              :disabled="logsPagination.current_page === logsPagination.last_page || logsLoading"
              @click="loadLogs(logsPagination.current_page + 1)"
            >
              Następna
            </button>
          </div>
        </div>
      </div>

      <!-- Edit Profile Modal -->
      <div v-if="editOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
        <button
          class="absolute inset-0 bg-black/60"
          @click="closeEditModal"
          aria-label="Close"
          :disabled="loading"
        />

        <div class="relative w-full max-w-lg rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white overflow-hidden animate-in fade-in zoom-in duration-200">
          <div class="p-6">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <h3 class="text-xl font-semibold drop-shadow">Edytuj profil</h3>
                <div class="mt-1 text-sm text-white/70">Zaktualizuj swoje imię i email.</div>
              </div>

              <button
                type="button"
                class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 hover:bg-white/15 transition flex items-center justify-center disabled:opacity-50"
                @click="closeEditModal"
                :disabled="loading"
                aria-label="Close"
              >
                <X class="h-4 w-4" />
              </button>
            </div>

            <div class="mt-5 space-y-4">
              <div>
                <label class="block text-sm font-medium mb-1">Imię i nazwisko</label>
                <input
                  v-model="editName"
                  type="text"
                  class="w-full h-11 px-4 rounded-xl border border-white/15 bg-white/10 text-white placeholder:text-white/40 outline-none focus:ring-2 focus:ring-white/20"
                  :disabled="loading"
                />
              </div>

              <div>
                <label class="block text-sm font-medium mb-1">Email</label>
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
                  @click="closeEditModal"
                  :disabled="loading"
                >
                  Anuluj
                </button>

                <button
                  type="button"
                  class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-red-500 to-orange-600 hover:opacity-90 active:opacity-80 transition shadow-lg disabled:opacity-50"
                  @click="handleEditProfileSave"
                  :disabled="loading || !editName.trim() || !editEmail.trim()"
                >
                  <Save class="h-4 w-4" />
                  Zapisz
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Change Password Modal -->
      <div v-if="passOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
        <button
          class="absolute inset-0 bg-black/60"
          @click="closePasswordModal"
          aria-label="Close"
          :disabled="passLoading"
        />

        <div class="relative w-full max-w-lg rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white overflow-hidden animate-in fade-in zoom-in duration-200">
          <div class="p-6">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <h3 class="text-xl font-semibold drop-shadow">Zmiana hasła</h3>
                <div class="mt-1 text-sm text-white/70">Wprowadź obecne hasło i wybierz nowe.</div>
              </div>

              <button
                type="button"
                class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 hover:bg-white/15 transition flex items-center justify-center disabled:opacity-50"
                @click="closePasswordModal"
                :disabled="passLoading"
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
                <label class="block text-sm font-medium mb-1">Obecne hasło</label>
                <div class="relative">
                  <input
                    v-model="currentPassword"
                    :type="showCurrent ? 'text' : 'password'"
                    class="w-full h-11 px-4 pr-11 rounded-xl border border-white/15 bg-white/10 text-white placeholder:text-white/40 outline-none focus:ring-2 focus:ring-white/20"
                    :disabled="passLoading"
                  />
                  <button
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 p-2 rounded-lg hover:bg-white/10"
                    @click="showCurrent = !showCurrent"
                    :disabled="passLoading"
                  >
                    <EyeOff v-if="showCurrent" class="h-4 w-4 text-white/70" />
                    <Eye v-else class="h-4 w-4 text-white/70" />
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium mb-1">Nowe hasło</label>
                <div class="relative">
                  <input
                    v-model="newPassword"
                    :type="showNew ? 'text' : 'password'"
                    class="w-full h-11 px-4 pr-11 rounded-xl border border-white/15 bg-white/10 text-white placeholder:text-white/40 outline-none focus:ring-2 focus:ring-white/20"
                    :disabled="passLoading"
                  />
                  <button
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 p-2 rounded-lg hover:bg-white/10"
                    @click="showNew = !showNew"
                    :disabled="passLoading"
                  >
                    <EyeOff v-if="showNew" class="h-4 w-4 text-white/70" />
                    <Eye v-else class="h-4 w-4 text-white/70" />
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium mb-1">Potwierdź nowe hasło</label>
                <div class="relative">
                  <input
                    v-model="newPassword2"
                    :type="showNew2 ? 'text' : 'password'"
                    class="w-full h-11 px-4 pr-11 rounded-xl border border-white/15 bg-white/10 text-white placeholder:text-white/40 outline-none focus:ring-2 focus:ring-white/20"
                    :disabled="passLoading"
                  />
                  <button
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 p-2 rounded-lg hover:bg-white/10"
                    @click="showNew2 = !showNew2"
                    :disabled="passLoading"
                  >
                    <EyeOff v-if="showNew2" class="h-4 w-4 text-white/70" />
                    <Eye v-else class="h-4 w-4 text-white/70" />
                  </button>
                </div>
              </div>

              <div class="pt-2 flex flex-col sm:flex-row gap-2 sm:justify-end">
                <button
                  type="button"
                  class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-white/10 border border-white/15 hover:bg-white/15 transition disabled:opacity-50"
                  @click="closePasswordModal"
                  :disabled="passLoading"
                >
                  Anuluj
                </button>

                <button
                  type="button"
                  class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-red-500 to-orange-600 hover:opacity-90 active:opacity-80 transition shadow-lg disabled:opacity-50"
                  @click="handleChangePasswordSave"
                  :disabled="passLoading || !currentPassword || !newPassword || !newPassword2"
                >
                  <Save class="h-4 w-4" />
                  Zapisz
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Change Role Modal -->
      <div v-if="roleModalOpen && selectedUser" class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
        <button
          class="absolute inset-0 bg-black/60"
          @click="closeRoleModal"
          aria-label="Close"
          :disabled="userBusyId === selectedUser.id"
        />

        <div class="relative w-full max-w-lg rounded-2xl border border-white/15 bg-white/10 backdrop-blur-xl shadow-2xl text-white overflow-hidden animate-in fade-in zoom-in duration-200">
          <div class="p-6">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <h3 class="text-xl font-semibold drop-shadow">Zmiana roli użytkownika</h3>
                <div class="mt-1 text-sm text-white/70">
                  Zmień rolę dla użytkownika: <strong>{{ selectedUser?.name }}</strong>
                </div>
              </div>

              <button
                type="button"
                class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 hover:bg-white/15 transition flex items-center justify-center disabled:opacity-50"
                @click="closeRoleModal"
                :disabled="userBusyId === selectedUser.id"
                aria-label="Close"
              >
                <X class="h-4 w-4" />
              </button>
            </div>

            <div class="mt-5 space-y-3">
              <label
                v-for="role in [
                  { value: 'user', label: 'Użytkownik', description: 'Podstawowy użytkownik bez specjalnych uprawnień' },
                  { value: 'admin', label: 'Administrator', description: 'Pełny dostęp do wszystkich funkcji systemu' },
                ]"
                :key="role.value"
                :class="`block p-4 rounded-xl border-2 cursor-pointer transition ${
                  selectedRole === role.value
                    ? 'border-white bg-white/20'
                    : 'border-white/15 bg-white/5 hover:bg-white/10'
                } ${userBusyId === selectedUser.id ? 'opacity-50 cursor-not-allowed' : ''}`"
              >
                <div class="flex items-start gap-3">
                  <input
                    type="radio"
                    :value="role.value"
                    v-model="selectedRole"
                    :disabled="userBusyId === selectedUser.id"
                    class="mt-1 h-4 w-4 text-red-600 focus:ring-2 focus:ring-white/20"
                  />
                  <div class="flex-1">
                    <div class="flex items-center gap-2">
                      <UserCog class="h-4 w-4" />
                      <span class="font-semibold">{{ role.label }}</span>
                    </div>
                    <div class="mt-1 text-sm text-white/70">{{ role.description }}</div>
                  </div>
                </div>
              </label>
            </div>

            <div class="pt-4 flex flex-col sm:flex-row gap-2 sm:justify-end mt-4">
              <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-white/10 border border-white/15 hover:bg-white/15 transition disabled:opacity-50"
                @click="closeRoleModal"
                :disabled="userBusyId === selectedUser.id"
              >
                Anuluj
              </button>

              <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium bg-gradient-to-r from-red-500 to-orange-600 hover:opacity-90 active:opacity-80 transition shadow-lg disabled:opacity-50"
                @click="handleUserRoleChange"
                :disabled="userBusyId === selectedUser.id || !selectedRole"
              >
                <Save class="h-4 w-4" />
                Zapisz
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
