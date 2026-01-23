import { describe, it, expect, vi, beforeEach } from 'vitest'
import { fetchGoogleMapsKey } from '@/composables/api/google'

// Mock the api module
vi.mock('@/composables/api/api', () => ({
  default: {
    get: vi.fn()
  }
}))

describe('Google API Composable', () => {
  let mockApi

  beforeEach(async () => {
    vi.clearAllMocks()
    mockApi = await import('@/composables/api/api')
  })

  describe('fetchGoogleMapsKey', () => {
    it('should call api.get with correct endpoint', async () => {
      const mockResponse = { data: { key: 'AIzaSyTestKey123' } }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchGoogleMapsKey()

      expect(mockApi.default.get).toHaveBeenCalledWith('/google/maps-key')
      expect(result).toEqual(mockResponse)
    })

    it('should handle API errors', async () => {
      const mockError = new Error('Failed to fetch Google Maps key')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(fetchGoogleMapsKey()).rejects.toThrow('Failed to fetch Google Maps key')
      expect(mockApi.default.get).toHaveBeenCalledWith('/google/maps-key')
    })

    it('should handle missing key in response', async () => {
      const mockResponse = { data: {} }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchGoogleMapsKey()

      expect(mockApi.default.get).toHaveBeenCalledWith('/google/maps-key')
      expect(result).toEqual(mockResponse)
    })

    it('should handle null response', async () => {
      const mockResponse = { data: null }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchGoogleMapsKey()

      expect(mockApi.default.get).toHaveBeenCalledWith('/google/maps-key')
      expect(result).toEqual(mockResponse)
    })

    it('should handle network errors', async () => {
      const mockError = new Error('Network timeout')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(fetchGoogleMapsKey()).rejects.toThrow('Network timeout')
      expect(mockApi.default.get).toHaveBeenCalledWith('/google/maps-key')
    })

    it('should handle unauthorized access', async () => {
      const mockError = new Error('Unauthorized access to Google Maps API')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(fetchGoogleMapsKey()).rejects.toThrow('Unauthorized access to Google Maps API')
    })

    it('should maintain API client configuration', async () => {
      mockApi.default.get.mockResolvedValue({ data: { key: 'test-key' } })

      await fetchGoogleMapsKey()

      expect(mockApi.default.get).toHaveBeenCalledTimes(1)
      expect(mockApi.default.get).toHaveBeenCalledWith('/google/maps-key')
    })

    it('should handle multiple sequential calls', async () => {
      mockApi.default.get
        .mockResolvedValueOnce({ data: { key: 'key1' } })
        .mockResolvedValueOnce({ data: { key: 'key2' } })

      const result1 = await fetchGoogleMapsKey()
      const result2 = await fetchGoogleMapsKey()

      expect(result1.data.key).toBe('key1')
      expect(result2.data.key).toBe('key2')
      expect(mockApi.default.get).toHaveBeenCalledTimes(2)
    })
  })
})
