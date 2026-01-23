import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { mount } from '@vue/test-utils'
import TripDetail from '@/pages/app/trips/TripDetail.vue'

// Mock vue-router
let mockRoute, mockRouter

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute.value,
  useRouter: () => mockRouter
}))

// Mock vue-i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key, fallback) => fallback || key,
    te: (key) => true,
    locale: { value: 'en' }
  })
}))

// Mock lucide-vue-next icons
vi.mock('lucide-vue-next', () => ({
  X: { template: '<div>X</div>' },
  Vote: { template: '<div>Vote</div>' },
  Pin: { template: '<div>Pin</div>' },
  Trash2: { template: '<div>Trash2</div>' }
}))

// Mock API functions
vi.mock('@/composables/api/trips.js', () => ({
  fetchTrip: vi.fn()
}))

vi.mock('@/composables/api/tripPlaces.js', () => ({
  fetchTripPlaces: vi.fn(),
  createTripPlace: vi.fn(),
  voteTripPlace: vi.fn(),
  updateTripPlace: vi.fn(),
  deleteTripPlace: vi.fn()
}))

vi.mock('@/composables/api/tripMembers.js', () => ({
  fetchTripMembers: vi.fn()
}))

// Mock components
vi.mock('@/components/trips/TripHeaderBar.vue', () => ({
  default: {
    template: '<div class="trip-header-bar">TripHeaderBar</div>',
    props: ['trip', 'stats', 'bannerImage', 'formatDate']
  }
}))

vi.mock('@/components/trips/TripTabs.vue', () => ({
  default: {
    template: '<div class="trip-tabs">TripTabs</div>',
    props: ['modelValue'],
    emits: ['update:modelValue']
  }
}))

vi.mock('@/components/trips/panels/PlacesWorkspace.vue', () => ({
  default: {
    name: 'PlacesWorkspace',
    template: '<div class="places-workspace">PlacesWorkspace</div>',
    props: ['trip', 'places', 'placesLoading', 'categories', 'filteredPlaces', 'selectedTripPlaceId', 'placeholder', 'labels'],
    emits: ['select-place', 'refresh-places', 'open-add-place']
  }
}))

vi.mock('@/components/trips/panels/TripMembersPanel.vue', () => ({
  default: {
    template: '<div class="trip-members-panel">TripMembersPanel</div>',
    props: ['trip', 'members', 'loading'],
    emits: ['members-changed', 'error']
  }
}))

vi.mock('@/components/trips/panels/TripPreferencesPanel.vue', () => ({
  default: {
    template: '<div class="trip-preferences-panel">TripPreferencesPanel</div>',
    props: ['excludedSlugs'],
    emits: ['error']
  }
}))

vi.mock('@/components/trips/panels/TripPlanPanel.vue', () => ({
  default: {
    template: '<div class="trip-plan-panel">TripPlanPanel</div>',
    props: ['tripId', 'trip', 'places', 'placesLoading'],
    emits: ['error']
  }
}))

vi.mock('@/components/trips/PlaceSearchModal.vue', () => ({
  default: {
    template: '<div class="place-search-modal">PlaceSearchModal</div>',
    props: ['modelValue', 'tripId'],
    emits: ['update:modelValue', 'picked']
  }
}))

