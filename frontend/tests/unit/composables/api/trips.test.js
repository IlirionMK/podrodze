import { describe, it, expect, vi, beforeEach } from 'vitest'
import { fetchUserTrips, fetchTrip, createTrip } from '@/composables/api/trips'

// Mock the api module
vi.mock('@/composables/api/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn()
  }
}))

describe('Trips API Composable', () => {
  let mockApi

  beforeEach(async () => {
    vi.clearAllMocks()
    mockApi = await import('@/composables/api/api')
  })

  describe('fetchUserTrips', () => {
    it('should call api.get with correct endpoint', async () => {
      const mockResponse = { data: [{ id: 1, title: 'Test Trip' }] }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchUserTrips()

      expect(mockApi.default.get).toHaveBeenCalledWith('/trips')
      expect(result).toEqual(mockResponse)
    })

    it('should handle API errors', async () => {
      const mockError = new Error('Network error')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(fetchUserTrips()).rejects.toThrow('Network error')
      expect(mockApi.default.get).toHaveBeenCalledWith('/trips')
    })
  })

  describe('fetchTrip', () => {
    it('should call api.get with correct endpoint and trip ID', async () => {
      const tripId = '123'
      const mockResponse = { data: { id: 123, title: 'Specific Trip' } }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchTrip(tripId)

      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}`)
      expect(result).toEqual(mockResponse)
    })

    it('should handle different trip IDs', async () => {
      const tripIds = ['1', 'abc-123', '456-def']
      
      for (const tripId of tripIds) {
        const mockResponse = { data: { id: tripId, title: `Trip ${tripId}` } }
        mockApi.default.get.mockResolvedValue(mockResponse)

        await fetchTrip(tripId)

        expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}`)
      }
    })

    it('should handle API errors when fetching specific trip', async () => {
      const tripId = '999'
      const mockError = new Error('Trip not found')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(fetchTrip(tripId)).rejects.toThrow('Trip not found')
      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}`)
    })
  })

  describe('createTrip', () => {
    it('should call api.post with correct endpoint and payload', async () => {
      const payload = {
        title: 'New Trip',
        destination: 'Paris',
        start_date: '2024-06-01',
        end_date: '2024-06-07'
      }
      const mockResponse = { data: { id: 1, ...payload } }
      mockApi.default.post.mockResolvedValue(mockResponse)

      const result = await createTrip(payload)

      expect(mockApi.default.post).toHaveBeenCalledWith('/trips', payload)
      expect(result).toEqual(mockResponse)
    })

    it('should handle different payloads', async () => {
      const payloads = [
        { title: 'Trip 1', destination: 'Rome' },
        { title: 'Trip 2', destination: 'London', description: 'Amazing trip' },
        { title: 'Trip 3', destination: 'Barcelona', budget: 1000 }
      ]

      for (const payload of payloads) {
        const mockResponse = { data: { id: Math.random(), ...payload } }
        mockApi.default.post.mockResolvedValue(mockResponse)

        await createTrip(payload)

        expect(mockApi.default.post).toHaveBeenCalledWith('/trips', payload)
      }
    })

    it('should handle API errors when creating trip', async () => {
      const payload = { title: 'Invalid Trip' }
      const mockError = new Error('Validation failed')
      mockApi.default.post.mockRejectedValue(mockError)

      await expect(createTrip(payload)).rejects.toThrow('Validation failed')
      expect(mockApi.default.post).toHaveBeenCalledWith('/trips', payload)
    })

    it('should handle empty payload', async () => {
      const payload = {}
      const mockError = new Error('Required fields missing')
      mockApi.default.post.mockRejectedValue(mockError)

      await expect(createTrip(payload)).rejects.toThrow('Required fields missing')
      expect(mockApi.default.post).toHaveBeenCalledWith('/trips', payload)
    })
  })

  describe('integration with API client', () => {
    it('should maintain API client configuration', async () => {
      // Test that all functions use the same API client instance
      mockApi.default.get.mockResolvedValue({ data: [] })
      mockApi.default.post.mockResolvedValue({ data: {} })

      await fetchUserTrips()
      await fetchTrip('123')
      await createTrip({ title: 'Test' })

      // All should use the same mocked API client
      expect(mockApi.default.get).toHaveBeenCalledTimes(2)
      expect(mockApi.default.post).toHaveBeenCalledTimes(1)
    })
  })
})
