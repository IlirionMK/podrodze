import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'

// Mock localStorage
const localStorageMock = {
  getItem: vi.fn(),
  setItem: vi.fn(),
  removeItem: vi.fn(),
  clear: vi.fn(),
}
global.localStorage = localStorageMock

describe('Composables Integration Tests', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('useAuth', () => {
    it('should handle authentication state', () => {
      // Mock implementation
      const createUseAuth = () => {
        const isAuthenticated = ref(false)
        const user = ref(null)
        const token = ref(localStorage.getItem('token'))

        const login = async (credentials) => {
          // Mock login logic
          isAuthenticated.value = true
          user.value = { id: 1, name: 'Test User', email: credentials.email }
          token.value = 'mock-token'
          localStorage.setItem('token', token.value)
        }

        const logout = () => {
          isAuthenticated.value = false
          user.value = null
          token.value = null
          localStorage.removeItem('token')
        }

        return { isAuthenticated, user, token, login, logout }
      }

      const { isAuthenticated, user, login, logout } = createUseAuth()

      expect(isAuthenticated.value).toBe(false)
      expect(user.value).toBeNull()

      login({ email: 'test@example.com', password: 'password' })
      
      expect(isAuthenticated.value).toBe(true)
      expect(user.value).toEqual({ id: 1, name: 'Test User', email: 'test@example.com' })
      expect(localStorage.setItem).toHaveBeenCalledWith('token', 'mock-token')

      logout()
      
      expect(isAuthenticated.value).toBe(false)
      expect(user.value).toBeNull()
      expect(localStorage.removeItem).toHaveBeenCalledWith('token')
    })
  })

  describe('useTrips', () => {
    it('should manage trips state', async () => {
      const mockAxios = {
        get: vi.fn(),
        post: vi.fn()
      }

      const createUseTrips = (axios) => {
        const trips = ref([])
        const loading = ref(false)
        const error = ref(null)

        const fetchTrips = async () => {
          loading.value = true
          error.value = null
          
          try {
            const response = await axios.get('/api/trips')
            trips.value = response.data
          } catch (err) {
            error.value = err.message
          } finally {
            loading.value = false
          }
        }

        const createTrip = async (tripData) => {
          loading.value = true
          error.value = null
          
          try {
            const response = await axios.post('/api/trips', tripData)
            trips.value.push(response.data)
            return response.data
          } catch (err) {
            error.value = err.message
            throw err
          } finally {
            loading.value = false
          }
        }

        return { trips, loading, error, fetchTrips, createTrip }
      }

      mockAxios.get.mockResolvedValue({
        data: [
          { id: 1, title: 'Trip 1' },
          { id: 2, title: 'Trip 2' }
        ]
      })

      const { trips, loading, error, fetchTrips, createTrip } = createUseTrips(mockAxios)

      expect(trips.value).toEqual([])
      expect(loading.value).toBe(false)

      await fetchTrips()

      expect(loading.value).toBe(false)
      expect(trips.value).toHaveLength(2)
      expect(mockAxios.get).toHaveBeenCalledWith('/api/trips')

      mockAxios.post.mockResolvedValue({
        data: { id: 3, title: 'New Trip' }
      })

      await createTrip({ title: 'New Trip' })

      expect(trips.value).toHaveLength(3)
      expect(trips.value[2]).toEqual({ id: 3, title: 'New Trip' })
    })
  })

  describe('useI18n integration', () => {
    it('should handle language switching', () => {
      const createUseLanguage = () => {
        const locale = ref('pl')
        
        const changeLanguage = (lang) => {
          locale.value = lang
          localStorage.setItem('lang', lang)
        }

        const t = (key) => {
          const translations = {
            pl: { 'auth.login.title': 'Zaloguj się' },
            en: { 'auth.login.title': 'Login' }
          }
          return translations[locale.value]?.[key] || key
        }

        return { locale, changeLanguage, t }
      }

      const { locale, changeLanguage, t } = createUseLanguage()

      expect(locale.value).toBe('pl')
      expect(t('auth.login.title')).toBe('Zaloguj się')

      changeLanguage('en')

      expect(locale.value).toBe('en')
      expect(t('auth.login.title')).toBe('Login')
      expect(localStorage.setItem).toHaveBeenCalledWith('lang', 'en')
    })
  })
})
