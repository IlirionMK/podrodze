import { describe, it, expect, vi, beforeEach } from 'vitest'
import { fetchTripItinerary, fetchTripItineraryFull } from '@/composables/api/itinerary'

// Mock the api module
vi.mock('@/composables/api/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn()
  }
}))

describe('Itinerary API Composable', () => {
  let mockApi

  beforeEach(async () => {
    vi.clearAllMocks()
    mockApi = await import('@/composables/api/api.js')
  })

  describe('fetchTripItinerary', () => {
    it('should call api.get with correct endpoint', async () => {
      const tripId = '123'
      const mockResponse = { 
        data: { 
          id: '123',
          days: [
            {
              date: '2024-06-01',
              activities: ['Visit Eiffel Tower', 'Louvre Museum']
            }
          ]
        } 
      }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchTripItinerary(tripId)

      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate`)
      expect(result).toEqual(mockResponse)
    })

    it('should handle different trip IDs', async () => {
      const tripIds = ['1', 'abc-123', '456-def']
      
      for (const tripId of tripIds) {
        const mockResponse = { data: { id: tripId, itinerary: `Itinerary for ${tripId}` } }
        mockApi.default.get.mockResolvedValue(mockResponse)

        await fetchTripItinerary(tripId)

        expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate`)
      }
    })

    it('should handle API errors', async () => {
      const tripId = '999'
      const mockError = new Error('Failed to generate itinerary')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(fetchTripItinerary(tripId)).rejects.toThrow('Failed to generate itinerary')
      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate`)
    })

    it('should handle trip not found', async () => {
      const tripId = 'nonexistent'
      const mockError = new Error('Trip not found')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(fetchTripItinerary(tripId)).rejects.toThrow('Trip not found')
      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate`)
    })

    it('should handle empty itinerary response', async () => {
      const tripId = 'empty-trip'
      const mockResponse = { data: { days: [] } }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchTripItinerary(tripId)

      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate`)
      expect(result.data.days).toEqual([])
    })

    it('should handle null response', async () => {
      const tripId = 'null-trip'
      const mockResponse = { data: null }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchTripItinerary(tripId)

      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate`)
      expect(result.data).toBeNull()
    })
  })

  describe('fetchTripItineraryFull', () => {
    it('should call api.post with correct endpoint', async () => {
      const tripId = '123'
      const mockResponse = { 
        data: { 
          id: '123',
          fullItinerary: {
            overview: 'Complete Paris experience',
            days: [
              {
                date: '2024-06-01',
                activities: ['Visit Eiffel Tower', 'Louvre Museum', 'Seine River Cruise'],
                meals: ['Breakfast at hotel', 'Lunch at café', 'Dinner at restaurant'],
                transportation: ['Metro to Eiffel Tower', 'Walk to Louvre']
              }
            ],
            recommendations: ['Bring comfortable shoes', 'Book museum tickets in advance']
          }
        } 
      }
      mockApi.default.post.mockResolvedValue(mockResponse)

      const result = await fetchTripItineraryFull(tripId)

      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate-full`)
      expect(result).toEqual(mockResponse)
    })

    it('should handle different trip IDs for full itinerary', async () => {
      const tripIds = ['trip-1', 'trip-2', 'trip-3']
      
      for (const tripId of tripIds) {
        const mockResponse = { data: { id: tripId, fullItinerary: `Full itinerary for ${tripId}` } }
        mockApi.default.post.mockResolvedValue(mockResponse)

        await fetchTripItineraryFull(tripId)

        expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate-full`)
      }
    })

    it('should handle API errors when generating full itinerary', async () => {
      const tripId = '789'
      const mockError = new Error('Failed to generate full itinerary')
      mockApi.default.post.mockRejectedValue(mockError)

      await expect(fetchTripItineraryFull(tripId)).rejects.toThrow('Failed to generate full itinerary')
      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate-full`)
    })

    it('should handle insufficient data for full itinerary', async () => {
      const tripId = 'incomplete-trip'
      const mockError = new Error('Insufficient trip data for full itinerary generation')
      mockApi.default.post.mockRejectedValue(mockError)

      await expect(fetchTripItineraryFull(tripId)).rejects.toThrow('Insufficient trip data for full itinerary generation')
      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate-full`)
    })

    it('should handle timeout errors for full itinerary generation', async () => {
      const tripId = 'timeout-trip'
      const mockError = new Error('Itinerary generation timeout')
      mockApi.default.post.mockRejectedValue(mockError)

      await expect(fetchTripItineraryFull(tripId)).rejects.toThrow('Itinerary generation timeout')
      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate-full`)
    })

    it('should handle empty full itinerary response', async () => {
      const tripId = 'empty-full-trip'
      const mockResponse = { data: { fullItinerary: null } }
      mockApi.default.post.mockResolvedValue(mockResponse)

      const result = await fetchTripItineraryFull(tripId)

      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate-full`)
      expect(result.data.fullItinerary).toBeNull()
    })
  })

  describe('integration between functions', () => {
    it('should maintain API client configuration across both functions', async () => {
      mockApi.default.get.mockResolvedValue({ data: { days: [] } })
      mockApi.default.post.mockResolvedValue({ data: { fullItinerary: {} } })

      await fetchTripItinerary('123')
      await fetchTripItineraryFull('123')

      expect(mockApi.default.get).toHaveBeenCalledTimes(1)
      expect(mockApi.default.post).toHaveBeenCalledTimes(1)
    })

    it('should handle sequential calls correctly', async () => {
      const tripId = 'sequential-trip'
      
      mockApi.default.get.mockResolvedValue({ data: { id: tripId, days: [] } })
      mockApi.default.post.mockResolvedValue({ data: { id: tripId, fullItinerary: {} } })

      const basicResult = await fetchTripItinerary(tripId)
      const fullResult = await fetchTripItineraryFull(tripId)

      expect(basicResult.data.id).toBe(tripId)
      expect(fullResult.data.id).toBe(tripId)
      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate`)
      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate-full`)
    })

    it('should handle errors independently', async () => {
      const tripId = 'error-trip'
      
      mockApi.default.get.mockRejectedValue(new Error('Basic itinerary failed'))
      mockApi.default.post.mockResolvedValue({ data: { fullItinerary: {} } })

      await expect(fetchTripItinerary(tripId)).rejects.toThrow('Basic itinerary failed')
      await expect(fetchTripItineraryFull(tripId)).resolves.not.toThrow()

      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate`)
      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate-full`)
    })
  })

  describe('edge cases', () => {
    it('should handle special characters in trip ID', async () => {
      const tripId = 'trip-with-special-chars_123'
      
      mockApi.default.get.mockResolvedValue({ data: { id: tripId } })
      mockApi.default.post.mockResolvedValue({ data: { id: tripId } })

      await fetchTripItinerary(tripId)
      await fetchTripItineraryFull(tripId)

      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate`)
      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate-full`)
    })

    it('should handle very long trip IDs', async () => {
      const tripId = 'very-long-trip-id-that-might-cause-issues-in-some-systems-123456789'
      
      mockApi.default.get.mockResolvedValue({ data: { id: tripId } })
      mockApi.default.post.mockResolvedValue({ data: { id: tripId } })

      await fetchTripItinerary(tripId)
      await fetchTripItineraryFull(tripId)

      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate`)
      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/itinerary/generate-full`)
    })
  })
})
