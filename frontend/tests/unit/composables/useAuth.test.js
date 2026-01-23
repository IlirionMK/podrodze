import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref, computed } from 'vue'

// Mock localStorage before importing useAuth
const localStorageMock = {
  getItem: vi.fn(),
  setItem: vi.fn(),
  removeItem: vi.fn()
}
global.localStorage = localStorageMock

// Mock the api module
vi.mock('@/composables/api/api', () => ({
  default: {
    post: vi.fn()
  }
}))

// Import useAuth after localStorage is mocked
import { useAuth } from '@/composables/useAuth'

describe('useAuth Composable', () => {
  let mockApi
  let mockRouter

  beforeEach(async () => {
    vi.clearAllMocks()
    vi.resetModules()
    
    // Mock API
    mockApi = await import('@/composables/api/api')
    
    // Mock router
    mockRouter = {
      push: vi.fn()
    }
  })

  describe('initialization', () => {
    beforeEach(() => {
      localStorageMock.getItem.mockClear()
    })

    it('initializes with token from localStorage', async () => {
      localStorageMock.getItem.mockImplementation((key) => {
        if (key === 'token') return 'test-token'
        return null
      })

      // Re-import useAuth to get fresh state
      const { useAuth: freshUseAuth } = await import('@/composables/useAuth')
      const { token } = freshUseAuth()
      expect(token.value).toBe('test-token')
    })

    it('initializes with user from localStorage', async () => {
      const mockUser = { id: 1, name: 'John', role: 'user' }
      localStorageMock.getItem.mockImplementation((key) => {
        if (key === 'user') return JSON.stringify(mockUser)
        return null
      })

      // Re-import useAuth to get fresh state
      const { useAuth: freshUseAuth } = await import('@/composables/useAuth')
      const { user } = freshUseAuth()
      expect(user.value).toEqual(mockUser)
    })

    it('initializes with null when no localStorage data', async () => {
      localStorageMock.getItem.mockReturnValue(null)

      // Re-import useAuth to get fresh state
      const { useAuth: freshUseAuth } = await import('@/composables/useAuth')
      const { token, user } = freshUseAuth()
      expect(token.value).toBeNull()
      expect(user.value).toBeNull()
    })
  })

  describe('setAuth', () => {
    it('sets token and user in reactive state', () => {
      localStorageMock.getItem.mockReturnValue(null)
      const { token, user, setAuth } = useAuth()

      const mockUser = { id: 1, name: 'John' }
      const mockToken = 'new-token'

      setAuth(mockUser, mockToken)

      expect(token.value).toBe(mockToken)
      expect(user.value).toStrictEqual(mockUser)
    })

    it('saves token and user to localStorage', () => {
      localStorageMock.getItem.mockReturnValue(null)
      const { setAuth } = useAuth()

      const mockUser = { id: 1, name: 'John' }
      const mockToken = 'new-token'

      setAuth(mockUser, mockToken)

      expect(localStorageMock.setItem).toHaveBeenCalledWith('token', mockToken)
      expect(localStorageMock.setItem).toHaveBeenCalledWith('user', JSON.stringify(mockUser))
    })
  })

  describe('clearAuth', () => {
    it('clears token and user from reactive state', () => {
      localStorageMock.getItem.mockImplementation((key) => {
        if (key === 'token') return 'test-token'
        if (key === 'user') return JSON.stringify({ id: 1 })
        return null
      })

      const { token, user, clearAuth } = useAuth()

      clearAuth()

      expect(token.value).toBeNull()
      expect(user.value).toBeNull()
    })

    it('removes token, user, and intended from localStorage', () => {
      localStorageMock.getItem.mockReturnValue('test-token')
      const { clearAuth } = useAuth()

      clearAuth()

      expect(localStorageMock.removeItem).toHaveBeenCalledWith('token')
      expect(localStorageMock.removeItem).toHaveBeenCalledWith('user')
      expect(localStorageMock.removeItem).toHaveBeenCalledWith('intended')
    })
  })

  describe('logout', () => {
    it('calls API logout endpoint', async () => {
      localStorageMock.getItem.mockReturnValue('test-token')
      mockApi.default.post.mockResolvedValue({})

      const { logout } = useAuth()

      await logout(mockRouter)

      expect(mockApi.default.post).toHaveBeenCalledWith('/logout')
    })

    it('clears auth data after logout', async () => {
      localStorageMock.getItem.mockReturnValue('test-token')
      mockApi.default.post.mockResolvedValue({})

      const { logout, token, user } = useAuth()

      await logout(mockRouter)

      expect(token.value).toBeNull()
      expect(user.value).toBeNull()
    })

    it('removes localStorage items after logout', async () => {
      localStorageMock.getItem.mockReturnValue('test-token')
      mockApi.default.post.mockResolvedValue({})

      const { logout } = useAuth()

      await logout(mockRouter)

      expect(localStorageMock.removeItem).toHaveBeenCalledWith('token')
      expect(localStorageMock.removeItem).toHaveBeenCalledWith('user')
      expect(localStorageMock.removeItem).toHaveBeenCalledWith('intended')
    })

    it('redirects to guest home after logout', async () => {
      localStorageMock.getItem.mockReturnValue('test-token')
      mockApi.default.post.mockResolvedValue({})

      const { logout } = useAuth()

      await logout(mockRouter)

      expect(mockRouter.push).toHaveBeenCalledWith({ name: 'guest.home' })
    })

    it('handles API errors during logout', async () => {
      localStorageMock.getItem.mockReturnValue('test-token')
      mockApi.default.post.mockRejectedValue(new Error('Network error'))

      const { logout, token, user } = useAuth()

      await logout(mockRouter)

      // Should still clear auth data even if API call fails
      expect(token.value).toBeNull()
      expect(user.value).toBeNull()
      expect(mockRouter.push).toHaveBeenCalledWith({ name: 'guest.home' })
    })

    it('works without router parameter', async () => {
      localStorageMock.getItem.mockReturnValue('test-token')
      mockApi.default.post.mockResolvedValue({})

      const { logout } = useAuth()

      // Should not throw error without router
      await expect(logout()).resolves.not.toThrow()
    })
  })

  describe('computed properties', () => {
    beforeEach(() => {
      localStorageMock.getItem.mockClear()
    })

    it('isAuthenticated returns true when token exists', async () => {
      localStorageMock.getItem.mockImplementation((key) => {
        if (key === 'token') return 'test-token'
        return null
      })

      // Re-import useAuth to get fresh state
      const { useAuth: freshUseAuth } = await import('@/composables/useAuth')
      const { isAuthenticated } = freshUseAuth()
      expect(isAuthenticated.value).toBe(true)
    })

    it('isAuthenticated returns false when token is null', () => {
      localStorageMock.getItem.mockReturnValue(null)

      const { isAuthenticated } = useAuth()
      expect(isAuthenticated.value).toBe(false)
    })

    it('isAdmin returns true when user role is admin', async () => {
      const mockUser = { id: 1, role: 'admin' }
      localStorageMock.getItem.mockImplementation((key) => {
        if (key === 'user') return JSON.stringify(mockUser)
        return null
      })

      // Re-import useAuth to get fresh state
      const { useAuth: freshUseAuth } = await import('@/composables/useAuth')
      const { isAdmin } = freshUseAuth()
      expect(isAdmin.value).toBe(true)
    })

    it('isAdmin returns false when user role is not admin', () => {
      const mockUser = { id: 1, role: 'user' }
      localStorageMock.getItem.mockImplementation((key) => {
        if (key === 'user') return JSON.stringify(mockUser)
        return null
      })

      const { isAdmin } = useAuth()
      expect(isAdmin.value).toBe(false)
    })

    it('isAdmin returns false when user is null', () => {
      localStorageMock.getItem.mockReturnValue(null)

      const { isAdmin } = useAuth()
      expect(isAdmin.value).toBe(false)
    })

    it('isAdmin returns false when user has no role', () => {
      const mockUser = { id: 1, name: 'John' }
      localStorageMock.getItem.mockImplementation((key) => {
        if (key === 'user') return JSON.stringify(mockUser)
        return null
      })

      const { isAdmin } = useAuth()
      expect(isAdmin.value).toBe(false)
    })
  })

  describe('reactive updates', () => {
    it('isAuthenticated updates when token changes', () => {
      localStorageMock.getItem.mockReturnValue(null)
      const { token, isAuthenticated, setAuth } = useAuth()

      expect(isAuthenticated.value).toBe(false)

      setAuth({ id: 1 }, 'new-token')
      expect(isAuthenticated.value).toBe(true)

      const { clearAuth } = useAuth()
      clearAuth()
      expect(isAuthenticated.value).toBe(false)
    })

    it('isAdmin updates when user role changes', () => {
      localStorageMock.getItem.mockReturnValue(null)
      const { isAdmin, setAuth } = useAuth()

      expect(isAdmin.value).toBe(false)

      setAuth({ id: 1, role: 'admin' }, 'token')
      expect(isAdmin.value).toBe(true)

      setAuth({ id: 1, role: 'user' }, 'token')
      expect(isAdmin.value).toBe(false)
    })
  })
})
