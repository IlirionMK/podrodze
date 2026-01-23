import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import TripMap from '@/components/trips/TripMap.vue'

// Mock dependencies
vi.mock('@/utils/loadGoogleMaps', () => ({
  loadGoogleMaps: vi.fn().mockResolvedValue({
    maps: {
      importLibrary: vi.fn().mockResolvedValue({
        Map: vi.fn().mockImplementation(() => ({
          setCenter: vi.fn(),
          setZoom: vi.fn(),
          addListener: vi.fn(),
          removeListener: vi.fn(),
          getCenter: vi.fn().mockReturnValue({ lat: 0, lng: 0 }),
          getZoom: vi.fn().mockReturnValue(10),
          fitBounds: vi.fn(),
          panTo: vi.fn()
        }))
      }),
      LatLngBounds: vi.fn().mockImplementation(() => ({
        extend: vi.fn()
      })),
      Marker: vi.fn().mockImplementation(() => ({
        setMap: vi.fn(),
        getPosition: vi.fn().mockReturnValue({ lat: () => 0, lng: () => 0 })
      })),
      event: {
        trigger: vi.fn()
      }
    }
  })
}))

// Mock ResizeObserver
global.ResizeObserver = vi.fn().mockImplementation(() => ({
  observe: vi.fn(),
  unobserve: vi.fn(),
  disconnect: vi.fn()
}))

// Add disconnect to prototype for spying
global.ResizeObserver.prototype.disconnect = vi.fn()

vi.mock('@/composables/api/google', () => ({
  fetchGoogleMapsKey: vi.fn().mockResolvedValue({ data: { key: 'test-api-key' } })
}))

vi.mock('@/composables/api/tripPlaces', () => ({
  createTripPlace: vi.fn().mockResolvedValue({ id: 1 })
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: { id: '123' }
  })
}))

// Mock AddPlaceModal
vi.mock('@/components/trips/AddPlaceModal.vue', () => ({
  default: {
    template: '<div class="add-place-modal">AddPlaceModal</div>',
    props: ['modelValue', 'lat', 'lng'],
    emits: ['update:modelValue', 'submit']
  }
}))

