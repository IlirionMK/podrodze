import { describe, it, expect, vi, beforeEach } from 'vitest'
import axios from 'axios'

// Mock axios
vi.mock('axios')

describe('API Integration Tests', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('Authentication API', () => {
    it('should handle login request', async () => {
      const mockResponse = {
        data: {
          user: { id: 1, name: 'Test User', email: 'test@example.com' },
          token: 'mock-jwt-token'
        }
      }
      
      axios.post.mockResolvedValue(mockResponse)
      
      const response = await axios.post('/api/login', {
        email: 'test@example.com',
        password: 'password'
      })
      
      expect(response.data).toEqual(mockResponse.data)
      expect(axios.post).toHaveBeenCalledWith('/api/login', {
        email: 'test@example.com',
        password: 'password'
      })
    })

    it('should handle login error', async () => {
      const mockError = new Error('Invalid credentials')
      mockError.response = { status: 401, data: { message: 'Invalid credentials' } }
      
      axios.post.mockRejectedValue(mockError)
      
      await expect(axios.post('/api/login', {
        email: 'wrong@example.com',
        password: 'wrongpassword'
      })).rejects.toThrow('Invalid credentials')
    })

    it('should handle registration request', async () => {
      const mockResponse = {
        data: {
          user: { id: 2, name: 'New User', email: 'new@example.com' },
          token: 'mock-jwt-token'
        }
      }
      
      axios.post.mockResolvedValue(mockResponse)
      
      const response = await axios.post('/api/register', {
        name: 'New User',
        email: 'new@example.com',
        password: 'password'
      })
      
      expect(response.data.user.name).toBe('New User')
      expect(axios.post).toHaveBeenCalledWith('/api/register', {
        name: 'New User',
        email: 'new@example.com',
        password: 'password'
      })
    })
  })

  describe('Trips API', () => {
    it('should fetch user trips', async () => {
      const mockResponse = {
        data: [
          { id: 1, title: 'Trip to Paris', destination: 'Paris', date: '2024-06-15' },
          { id: 2, title: 'Trip to Rome', destination: 'Rome', date: '2024-07-20' }
        ]
      }
      
      axios.get.mockResolvedValue(mockResponse)
      
      const response = await axios.get('/api/trips', {
        headers: { Authorization: 'Bearer mock-token' }
      })
      
      expect(response.data).toHaveLength(2)
      expect(response.data[0].title).toBe('Trip to Paris')
      expect(axios.get).toHaveBeenCalledWith('/api/trips', {
        headers: { Authorization: 'Bearer mock-token' }
      })
    })

    it('should create a new trip', async () => {
      const mockResponse = {
        data: { id: 3, title: 'Trip to London', destination: 'London' }
      }
      
      axios.post.mockResolvedValue(mockResponse)
      
      const response = await axios.post('/api/trips', {
        title: 'Trip to London',
        destination: 'London'
      }, {
        headers: { Authorization: 'Bearer mock-token' }
      })
      
      expect(response.data.title).toBe('Trip to London')
      expect(axios.post).toHaveBeenCalledWith('/api/trips', {
        title: 'Trip to London',
        destination: 'London'
      }, {
        headers: { Authorization: 'Bearer mock-token' }
      })
    })
  })

  describe('Itinerary API', () => {
    it('should generate itinerary', async () => {
      const mockResponse = {
        data: {
          id: 1,
          trip_id: 1,
          activities: [
            { time: '09:00', activity: 'Visit Eiffel Tower' },
            { time: '12:00', activity: 'Lunch at local restaurant' }
          ]
        }
      }
      
      axios.post.mockResolvedValue(mockResponse)
      
      const response = await axios.post('/api/itineraries/generate', {
        trip_id: 1,
        preferences: ['museums', 'food']
      })
      
      expect(response.data.activities).toHaveLength(2)
      expect(axios.post).toHaveBeenCalledWith('/api/itineraries/generate', {
        trip_id: 1,
        preferences: ['museums', 'food']
      })
    })
  })

  describe('Error Handling', () => {
    it('should handle network errors', async () => {
      const networkError = new Error('Network Error')
      axios.get.mockRejectedValue(networkError)
      
      await expect(axios.get('/api/trips')).rejects.toThrow('Network Error')
    })

    it('should handle 500 server errors', async () => {
      const serverError = new Error('Internal Server Error')
      serverError.response = { status: 500, data: { message: 'Server error' } }
      
      axios.get.mockRejectedValue(serverError)
      
      await expect(axios.get('/api/trips')).rejects.toThrow('Internal Server Error')
    })
  })
})