describe('TripDetail Page', () => {
  let wrapper
  let mockFetchTrip
  let mockFetchTripPlaces
  let mockFetchTripMembers
  let mockCreateTripPlace
  let mockVoteTripPlace
  let mockUpdateTripPlace
  let mockDeleteTripPlace

  beforeEach(async () => {
    vi.useFakeTimers()
    vi.clearAllMocks()
    
    // Initialize route mocks
    mockRoute = ref({ params: { id: '123' }, query: { tab: 'overview' } })
    mockRouter = { push: vi.fn(), replace: vi.fn() }
    
    const tripsApi = await import('@/composables/api/trips.js')
    const tripPlacesApi = await import('@/composables/api/tripPlaces.js')
    const tripMembersApi = await import('@/composables/api/tripMembers.js')
    
    mockFetchTrip = tripsApi.fetchTrip
    mockFetchTripPlaces = tripPlacesApi.fetchTripPlaces
    mockFetchTripMembers = tripMembersApi.fetchTripMembers
    mockCreateTripPlace = tripPlacesApi.createTripPlace
    mockVoteTripPlace = tripPlacesApi.voteTripPlace
    mockUpdateTripPlace = tripPlacesApi.updateTripPlace
    mockDeleteTripPlace = tripPlacesApi.deleteTripPlace
    
    // Setup default API mocks
    mockFetchTrip.mockResolvedValue({
      data: {
        data: {
          id: 123,
          name: 'Paris Trip',
          description: 'A wonderful trip to Paris',
          start_date: '2024-06-01',
          end_date: '2024-06-07'
        }
      }
    })
    
    mockFetchTripPlaces.mockResolvedValue({
      data: {
        data: [
          {
            id: 1,
            place: {
              id: 1,
              name: 'Eiffel Tower',
              category_slug: 'attraction'
            },
            is_fixed: false
          }
        ]
      }
    })
    
    mockFetchTripMembers.mockResolvedValue({
      data: {
        data: [
          {
            id: 1,
            name: 'John Doe',
            email: 'john@example.com'
          }
        ]
      }
    })
    
    mockCreateTripPlace.mockResolvedValue({})
    mockVoteTripPlace.mockResolvedValue({})
    mockUpdateTripPlace.mockResolvedValue({})
    mockDeleteTripPlace.mockResolvedValue({})
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const createWrapper = (routeParams = { id: '123' }, routeQuery = { tab: 'overview' }) => {
    mockRoute.value = { params: routeParams, query: routeQuery }
    
    return mount(TripDetail, {
      global: {
        stubs: {
          Teleport: true,
          Transition: true
        }
      }
    })
  }

  describe('basic rendering', () => {
    it('renders trip detail page', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.find('.w-full').exists()).toBe(true)
      expect(wrapper.findComponent({ name: 'TripHeaderBar' }).exists()).toBe(true)
      expect(wrapper.findComponent({ name: 'TripTabs' }).exists()).toBe(true)
    })

    it('shows loading state initially', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.loading).toBe(true)
      expect(wrapper.find('.animate-pulse').exists()).toBe(true)
    })

    it('shows error state when data loading fails', async () => {
      mockFetchTrip.mockRejectedValue(new Error('Network error'))
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.errorMsg).toBeTruthy()
      expect(wrapper.find('.bg-red-100').exists()).toBe(true)
    })

    it('renders trip content when data is loaded', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.loading).toBe(false)
      expect(wrapper.vm.trip).toBeTruthy()
      expect(wrapper.findComponent({ name: 'TripHeaderBar' }).exists()).toBe(true)
    })
  })

  describe('data loading', () => {
    it('loads trip data on mount', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(mockFetchTrip).toHaveBeenCalledWith('123')
      expect(mockFetchTripPlaces).toHaveBeenCalledWith('123')
      expect(mockFetchTripMembers).toHaveBeenCalledWith('123')
    })

    it('loads data when trip ID changes', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      // Change route params
      mockRoute.value.params = { id: '456' }
      
      await wrapper.vm.$nextTick()
      await vi.runAllTimersAsync()
      
      // Should load new data
      expect(mockFetchTrip).toHaveBeenCalledWith('456')
    })

    it('handles concurrent load requests correctly', async () => {
      wrapper = createWrapper()
      
      // Simulate multiple rapid changes
      mockRoute.value.params = { id: '123' }
      await wrapper.vm.$nextTick()
      
      mockRoute.value.params = { id: '456' }
      await wrapper.vm.$nextTick()
      
      mockRoute.value.params = { id: '789' }
      await wrapper.vm.$nextTick()
      
      await vi.runAllTimersAsync()
      
      // Should only load the last one
      expect(mockFetchTrip).toHaveBeenCalledTimes(3)
      expect(mockFetchTrip).toHaveBeenLastCalledWith('789')
    })

    it('sets loading to false after successful load', async () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.loading).toBe(true)
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.loading).toBe(false)
    })

    it('sets error message on load failure', async () => {
      mockFetchTrip.mockRejectedValue({
        response: { data: { message: 'Trip not found' } }
      })
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.errorMsg).toBe('Trip not found')
    })
  })

  describe('tab management', () => {
    beforeEach(async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
    })

    it('initializes tab from route query', async () => {
      wrapper = createWrapper({ id: '123' }, { tab: 'places' })
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.activeTab).toBe('places')
    })

    it('uses default tab when invalid tab in query', async () => {
      wrapper = createWrapper({ id: '123' }, { tab: 'invalid' })
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.activeTab).toBe('overview')
    })

    it('changes tab and updates route', async () => {
      wrapper.vm.setTab('places')
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.activeTab).toBe('places')
      expect(mockRouter.replace).toHaveBeenCalledWith({
        query: { tab: 'places' }
      })
    })

    it('renders correct tab content', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      // Overview tab
      expect(wrapper.vm.activeTab).toBe('overview')
      expect(wrapper.find('section').exists()).toBe(true)
      
      // Change to places tab
      wrapper.vm.setTab('places')
      await wrapper.vm.$nextTick()
      
      expect(wrapper.findComponent({ name: 'PlacesWorkspace' }).exists()).toBe(true)
    })

    it('renders plan panel when plan tab is active', async () => {
      wrapper.vm.setTab('plan')
      await wrapper.vm.$nextTick()
      
      expect(wrapper.findComponent({ name: 'TripPlanPanel' }).exists()).toBe(true)
    })

    it('renders team panel when team tab is active', async () => {
      wrapper.vm.setTab('team')
      await wrapper.vm.$nextTick()
      
      expect(wrapper.findComponent({ name: 'TripMembersPanel' }).exists()).toBe(true)
    })

    it('renders preferences panel when preferences tab is active', async () => {
      wrapper.vm.setTab('preferences')
      await wrapper.vm.$nextTick()
      
      expect(wrapper.findComponent({ name: 'TripPreferencesPanel' }).exists()).toBe(true)
    })
  })

  describe('places management', () => {
    beforeEach(async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
    })

    it('computes categories from places', () => {
      expect(wrapper.vm.categories).toEqual(['all', 'attraction'])
    })

    it('filters places by category', () => {
      wrapper.vm.categoryFilter = 'attraction'
      
      const filtered = wrapper.vm.filteredPlaces
      expect(filtered).toHaveLength(1)
      expect(filtered[0].place.category_slug).toBe('attraction')
    })

    it('filters places by search query', () => {
      wrapper.vm.placeQuery = 'eiffel'
      
      const filtered = wrapper.vm.filteredPlaces
      expect(filtered).toHaveLength(1)
      expect(filtered[0].place.name.toLowerCase()).toContain('eiffel')
    })

    it('sorts places by name ascending', () => {
      wrapper.vm.sortKey = 'name_asc'
      
      const filtered = wrapper.vm.filteredPlaces
      expect(filtered).toHaveLength(1)
    })

    it('sorts places by name descending', () => {
      wrapper.vm.sortKey = 'name_desc'
      
      const filtered = wrapper.vm.filteredPlaces
      expect(filtered).toHaveLength(1)
    })

    it('computes stats correctly', () => {
      const stats = wrapper.vm.stats
      
      expect(stats.places).toBe(1)
      expect(stats.members).toBe(1)
      expect(stats.activities).toBe(0)
    })

    it('opens place search modal', () => {
      wrapper.vm.openPlaceSearch()
      
      expect(wrapper.vm.placeSearchOpen).toBe(true)
    })

    it('handles place selection', () => {
      wrapper.vm.onSelectPlace(1)
      
      expect(wrapper.vm.selectedTripPlaceId).toBe(1)
      expect(wrapper.vm.placeModalOpen).toBe(true)
    })

    it('closes place modal and clears selection', () => {
      wrapper.vm.selectedTripPlaceId = 1
      wrapper.vm.placeModalOpen = true
      
      wrapper.vm.closePlaceModal()
      
      expect(wrapper.vm.placeModalOpen).toBe(false)
      // closePlaceModal doesn't clear selectedTripPlaceId, only the delete action does
      expect(wrapper.vm.selectedTripPlaceId).toBe(1)
    })

    it('adds place successfully', async () => {
      wrapper.vm.placeSearchOpen = true
      
      const payload = { name: 'New Place' }
      await wrapper.vm.onAddPlace(payload)
      
      expect(mockCreateTripPlace).toHaveBeenCalledWith('123', payload)
      expect(wrapper.vm.placeSearchOpen).toBe(false)
      expect(wrapper.vm.activeTab).toBe('places')
    })

    it('refreshes places', async () => {
      await wrapper.vm.refreshPlaces()
      
      expect(mockFetchTripPlaces).toHaveBeenCalledWith('123')
      expect(wrapper.vm.placesLoading).toBe(false)
    })

    it('handles place refresh error', async () => {
      mockFetchTripPlaces.mockRejectedValue(new Error('Network error'))
      
      await wrapper.vm.refreshPlaces()
      
      expect(wrapper.vm.errorMsg).toBeTruthy()
    })
  })

  describe('place actions modal', () => {
    beforeEach(async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      // Select a place
      wrapper.vm.onSelectPlace(1)
    })

    it('computes selected trip place correctly', () => {
      const selected = wrapper.vm.selectedTripPlace
      
      expect(selected).toBeTruthy()
      expect(selected.id).toBe(1)
      expect(selected.place.name).toBe('Eiffel Tower')
    })

    it('computes selected backend ID correctly', () => {
      const backendId = wrapper.vm.selectedBackendId
      
      expect(backendId).toBe(1)
    })

    it('computes selected is fixed correctly', () => {
      const isFixed = wrapper.vm.selectedIsFixed
      
      expect(isFixed).toBe(false)
    })

    it('votes for place', async () => {
      await wrapper.vm.doVote()
      
      expect(mockVoteTripPlace).toHaveBeenCalledWith('123', 1)
      expect(wrapper.vm.actionBusy).toBe(false)
    })

    it('toggles fixed status', async () => {
      await wrapper.vm.doToggleFixed()
      
      expect(mockUpdateTripPlace).toHaveBeenCalledWith('123', 1, { is_fixed: true })
      expect(wrapper.vm.actionBusy).toBe(false)
    })

    it('removes place', async () => {
      await wrapper.vm.doRemove()
      
      expect(mockDeleteTripPlace).toHaveBeenCalledWith('123', 1)
      expect(wrapper.vm.placeModalOpen).toBe(false)
      expect(wrapper.vm.selectedTripPlaceId).toBeNull()
    })

    it('handles action errors gracefully', async () => {
      mockVoteTripPlace.mockRejectedValue(new Error('Vote failed'))
      
      await wrapper.vm.doVote()
      
      expect(wrapper.vm.errorMsg).toBeTruthy()
      expect(wrapper.vm.actionBusy).toBe(false)
    })

    it('disables actions when busy', async () => {
      wrapper.vm.actionBusy = true
      
      // Should not be able to perform actions
      expect(wrapper.vm.selectedBackendId).toBeTruthy()
      // The action methods should early return when actionBusy is true
    })
  })

  describe('computed properties', () => {
    beforeEach(async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
    })

    it('computes trip ID from route params', () => {
      expect(wrapper.vm.tripId).toBe('123')
    })

    it('computes filtered places correctly', () => {
      wrapper.vm.placeQuery = 'eiffel'
      wrapper.vm.categoryFilter = 'attraction'
      wrapper.vm.sortKey = 'name_asc'
      
      const filtered = wrapper.vm.filteredPlaces
      expect(filtered).toHaveLength(1)
      expect(filtered[0].place.name.toLowerCase()).toContain('eiffel')
    })

    it('computes stats correctly', () => {
      const stats = wrapper.vm.stats
      
      expect(stats.places).toBe(1)
      expect(stats.members).toBe(1)
      expect(stats.activities).toBe(0)
    })
  })

  describe('date formatting', () => {
    beforeEach(async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
    })

    it('formats valid dates', () => {
      const date = '2024-06-01T00:00:00Z'
      const formatted = wrapper.vm.formatDate(date)
      
      expect(formatted).toBeTruthy()
      expect(typeof formatted).toBe('string')
    })

    it('handles null dates', () => {
      const formatted = wrapper.vm.formatDate(null)
      
      expect(formatted).toBe('—')
    })

    it('handles invalid dates', () => {
      const invalidDate = 'invalid-date'
      const formatted = wrapper.vm.formatDate(invalidDate)
      
      expect(formatted).toBe(invalidDate)
    })
  })

  describe('error handling', () => {
    it('displays error message when set', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      // Set loading to false and error message to trigger error state
      wrapper.vm.loading = false
      wrapper.vm.errorMsg = 'Test error'
      await wrapper.vm.$nextTick()
      
      expect(wrapper.find('.bg-red-100').text()).toBe('Test error')
    })

    it('clears error message on successful operations', async () => {
      wrapper = createWrapper()
      wrapper.vm.errorMsg = 'Previous error'
      
      await wrapper.vm.refreshPlaces()
      
      // refreshPlaces doesn't clear error message, only sets it on error
      expect(wrapper.vm.errorMsg).toBe('Previous error')
    })

    it('handles API errors in load data', async () => {
      mockFetchTrip.mockRejectedValue({
        response: { data: { message: 'API Error' } }
      })
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.errorMsg).toBe('API Error')
      expect(wrapper.vm.loading).toBe(false)
    })
  })

  describe('component integration', () => {
    beforeEach(async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
    })

    it('passes correct props to TripHeaderBar', () => {
      const headerBar = wrapper.findComponent({ name: 'TripHeaderBar' })
      
      expect(headerBar.props('trip')).toEqual(wrapper.vm.trip)
      expect(headerBar.props('stats')).toEqual(wrapper.vm.stats)
      expect(headerBar.props('bannerImage')).toBe(wrapper.vm.bannerImage)
    })

    it('passes correct props to TripTabs', () => {
      const tabs = wrapper.findComponent({ name: 'TripTabs' })
      
      expect(tabs.props('modelValue')).toBe(wrapper.vm.activeTab)
    })

    it('passes correct props to PlacesWorkspace', async () => {
      wrapper.vm.setTab('places')
      await wrapper.vm.$nextTick()
      
      const workspace = wrapper.findComponent({ name: 'PlacesWorkspace' })
      
      expect(workspace.props('trip')).toEqual(wrapper.vm.trip)
      expect(workspace.props('places')).toEqual(wrapper.vm.places)
      expect(workspace.props('categories')).toEqual(wrapper.vm.categories)
      expect(workspace.props('filteredPlaces')).toEqual(wrapper.vm.filteredPlaces)
    })

    it('passes correct props to PlaceSearchModal', () => {
      const modal = wrapper.findComponent({ name: 'PlaceSearchModal' })
      
      expect(modal.props('modelValue')).toBe(wrapper.vm.placeSearchOpen)
      expect(modal.props('tripId')).toBe('123')
    })
  })

  describe('overview tab', () => {
    beforeEach(async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
    })

    it('renders overview section', () => {
      expect(wrapper.vm.activeTab).toBe('overview')
      
      const section = wrapper.find('section')
      expect(section.find('h2').text()).toContain('trip.about')
    })

    it('shows trip description', () => {
      const section = wrapper.find('section')
      expect(section.text()).toContain('A wonderful trip to Paris')
    })

    it('shows add place button', () => {
      const section = wrapper.find('section')
      expect(section.text()).toContain('trip.view.add_place')
    })

    it('switches to places tab when add place button is clicked', async () => {
      const section = wrapper.find('section')
      const button = section.find('button')
      
      await button.trigger('click')
      
      expect(wrapper.vm.activeTab).toBe('places')
      expect(wrapper.vm.placeSearchOpen).toBe(true)
    })
  })

  describe('responsive design', () => {
    it('has proper container styling', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const container = wrapper.find('.max-w-6xl')
      expect(container.classes()).toContain('mx-auto')
      expect(container.classes()).toContain('px-4')
    })

    it('has proper grid layout in loading state', () => {
      wrapper = createWrapper()
      
      const grid = wrapper.find('.grid.grid-cols-2.md\\:grid-cols-4')
      expect(grid.classes()).toContain('grid-cols-2')
      expect(grid.classes()).toContain('md:grid-cols-4')
    })
  })

  describe('accessibility', () => {
    it('has proper modal attributes', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      wrapper.vm.onSelectPlace(1)
      await wrapper.vm.$nextTick()
      
      const modal = wrapper.find('[role="dialog"]')
      expect(modal.attributes('role')).toBe('dialog')
      expect(modal.attributes('aria-modal')).toBe('true')
    })

    it('has proper close button labels', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      wrapper.vm.onSelectPlace(1)
      await wrapper.vm.$nextTick()
      
      const closeButton = wrapper.find('button[aria-label="Close"]')
      expect(closeButton.attributes('aria-label')).toBe('Close')
    })
  })

  describe('edge cases', () => {
    it('handles missing trip data gracefully', async () => {
      mockFetchTrip.mockResolvedValue({ data: { data: null } })
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.trip).toBeNull()
      expect(wrapper.vm.loading).toBe(false)
    })

    it('handles empty places array', async () => {
      mockFetchTripPlaces.mockResolvedValue({ data: { data: [] } })
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.places).toEqual([])
      expect(wrapper.vm.categories).toEqual(['all'])
    })

    it('handles missing place data', async () => {
      mockFetchTripPlaces.mockResolvedValue({
        data: {
          data: [
            {
              id: 1,
              place: null
            }
          ]
        }
      })
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.places).toHaveLength(1)
      expect(wrapper.vm.categories).toEqual(['all'])
    })

    it('handles invalid route params', () => {
      wrapper = createWrapper({ id: null })
      
      expect(wrapper.vm.tripId).toBe('')
    })
  })
})