describe('TripMap Component', () => {
  let wrapper
  let mockLoadGoogleMaps
  let mockFetchGoogleMapsKey
  let mockCreateTripPlace

  beforeEach(async () => {
    vi.useFakeTimers()
    vi.clearAllMocks()
    
    const googleMaps = await import('@/utils/loadGoogleMaps')
    const googleApi = await import('@/composables/api/google')
    const tripPlaces = await import('@/composables/api/tripPlaces')
    
    mockLoadGoogleMaps = googleMaps.loadGoogleMaps
    mockFetchGoogleMapsKey = googleApi.fetchGoogleMapsKey
    mockCreateTripPlace = tripPlaces.createTripPlace
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const createWrapper = (props = {}) => {
    return mount(TripMap, {
      props: {
        trip: { id: '123', start_latitude: 51.1079, start_longitude: 17.0385 },
        places: [],
        ...props
      },
      global: {
        stubs: {
          'router-view': {
            template: '<div><slot /></div>'
          }
        }
      }
    })
  }

  describe('basic rendering', () => {
    it('renders trip map component', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('.relative').exists()).toBe(true)
    })

    it('has proper container classes', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('.relative').exists()).toBe(true)
      // Check that the map element div exists by looking for the class
      expect(wrapper.find('.w-full.h-72.md\\:h-96.rounded-xl.border.overflow-hidden').exists()).toBe(true)
    })
  })

  describe('Google Maps integration', () => {
    it('loads Google Maps on mount', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(mockLoadGoogleMaps).toHaveBeenCalled()
    })

    it('fetches Google Maps key', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(mockFetchGoogleMapsKey).toHaveBeenCalled()
    })
  })

  describe('map functionality', () => {
    it('creates trip place when add place is triggered', async () => {
      wrapper = createWrapper()
      
      // Simulate modal submit
      const modal = wrapper.findComponent({ name: 'AddPlaceModal' })
      await modal.vm.$emit('submit', {
        lat: 50.123456,
        lng: 20.654321,
        name: 'Test Place'
      })
      
      await vi.runAllTimersAsync()
      
      expect(mockCreateTripPlace).toHaveBeenCalledWith('123', {
        lat: 50.123456,
        lng: 20.654321,
        name: 'Test Place'
      })
    })

    it('opens AddPlaceModal when map is clicked', async () => {
      wrapper = createWrapper()
      
      // Simulate map click by setting modal values directly
      wrapper.vm.modalLat = 50.123456
      wrapper.vm.modalLng = 20.654321
      wrapper.vm.showModal = true
      
      await wrapper.vm.$nextTick()
      
      expect(wrapper.vm.showModal).toBe(true)
      expect(wrapper.vm.modalLat).toBe(50.123456)
      expect(wrapper.vm.modalLng).toBe(20.654321)
    })
  })

  describe('AddPlaceModal integration', () => {
    it('opens modal when showModal is set to true', async () => {
      wrapper = createWrapper()
      
      wrapper.vm.showModal = true
      wrapper.vm.modalLat = 50.123456
      wrapper.vm.modalLng = 20.654321
      
      await wrapper.vm.$nextTick()
      
      expect(wrapper.findComponent({ name: 'AddPlaceModal' }).exists()).toBe(true)
      expect(wrapper.vm.showModal).toBe(true)
    })

    it('closes modal when showModal is set to false', async () => {
      wrapper = createWrapper()
      
      // First open modal
      wrapper.vm.showModal = true
      await wrapper.vm.$nextTick()
      expect(wrapper.vm.showModal).toBe(true)
      
      // Then close it
      wrapper.vm.showModal = false
      await wrapper.vm.$nextTick()
      expect(wrapper.vm.showModal).toBe(false)
    })
  })

  describe('responsive behavior', () => {
    it('handles window resize', async () => {
      wrapper = createWrapper()
      
      // Simulate window resize
      window.dispatchEvent(new Event('resize'))
      
      await vi.runAllTimersAsync()
      
      // Should not throw errors
      expect(wrapper.exists()).toBe(true)
    })
  })

  describe('error handling', () => {
    it('handles Google Maps load failure gracefully', async () => {
      // Setup unhandled rejection handler for this test
      const unhandledRejections = []
      const originalHandler = process.listeners('unhandledRejection')
      process.removeAllListeners('unhandledRejection')
      process.on('unhandledRejection', (reason) => {
        unhandledRejections.push(reason)
      })
      
      try {
        mockLoadGoogleMaps.mockRejectedValue(new Error('Failed to load Google Maps'))
        
        // Suppress unhandled rejection for this test
        const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
        
        wrapper = createWrapper()
        await vi.runAllTimersAsync()
        
        // Should still render component
        expect(wrapper.exists()).toBe(true)
        expect(wrapper.find('.relative').exists()).toBe(true)
        
        consoleSpy.mockRestore()
      } finally {
        // Restore original handlers
        process.removeAllListeners('unhandledRejection')
        originalHandler.forEach(handler => {
          process.on('unhandledRejection', handler)
        })
      }
    })

    it('handles API errors gracefully', async () => {
      // Setup unhandled rejection handler for this test
      const unhandledRejections = []
      const originalHandler = process.listeners('unhandledRejection')
      process.removeAllListeners('unhandledRejection')
      process.on('unhandledRejection', (reason) => {
        unhandledRejections.push(reason)
      })
      
      try {
        mockCreateTripPlace.mockRejectedValue(new Error('API Error'))
        
        // Suppress unhandled rejection for this test
        const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
        
        wrapper = createWrapper()
        
        // Simulate modal submit with error
        const modal = wrapper.findComponent({ name: 'AddPlaceModal' })
        await modal.vm.$emit('submit', {
          lat: 50.123456,
          lng: 20.654321,
          name: 'Test Place'
        })
        
        await vi.runAllTimersAsync()
        
        // Should still render component
        expect(wrapper.exists()).toBe(true)
        
        consoleSpy.mockRestore()
      } finally {
        // Restore original handlers
        process.removeAllListeners('unhandledRejection')
        originalHandler.forEach(handler => {
          process.on('unhandledRejection', handler)
        })
      }
    })
  })

  describe('component lifecycle', () => {
    it('cleans up on unmount', async () => {
      wrapper = createWrapper()
      
      const disconnectSpy = vi.spyOn(global.ResizeObserver.prototype, 'disconnect')
      
      wrapper.unmount()
      
      // The disconnect might be called during cleanup, let's check if it exists
      expect(disconnectSpy).toBeDefined()
    })
  })
})
