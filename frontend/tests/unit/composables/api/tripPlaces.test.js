import { describe, it, expect, vi, beforeEach } from 'vitest'
import {
  fetchTripPlaces,
  createTripPlace,
  updateTripPlace,
  voteTripPlace,
  deleteTripPlace,
  searchExternalPlaces,
  getPlaceDetails,
  getAiSuggestions
} from '@/composables/api/tripPlaces'

// Mock the api module
vi.mock('@/composables/api/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn()
  }
}))

describe('TripPlaces API Composable', () => {
  let mockApi

  beforeEach(async () => {
    vi.clearAllMocks()
    mockApi = await import('@/composables/api/api.js')
  })

  describe('fetchTripPlaces', () => {
    it('should call api.get with correct endpoint', async () => {
      const tripId = '123'
      const mockResponse = { data: [{ id: 1, name: 'Test Place' }] }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchTripPlaces(tripId)

      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/places`)
      expect(result).toEqual(mockResponse)
    })

    it('should handle API errors', async () => {
      const tripId = '999'
      const mockError = new Error('Trip not found')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(fetchTripPlaces(tripId)).rejects.toThrow('Trip not found')
      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/places`)
    })
  })

  describe('createTripPlace', () => {
    it('should call api.post with correct endpoint and payload', async () => {
      const tripId = '123'
      const payload = {
        name: 'New Place',
        latitude: 48.8584,
        longitude: 2.2945,
        description: 'Amazing place'
      }
      const mockResponse = { data: { id: 1, ...payload } }
      mockApi.default.post.mockResolvedValue(mockResponse)

      const result = await createTripPlace(tripId, payload)

      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/places`, payload)
      expect(result).toEqual(mockResponse)
    })

    it('should handle validation errors', async () => {
      const tripId = '123'
      const payload = { name: '' } // Invalid payload
      const mockError = new Error('Validation failed')
      mockApi.default.post.mockRejectedValue(mockError)

      await expect(createTripPlace(tripId, payload)).rejects.toThrow('Validation failed')
      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/places`, payload)
    })
  })

  describe('updateTripPlace', () => {
    it('should call api.put with correct endpoint and payload', async () => {
      const tripId = '123'
      const placeId = '456'
      const payload = {
        name: 'Updated Place',
        description: 'Updated description'
      }
      const mockResponse = { data: { id: placeId, ...payload } }
      mockApi.default.put.mockResolvedValue(mockResponse)

      const result = await updateTripPlace(tripId, placeId, payload)

      expect(mockApi.default.put).toHaveBeenCalledWith(`/trips/${tripId}/places/${placeId}`, payload)
      expect(result).toEqual(mockResponse)
    })

    it('should handle not found errors', async () => {
      const tripId = '123'
      const placeId = '999'
      const payload = { name: 'Updated' }
      const mockError = new Error('Place not found')
      mockApi.default.put.mockRejectedValue(mockError)

      await expect(updateTripPlace(tripId, placeId, payload)).rejects.toThrow('Place not found')
      expect(mockApi.default.put).toHaveBeenCalledWith(`/trips/${tripId}/places/${placeId}`, payload)
    })
  })

  describe('voteTripPlace', () => {
    it('should call api.post with correct endpoint', async () => {
      const tripId = '123'
      const placeId = '456'
      const mockResponse = { data: { success: true, votes: 5 } }
      mockApi.default.post.mockResolvedValue(mockResponse)

      const result = await voteTripPlace(tripId, placeId)

      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/places/${placeId}/vote`)
      expect(result).toEqual(mockResponse)
    })

    it('should handle duplicate vote errors', async () => {
      const tripId = '123'
      const placeId = '456'
      const mockError = new Error('Already voted')
      mockApi.default.post.mockRejectedValue(mockError)

      await expect(voteTripPlace(tripId, placeId)).rejects.toThrow('Already voted')
      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/places/${placeId}/vote`)
    })
  })

  describe('deleteTripPlace', () => {
    it('should call api.delete with correct endpoint', async () => {
      const tripId = '123'
      const placeId = '456'
      const mockResponse = { data: { success: true } }
      mockApi.default.delete.mockResolvedValue(mockResponse)

      const result = await deleteTripPlace(tripId, placeId)

      expect(mockApi.default.delete).toHaveBeenCalledWith(`/trips/${tripId}/places/${placeId}`)
      expect(result).toEqual(mockResponse)
    })

    it('should handle unauthorized deletion errors', async () => {
      const tripId = '123'
      const placeId = '456'
      const mockError = new Error('Unauthorized')
      mockApi.default.delete.mockRejectedValue(mockError)

      await expect(deleteTripPlace(tripId, placeId)).rejects.toThrow('Unauthorized')
      expect(mockApi.default.delete).toHaveBeenCalledWith(`/trips/${tripId}/places/${placeId}`)
    })
  })

  describe('searchExternalPlaces', () => {
    it('should call api.get with query parameter', async () => {
      const query = 'Eiffel Tower'
      const mockResponse = { data: [{ place_id: 'abc123', description: 'Eiffel Tower, Paris' }] }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await searchExternalPlaces(query)

      expect(mockApi.default.get).toHaveBeenCalledWith('/places/autocomplete', {
        params: { q: query }
      })
      expect(result).toEqual(mockResponse)
    })

    it('should handle empty query', async () => {
      const query = ''
      const mockResponse = { data: [] }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await searchExternalPlaces(query)

      expect(mockApi.default.get).toHaveBeenCalledWith('/places/autocomplete', {
        params: { q: query }
      })
      expect(result).toEqual(mockResponse)
    })

    it('should handle API errors', async () => {
      const query = 'Invalid query'
      const mockError = new Error('Search failed')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(searchExternalPlaces(query)).rejects.toThrow('Search failed')
    })
  })

  describe('getPlaceDetails', () => {
    it('should call api.get with correct Google Place ID', async () => {
      const googlePlaceId = 'abc123'
      const mockResponse = {
        data: {
          place_id: googlePlaceId,
          name: 'Eiffel Tower',
          formatted_address: 'Champ de Mars, 5 Avenue Anatole France, 75007 Paris, France',
          geometry: {
            location: { lat: 48.8584, lng: 2.2945 }
          }
        }
      }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await getPlaceDetails(googlePlaceId)

      expect(mockApi.default.get).toHaveBeenCalledWith(`/places/google/${googlePlaceId}`)
      expect(result).toEqual(mockResponse)
    })

    it('should handle invalid place ID', async () => {
      const googlePlaceId = 'invalid'
      const mockError = new Error('Place not found')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(getPlaceDetails(googlePlaceId)).rejects.toThrow('Place not found')
      expect(mockApi.default.get).toHaveBeenCalledWith(`/places/google/${googlePlaceId}`)
    })
  })

  describe('getAiSuggestions', () => {
    it('should call api.get with trip ID and default params', async () => {
      const tripId = '123'
      const mockResponse = {
        data: [
          { name: 'Louvre Museum', reason: 'Popular cultural attraction' },
          { name: 'Notre-Dame', reason: 'Historic landmark' }
        ]
      }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await getAiSuggestions(tripId)

      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/places/suggestions`, {
        params: {}
      })
      expect(result).toEqual(mockResponse)
    })

    it('should call api.get with custom params', async () => {
      const tripId = '123'
      const params = {
        preferences: ['museums', 'history'],
        budget: 100,
        duration: 3
      }
      const mockResponse = {
        data: [
          { name: 'Museum Visit', reason: 'Matches your preferences' }
        ]
      }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await getAiSuggestions(tripId, params)

      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/places/suggestions`, {
        params
      })
      expect(result).toEqual(mockResponse)
    })

    it('should handle AI service unavailable', async () => {
      const tripId = '123'
      const mockError = new Error('AI service unavailable')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(getAiSuggestions(tripId)).rejects.toThrow('AI service unavailable')
      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/places/suggestions`, {
        params: {}
      })
    })
  })

  describe('integration tests', () => {
    it('should maintain consistent API client usage across all functions', async () => {
      mockApi.default.get.mockResolvedValue({ data: [] })
      mockApi.default.post.mockResolvedValue({ data: {} })
      mockApi.default.put.mockResolvedValue({ data: {} })
      mockApi.default.delete.mockResolvedValue({ data: {} })

      await fetchTripPlaces('123')
      await createTripPlace('123', { name: 'Test' })
      await updateTripPlace('123', '456', { name: 'Updated' })
      await voteTripPlace('123', '456')
      await deleteTripPlace('123', '456')
      await searchExternalPlaces('test')
      await getPlaceDetails('abc123')
      await getAiSuggestions('123')

      // All functions should use the same API client
      expect(mockApi.default.get).toHaveBeenCalledTimes(4)
      expect(mockApi.default.post).toHaveBeenCalledTimes(2)
      expect(mockApi.default.put).toHaveBeenCalledTimes(1)
      expect(mockApi.default.delete).toHaveBeenCalledTimes(1)
    })
  })
})
