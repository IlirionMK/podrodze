import { describe, it, expect, vi, beforeEach } from 'vitest'
import {
  fetchTripMembers,
  inviteTripMember,
  updateTripMember,
  removeTripMember,
  acceptTripInvite,
  declineTripInvite,
  fetchMyInvites,
  fetchSentInvites
} from '@/composables/api/tripMembers'

// Mock the api module
vi.mock('@/composables/api/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn()
  }
}))

describe('TripMembers API Composable', () => {
  let mockApi

  beforeEach(async () => {
    vi.clearAllMocks()
    mockApi = await import('@/composables/api/api')
  })

  describe('fetchTripMembers', () => {
    it('should call api.get with correct endpoint', async () => {
      const tripId = '123'
      const mockResponse = { data: [{ id: 1, name: 'John' }, { id: 2, name: 'Jane' }] }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchTripMembers(tripId)

      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/members`)
      expect(result).toEqual(mockResponse)
    })

    it('should handle different trip IDs', async () => {
      const tripIds = ['1', 'abc-123', '456-def']
      
      for (const tripId of tripIds) {
        const mockResponse = { data: [{ id: tripId, name: `Member ${tripId}` }] }
        mockApi.default.get.mockResolvedValue(mockResponse)

        await fetchTripMembers(tripId)

        expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/members`)
      }
    })

    it('should handle API errors', async () => {
      const tripId = '999'
      const mockError = new Error('Failed to fetch members')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(fetchTripMembers(tripId)).rejects.toThrow('Failed to fetch members')
      expect(mockApi.default.get).toHaveBeenCalledWith(`/trips/${tripId}/members`)
    })
  })

  describe('inviteTripMember', () => {
    it('should call api.post with correct endpoint and payload', async () => {
      const tripId = '123'
      const payload = { email: 'test@example.com', role: 'member' }
      const mockResponse = { data: { id: 1, email: 'test@example.com' } }
      mockApi.default.post.mockResolvedValue(mockResponse)

      const result = await inviteTripMember(tripId, payload)

      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/members/invite`, payload)
      expect(result).toEqual(mockResponse)
    })

    it('should handle different invitation payloads', async () => {
      const tripId = '456'
      const payloads = [
        { email: 'user1@example.com' },
        { email: 'user2@example.com', role: 'admin' },
        { email: 'user3@example.com', message: 'Join my trip!' }
      ]

      for (const payload of payloads) {
        const mockResponse = { data: { success: true } }
        mockApi.default.post.mockResolvedValue(mockResponse)

        await inviteTripMember(tripId, payload)

        expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/members/invite`, payload)
      }
    })

    it('should handle API errors when inviting', async () => {
      const tripId = '789'
      const payload = { email: 'invalid@example.com' }
      const mockError = new Error('User not found')
      mockApi.default.post.mockRejectedValue(mockError)

      await expect(inviteTripMember(tripId, payload)).rejects.toThrow('User not found')
      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/members/invite`, payload)
    })
  })

  describe('updateTripMember', () => {
    it('should call api.patch with correct endpoint and payload', async () => {
      const tripId = '123'
      const userId = '456'
      const payload = { role: 'admin' }
      const mockResponse = { data: { id: 456, role: 'admin' } }
      mockApi.default.patch.mockResolvedValue(mockResponse)

      const result = await updateTripMember(tripId, userId, payload)

      expect(mockApi.default.patch).toHaveBeenCalledWith(`/trips/${tripId}/members/${userId}`, payload)
      expect(result).toEqual(mockResponse)
    })

    it('should handle different update payloads', async () => {
      const tripId = 'trip-1'
      const userId = 'user-1'
      const payloads = [
        { role: 'member' },
        { permissions: ['edit', 'delete'] },
        { status: 'active' }
      ]

      for (const payload of payloads) {
        const mockResponse = { data: { updated: true } }
        mockApi.default.patch.mockResolvedValue(mockResponse)

        await updateTripMember(tripId, userId, payload)

        expect(mockApi.default.patch).toHaveBeenCalledWith(`/trips/${tripId}/members/${userId}`, payload)
      }
    })

    it('should handle API errors when updating member', async () => {
      const tripId = '123'
      const userId = '456'
      const payload = { role: 'invalid' }
      const mockError = new Error('Invalid role')
      mockApi.default.patch.mockRejectedValue(mockError)

      await expect(updateTripMember(tripId, userId, payload)).rejects.toThrow('Invalid role')
      expect(mockApi.default.patch).toHaveBeenCalledWith(`/trips/${tripId}/members/${userId}`, payload)
    })
  })

  describe('removeTripMember', () => {
    it('should call api.delete with correct endpoint', async () => {
      const tripId = '123'
      const userId = '456'
      const mockResponse = { data: { success: true } }
      mockApi.default.delete.mockResolvedValue(mockResponse)

      const result = await removeTripMember(tripId, userId)

      expect(mockApi.default.delete).toHaveBeenCalledWith(`/trips/${tripId}/members/${userId}`)
      expect(result).toEqual(mockResponse)
    })

    it('should handle different trip and user IDs', async () => {
      const testCases = [
        { tripId: '1', userId: 'a' },
        { tripId: 'trip-2', userId: 'user-2' },
        { tripId: '456', userId: '789' }
      ]

      for (const { tripId, userId } of testCases) {
        const mockResponse = { data: { deleted: true } }
        mockApi.default.delete.mockResolvedValue(mockResponse)

        await removeTripMember(tripId, userId)

        expect(mockApi.default.delete).toHaveBeenCalledWith(`/trips/${tripId}/members/${userId}`)
      }
    })

    it('should handle API errors when removing member', async () => {
      const tripId = '123'
      const userId = '456'
      const mockError = new Error('Cannot remove owner')
      mockApi.default.delete.mockRejectedValue(mockError)

      await expect(removeTripMember(tripId, userId)).rejects.toThrow('Cannot remove owner')
      expect(mockApi.default.delete).toHaveBeenCalledWith(`/trips/${tripId}/members/${userId}`)
    })
  })

  describe('acceptTripInvite', () => {
    it('should call api.post with correct endpoint', async () => {
      const tripId = '123'
      const mockResponse = { data: { status: 'accepted' } }
      mockApi.default.post.mockResolvedValue(mockResponse)

      const result = await acceptTripInvite(tripId)

      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/accept`)
      expect(result).toEqual(mockResponse)
    })

    it('should handle API errors when accepting invite', async () => {
      const tripId = '456'
      const mockError = new Error('Invite expired')
      mockApi.default.post.mockRejectedValue(mockError)

      await expect(acceptTripInvite(tripId)).rejects.toThrow('Invite expired')
      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/accept`)
    })
  })

  describe('declineTripInvite', () => {
    it('should call api.post with correct endpoint', async () => {
      const tripId = '123'
      const mockResponse = { data: { status: 'declined' } }
      mockApi.default.post.mockResolvedValue(mockResponse)

      const result = await declineTripInvite(tripId)

      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/decline`)
      expect(result).toEqual(mockResponse)
    })

    it('should handle API errors when declining invite', async () => {
      const tripId = '789'
      const mockError = new Error('Invite not found')
      mockApi.default.post.mockRejectedValue(mockError)

      await expect(declineTripInvite(tripId)).rejects.toThrow('Invite not found')
      expect(mockApi.default.post).toHaveBeenCalledWith(`/trips/${tripId}/decline`)
    })
  })

  describe('fetchMyInvites', () => {
    it('should call api.get with correct endpoint', async () => {
      const mockResponse = { data: [{ id: 1, trip: 'Paris Trip' }, { id: 2, trip: 'Rome Trip' }] }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchMyInvites()

      expect(mockApi.default.get).toHaveBeenCalledWith('/users/me/invites')
      expect(result).toEqual(mockResponse)
    })

    it('should handle empty invites list', async () => {
      const mockResponse = { data: [] }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchMyInvites()

      expect(mockApi.default.get).toHaveBeenCalledWith('/users/me/invites')
      expect(result.data).toEqual([])
    })

    it('should handle API errors when fetching invites', async () => {
      const mockError = new Error('Failed to fetch invites')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(fetchMyInvites()).rejects.toThrow('Failed to fetch invites')
      expect(mockApi.default.get).toHaveBeenCalledWith('/users/me/invites')
    })
  })

  describe('fetchSentInvites', () => {
    it('should call api.get with correct endpoint', async () => {
      const mockResponse = { data: [{ id: 1, email: 'test@example.com', status: 'pending' }] }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchSentInvites()

      expect(mockApi.default.get).toHaveBeenCalledWith('/users/me/invites/sent')
      expect(result).toEqual(mockResponse)
    })

    it('should handle empty sent invites', async () => {
      const mockResponse = { data: [] }
      mockApi.default.get.mockResolvedValue(mockResponse)

      const result = await fetchSentInvites()

      expect(mockApi.default.get).toHaveBeenCalledWith('/users/me/invites/sent')
      expect(result.data).toEqual([])
    })

    it('should handle API errors when fetching sent invites', async () => {
      const mockError = new Error('Failed to fetch sent invites')
      mockApi.default.get.mockRejectedValue(mockError)

      await expect(fetchSentInvites()).rejects.toThrow('Failed to fetch sent invites')
      expect(mockApi.default.get).toHaveBeenCalledWith('/users/me/invites/sent')
    })
  })

  describe('integration with API client', () => {
    it('should maintain API client configuration', async () => {
      mockApi.default.get.mockResolvedValue({ data: [] })
      mockApi.default.post.mockResolvedValue({ data: {} })
      mockApi.default.patch.mockResolvedValue({ data: {} })
      mockApi.default.delete.mockResolvedValue({ data: {} })

      await fetchTripMembers('123')
      await inviteTripMember('123', { email: 'test@example.com' })
      await updateTripMember('123', '456', { role: 'admin' })
      await removeTripMember('123', '456')
      await acceptTripInvite('123')
      await declineTripInvite('123')
      await fetchMyInvites()
      await fetchSentInvites()

      expect(mockApi.default.get).toHaveBeenCalledTimes(3)
      expect(mockApi.default.post).toHaveBeenCalledTimes(3)
      expect(mockApi.default.patch).toHaveBeenCalledTimes(1)
      expect(mockApi.default.delete).toHaveBeenCalledTimes(1)
    })
  })
})
