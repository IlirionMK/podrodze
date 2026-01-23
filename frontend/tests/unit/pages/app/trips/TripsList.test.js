import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import TripsList from '@/pages/app/trips/TripsList.vue'

// Setup fake timers
vi.useFakeTimers()

// Mock vue-router
vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: vi.fn()
  })
}))

// Mock lucide-vue-next icons
vi.mock('lucide-vue-next', () => ({
  RefreshCw: { template: '<div>RefreshCw</div>' },
  Plus: { template: '<div>Plus</div>' }
}))

// Mock API
vi.mock('@/composables/api/trips', () => ({
  fetchUserTrips: vi.fn()
}))

describe('TripsList Page', () => {
  let wrapper
  let mockRouter
  let mockFetchUserTrips

  beforeEach(async () => {
    vi.clearAllMocks()
    
    const router = await import('vue-router')
    const tripsApi = await import('@/composables/api/trips')
    
    mockRouter = router.useRouter()
    mockFetchUserTrips = tripsApi.fetchUserTrips
    
    // Setup default API mock
    mockFetchUserTrips.mockResolvedValue({
      data: [
        {
          id: 1,
          name: 'Paris Trip',
          start_date: '2024-06-01',
          end_date: '2024-06-07',
          country: 'France',
          location: 'Paris',
          activities_count: 5,
          cover_url: 'https://example.com/paris.jpg'
        },
        {
          id: 2,
          name: 'Rome Adventure',
          start_date: '2024-07-15',
          end_date: '2024-07-22',
          country: 'Italy',
          location: 'Rome',
          activities_count: 8,
          image_url: 'https://example.com/rome.jpg'
        }
      ]
    })
  })

  const createWrapper = () => {
    return mount(TripsList, {
      global: {
        stubs: {
          'router-link': {
            template: '<a><slot /></a>',
            props: ['to']
          }
        }
      }
    })
  }

  describe('basic rendering', () => {
    it('renders trips list page', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('.max-w-6xl').exists()).toBe(true)
      expect(wrapper.find('h1').exists()).toBe(true)
    })

    it('renders page header', () => {
      wrapper = createWrapper()
      
      const title = wrapper.find('h1')
      expect(title.text()).toContain('Moje Podróże')
      expect(title.classes()).toContain('text-lg')
      expect(title.classes()).toContain('font-semibold')
    })

    it('renders page subtitle', () => {
      wrapper = createWrapper()
      
      const subtitle = wrapper.find('p')
      expect(subtitle.text()).toContain('Wybierz podróż, aby zobaczyć szczegóły i planować aktywności')
      expect(subtitle.classes()).toContain('text-sm')
      expect(subtitle.classes()).toContain('text-gray-600')
    })

    it('renders action buttons', () => {
      wrapper = createWrapper()
      
      expect(wrapper.text()).toContain('RefreshCw')
      expect(wrapper.text()).toContain('Dodaj podróż')
    })
  })

  describe('data loading', () => {
    it('loads trips on mount', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(mockFetchUserTrips).toHaveBeenCalled()
      expect(wrapper.vm.loading).toBe(false)
    })

    it('shows loading state initially', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.loading).toBe(true)
      expect(wrapper.find('.animate-pulse').exists()).toBe(true)
    })

    it('shows loading skeleton', () => {
      wrapper = createWrapper()
      
      const skeleton = wrapper.find('.animate-pulse')
      expect(skeleton.find('.h-4.w-56').exists()).toBe(true)
      expect(skeleton.find('.grid').exists()).toBe(true)
    })

    it('displays loaded trips', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.trips).toHaveLength(2)
      expect(wrapper.vm.trips[0].name).toBe('Paris Trip')
      expect(wrapper.vm.trips[1].name).toBe('Rome Adventure')
    })

    it('handles load error gracefully', async () => {
      mockFetchUserTrips.mockRejectedValue(new Error('Network error'))
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.errorMsg).toBeTruthy()
      expect(wrapper.find('.bg-red-100').exists()).toBe(true)
      expect(wrapper.vm.loading).toBe(false)
    })

    it('shows error message from API response', async () => {
      mockFetchUserTrips.mockRejectedValue({
        response: { data: { message: 'API Error' } }
      })
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.errorMsg).toBe('API Error')
    })
  })

  describe('empty state', () => {
    it('shows empty state when no trips', async () => {
      mockFetchUserTrips.mockResolvedValue({ data: [] })
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.trips).toHaveLength(0)
      expect(wrapper.text()).toContain('Brak podróży')
      expect(wrapper.text()).toContain('Nie masz jeszcze żadnych podróży')
    })

    it('shows create trip button in empty state', async () => {
      mockFetchUserTrips.mockResolvedValue({ data: [] })
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      const createLinks = wrapper.findAll('a').filter(link => 
        link.text().includes('Dodaj podróż')
      )
      expect(createLinks.length).toBe(2) // One in header, one in empty state
    })
  })

  describe('date formatting', () => {
    beforeEach(async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
    })

    it('formats short date correctly', () => {
      const formatted = wrapper.vm.formatShortDate('2024-06-01T00:00:00Z')
      expect(formatted).toBe('01 cze')
    })

    it('handles null date in short format', () => {
      const formatted = wrapper.vm.formatShortDate(null)
      expect(formatted).toBe('—')
    })

    it('formats date range correctly', () => {
      const formatted = wrapper.vm.formatDateRange('2024-06-01', '2024-06-07')
      expect(formatted).toBe('01 cze – 07 cze')
    })

    it('formats date range across years', () => {
      const formatted = wrapper.vm.formatDateRange('2023-12-31', '2024-01-01')
      expect(formatted).toBe('31 gru 2023 – 01 sty 2024')
    })

    it('handles null dates in range', () => {
      expect(wrapper.vm.formatDateRange(null, null)).toBe('—')
      expect(wrapper.vm.formatDateRange('2024-06-01', null)).toBe('01 cze')
      expect(wrapper.vm.formatDateRange(null, '2024-06-07')).toBe('07 cze')
    })

    it('calculates trip days correctly', () => {
      expect(wrapper.vm.tripDays('2024-06-01', '2024-06-07')).toBe(7)
      expect(wrapper.vm.tripDays('2024-06-01', '2024-06-01')).toBe(1)
      expect(wrapper.vm.tripDays(null, '2024-06-07')).toBeNull()
    })
  })

  describe('date formatting', () => {
    it('has proper button styling', () => {
      wrapper = createWrapper()
      
      const refreshButton = wrapper.find('button[type="button"]')
      
      expect(refreshButton.classes()).toContain('inline-flex')
      expect(refreshButton.classes()).toContain('items-center')
      expect(refreshButton.classes()).toContain('justify-center')
      expect(refreshButton.classes()).toContain('rounded-full')
      expect(refreshButton.classes()).toContain('w-11')
      expect(refreshButton.classes()).toContain('h-11')
      expect(refreshButton.classes()).toContain('bg-gradient-to-r')
      expect(refreshButton.classes()).toContain('from-blue-600')
      expect(refreshButton.classes()).toContain('to-purple-600')
    })

    it('has proper ARIA attributes', () => {
      wrapper = createWrapper()
      
      const refreshButton = wrapper.find('button[type="button"]')
      expect(refreshButton.attributes('aria-label')).toBe('Odśwież')
      expect(refreshButton.attributes('title')).toBe('Odśwież')
    })
  })

})
