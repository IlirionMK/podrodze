import { describe, it, expect, vi, beforeEach } from 'vitest'
import { fetchPreferences, updateMyPreferences } from '@/composables/api/preferences'

// Mock the api module
vi.mock('@/composables/api/api', () => ({
  default: {
    get: vi.fn(),
    put: vi.fn()
  }
}))

describe('Preferences API Composable', () => {
  let mockApi

  beforeEach(async () => {
    vi.clearAllMocks()
    mockApi = await import('@/composables/api/api')
  })

  describe('fetchPreferences', () => {
    it('should call api.get with correct endpoint', async () => {
      const mockResponse = { data: { theme: 'dark', language: 'en' } }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchPreferences()

      expect(mockApi.default.get).toHaveBeenCalledWith('/preferences')
      expect(result).toEqual(mockResponse)
    })

    it('should handle API errors', async () => {
      const mockError = new Error('Failed to fetch preferences')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(fetchPreferences()).rejects.toThrow('Failed to fetch preferences')
      expect(mockApi.default.get).toHaveBeenCalledWith('/preferences')
    })

    it('should return empty preferences when API returns null', async () => {
      const mockResponse = { data: null }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchPreferences()

      expect(mockApi.default.get).toHaveBeenCalledWith('/preferences')
      expect(result).toEqual(mockResponse)
    })
  })

  describe('updateMyPreferences', () => {
    it('should call api.put with correct endpoint and payload', async () => {
      const preferences = { theme: 'light', language: 'pl' }
      const mockResponse = { data: { success: true } }
      mockApi.default.put.mockResolvedValue(mockResponse)

      const result = await updateMyPreferences(preferences)

      expect(mockApi.default.put).toHaveBeenCalledWith('/users/me/preferences', { preferences })
      expect(result).toEqual(mockResponse)
    })

    it('should handle different preference formats', async () => {
      const testCases = [
        { theme: 'dark' },
        { language: 'en', notifications: true },
        { theme: 'light', language: 'de', privacy: { level: 2 } },
        {} // empty preferences
      ]

      for (const preferences of testCases) {
        const mockResponse = { data: { updated: true } }
        mockApi.default.put.mockResolvedValue(mockResponse)

        await updateMyPreferences(preferences)

        expect(mockApi.default.put).toHaveBeenCalledWith('/users/me/preferences', { preferences })
      }
    })

    it('should handle API errors when updating preferences', async () => {
      const preferences = { theme: 'invalid' }
      const mockError = new Error('Validation failed')
      mockApi.default.put.mockRejectedValue(mockError)

      await expect(updateMyPreferences(preferences)).rejects.toThrow('Validation failed')
      expect(mockApi.default.put).toHaveBeenCalledWith('/users/me/preferences', { preferences })
    })

    it('should handle network errors', async () => {
      const preferences = { theme: 'dark' }
      const mockError = new Error('Network error')
      mockApi.default.put.mockRejectedValue(mockError)

      await expect(updateMyPreferences(preferences)).rejects.toThrow('Network error')
    })

    it('should handle malformed preferences', async () => {
      const preferences = null
      const mockError = new Error('Invalid preferences format')
      mockApi.default.put.mockRejectedValue(mockError)

      await expect(updateMyPreferences(preferences)).rejects.toThrow('Invalid preferences format')
      expect(mockApi.default.put).toHaveBeenCalledWith('/users/me/preferences', { preferences })
    })
  })

  describe('integration with API client', () => {
    it('should maintain API client configuration', async () => {
      mockApi.default.get.mockResolvedValue({ data: {} })
      mockApi.default.put.mockResolvedValue({ data: {} })

      await fetchPreferences()
      await updateMyPreferences({ theme: 'dark' })

      expect(mockApi.default.get).toHaveBeenCalledTimes(1)
      expect(mockApi.default.put).toHaveBeenCalledTimes(1)
    })

    it('should handle sequential calls correctly', async () => {
      mockApi.default.get
        .mockResolvedValueOnce({ data: { theme: 'dark' } })
        .mockResolvedValueOnce({ data: { theme: 'light' } })
      
      mockApi.default.put.mockResolvedValue({ data: { success: true } })

      const result1 = await fetchPreferences()
      await updateMyPreferences({ theme: 'light' })
      const result2 = await fetchPreferences()

      expect(result1.data.theme).toBe('dark')
      expect(result2.data.theme).toBe('light')
      expect(mockApi.default.get).toHaveBeenCalledTimes(2)
      expect(mockApi.default.put).toHaveBeenCalledTimes(1)
    })
  })
})
