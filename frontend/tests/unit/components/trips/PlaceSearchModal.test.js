import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PlaceSearchModal from '@/components/trips/PlaceSearchModal.vue'

// Mock vue-i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key, fallback) => fallback || key,
    locale: { value: 'en' }
  })
}))

// Mock lucide-vue-next icons
vi.mock('lucide-vue-next', () => ({
  Sparkles: { template: '<div>Sparkles</div>' },
  Search: { template: '<div>Search</div>' },
  MapPin: { template: '<div>MapPin</div>' },
  Plus: { template: '<div>Plus</div>' },
  X: { template: '<div>X</div>' }
}))

// Mock API functions
vi.mock('@/composables/api/tripPlaces.js', () => ({
  searchExternalPlaces: vi.fn(),
  getAiSuggestions: vi.fn()
}))

describe('PlaceSearchModal Component', () => {
  let wrapper
  let mockSearchExternalPlaces
  let mockGetAiSuggestions

  beforeEach(async () => {
    vi.useFakeTimers()
    vi.clearAllMocks()
    
    const api = await import('@/composables/api/tripPlaces.js')
    mockSearchExternalPlaces = api.searchExternalPlaces
    mockGetAiSuggestions = api.getAiSuggestions
    
    mockSearchExternalPlaces.mockResolvedValue({
      data: {
        data: [
          {
            google_place_id: 'test123',
            main_text: 'Test Place',
            secondary_text: 'Test Address'
          }
        ]
      }
    })
    
    mockGetAiSuggestions.mockResolvedValue({
      data: {
        data: [
          {
            internal_place_id: 1,
            name: 'Suggested Place',
            address: 'Suggested Address',
            category: 'restaurant',
            reason: 'Great food'
          }
        ]
      }
    })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const createWrapper = (props = {}) => {
    return mount(PlaceSearchModal, {
      props: {
        modelValue: true,
        tripId: '123',
        ...props
      },
      global: {
        stubs: {
          Teleport: true,
          Transition: true
        }
      }
    })
  }

  describe('basic rendering', () => {
    it('renders modal when modelValue is true', () => {
      wrapper = createWrapper({ modelValue: true })
      
      expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
      expect(wrapper.find('.fixed.inset-0').exists()).toBe(true)
    })

    it('does not render modal when modelValue is false', () => {
      wrapper = createWrapper({ modelValue: false })
      
      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    })

    it('renders close button', () => {
      wrapper = createWrapper()
      
      const closeButton = wrapper.find('button[aria-label="Close"]')
      expect(closeButton.exists()).toBe(true)
    })

    it('renders backdrop', () => {
      wrapper = createWrapper()
      
      const backdrop = wrapper.find('.absolute.inset-0.bg-black\\/60')
      expect(backdrop.exists()).toBe(true)
    })

    it('renders tab buttons', () => {
      wrapper = createWrapper()
      
      const tabButtons = wrapper.findAll('button[type="button"]')
      expect(tabButtons.length).toBeGreaterThan(0)
      
      // Should have AI Recommendations and Search tabs
      expect(wrapper.text()).toContain('AI Recommendations')
      expect(wrapper.text()).toContain('Search')
    })
  })

  describe('tabs functionality', () => {
    it('starts with suggested tab', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.tab).toBe('suggested')
    })

    it('switches to search tab when clicked', async () => {
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()  // Wait for watch to trigger
      await vi.runAllTimersAsync()  // Wait for fetchSuggestions to complete
      
      // Find search tab by text
      const buttons = wrapper.findAll('button[type="button"]')
      const searchTab = buttons.find(btn => btn.text().includes('Search'))
      await searchTab.trigger('click')
      
      expect(wrapper.vm.tab).toBe('search')
    })

    it('switches back to suggested tab when clicked', async () => {
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()  // Wait for watch to trigger
      await vi.runAllTimersAsync()  // Wait for fetchSuggestions to complete
      
      // Switch to search first
      const buttons = wrapper.findAll('button[type="button"]')
      const searchTab = buttons.find(btn => btn.text().includes('Search'))
      await searchTab.trigger('click')
      
      // Switch back to suggested
      const suggestedTab = buttons.find(btn => btn.text().includes('AI'))
      await suggestedTab.trigger('click')
      
      expect(wrapper.vm.tab).toBe('suggested')
    })

    it('shows search input only on search tab', async () => {
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()  // Wait for watch to trigger
      await vi.runAllTimersAsync()  // Wait for fetchSuggestions to complete
      
      // Initially on suggested tab - no search input
      expect(wrapper.find('input[type="text"]').exists()).toBe(false)
      
      // Switch to search tab (button with "Search" text)
      const buttons = wrapper.findAll('button[type="button"]')
      const searchTab = buttons.find(btn => btn.text().includes('Search'))
      await searchTab.trigger('click')
      
      expect(wrapper.find('input[type="text"]').exists()).toBe(true)
    })

    it('applies active styling to current tab', async () => {
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()  // Wait for watch to trigger
      await vi.runAllTimersAsync()  // Wait for fetchSuggestions to complete
      
      const buttons = wrapper.findAll('button[type="button"]')
      const suggestedTab = buttons.find(btn => btn.text().includes('AI'))
      const searchTab = buttons.find(btn => btn.text().includes('Search'))
      
      // Initially suggested tab should be active
      expect(suggestedTab.classes()).toContain('bg-white/10')
      expect(searchTab.classes()).not.toContain('bg-white/10')
      
      // Switch to search
      await searchTab.trigger('click')
      
      expect(suggestedTab.classes()).not.toContain('bg-white/10')
      expect(searchTab.classes()).toContain('bg-white/10')
    })
  })

  describe('search functionality', () => {
    beforeEach(async () => {
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()  // Wait for watch to trigger
      await vi.runAllTimersAsync()  // Wait for fetchSuggestions to complete
      
      // Switch to search tab (button with "Search" text)
      const buttons = wrapper.findAll('button[type="button"]')
      const searchTab = buttons.find(btn => btn.text().includes('Search'))
      await searchTab.trigger('click')
    })

    it('updates search query when typing', async () => {
      const searchInput = wrapper.find('input[type="text"]')
      await searchInput.setValue('Test Query')
      
      expect(wrapper.vm.q).toBe('Test Query')
    })

    it('triggers search after debounce delay', async () => {
      vi.useFakeTimers()
      
      const searchInput = wrapper.find('input[type="text"]')
      await searchInput.setValue('Test Query')
      
      // Should not trigger immediately
      expect(mockSearchExternalPlaces).not.toHaveBeenCalled()
      
      // Advance timer by 500ms
      vi.advanceTimersByTime(500)
      
      await vi.runAllTimersAsync()
      
      expect(mockSearchExternalPlaces).toHaveBeenCalledWith('Test Query')
      
      vi.useRealTimers()
    })

    it('does not search for queries shorter than 2 characters', async () => {
      vi.useFakeTimers()
      
      const searchInput = wrapper.find('input[type="text"]')
      await searchInput.setValue('a')
      
      vi.advanceTimersByTime(500)
      await vi.runAllTimersAsync()
      
      expect(mockSearchExternalPlaces).not.toHaveBeenCalled()
      
      vi.useRealTimers()
    })

    it('clears search results when query is empty', async () => {
      const searchInput = wrapper.find('input[type="text"]')
      await searchInput.setValue('')
      
      expect(wrapper.vm.items).toEqual([])
    })

    it('triggers search on enter key', async () => {
      const searchInput = wrapper.find('input[type="text"]')
      await searchInput.setValue('Test Query')
      await searchInput.trigger('keydown.enter')
      
      expect(mockSearchExternalPlaces).toHaveBeenCalledWith('Test Query')
    })
  })

  describe('AI suggestions', () => {
    beforeEach(async () => {
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()  // Wait for watch to trigger
      await vi.runAllTimersAsync()  // Wait for fetchSuggestions to complete
    })

    it('fetches suggestions when modal opens', () => {
      expect(mockGetAiSuggestions).toHaveBeenCalledWith('123', {
        limit: 5,
        locale: 'en'
      })
    })

    it('processes suggestion data correctly', () => {
      expect(wrapper.vm.items).toHaveLength(1)
      expect(wrapper.vm.items[0]).toMatchObject({
        unique_key: 'db_1',
        place_id: 1,
        google_place_id: null,
        name: 'Suggested Place',
        address: 'Suggested Address',
        category_slug: 'restaurant',
        ai_reason: 'Great food',
        image: undefined,
        rating: undefined,
        distance_m: undefined,
        reviews_count: undefined,
        source: 'suggestion'
      })
    })

    it('handles missing external_id correctly', async () => {
      mockGetAiSuggestions.mockResolvedValue({
        data: {
          data: [
            {
              internal_place_id: 1,
              name: 'Test Place',
              category: 'restaurant'
            }
          ]
        }
      })
      
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch with new mock data
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()  // Wait for watch to trigger
      await vi.runAllTimersAsync()  // Wait for fetchSuggestions to complete
      
      const items = wrapper.vm.items
      expect(items[0].google_place_id).toBeNull()
    })
  })

  describe('item selection', () => {
    beforeEach(async () => {
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()  // Wait for watch to trigger
      await vi.runAllTimersAsync()  // Wait for fetchSuggestions to complete
    })

    it('selects item when clicked', async () => {
      const items = wrapper.findAll('button[type="button"]').filter(btn => 
        btn.text().includes('Suggested Place')
      )
      
      await items[0].trigger('click')
      
      expect(wrapper.vm.selected).toBeTruthy()
      expect(wrapper.vm.selected.name).toBe('Suggested Place')
    })

    it('applies selected styling to selected item', async () => {
      const items = wrapper.findAll('button[type="button"]').filter(btn => 
        btn.text().includes('Suggested Place')
      )
      
      await items[0].trigger('click')
      await wrapper.vm.$nextTick()
      
      expect(items[0].classes()).toContain('border-white/20')
      expect(items[0].classes()).toContain('bg-white/10')
      expect(items[0].classes()).toContain('ring-2')
    })

    it('enables add button when item is selected', async () => {
      const items = wrapper.findAll('button[type="button"]').filter(btn => 
        btn.text().includes('Suggested Place')
      )
      
      expect(wrapper.vm.canAdd).toBe(false)
      
      await items[0].trigger('click')
      
      expect(wrapper.vm.canAdd).toBe(true)
    })
  })

  describe('add functionality', () => {
    beforeEach(async () => {
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()  // Wait for watch to trigger
      await vi.runAllTimersAsync()  // Wait for fetchSuggestions to complete
      
      // Select an item
      const items = wrapper.findAll('button[type="button"]').filter(btn => 
        btn.text().includes('Suggested Place')
      )
      await items[0].trigger('click')
    })

    it('emits picked event with correct payload for internal place', async () => {
      const buttons = wrapper.findAll('button[type="button"]')
      const addButton = buttons.find(btn => btn.text().includes('Plus Add'))
      
      await addButton.trigger('click')
      
      expect(wrapper.emitted('picked')).toBeTruthy()
      expect(wrapper.emitted('picked')[0][0]).toMatchObject({
        _source: 'suggestion',
        place_id: 1
      })
    })

    it('emits picked event with correct payload for Google place', async () => {
      // Switch to search tab (button with "Search" text)
      const buttons = wrapper.findAll('button[type="button"]')
      const searchTab = buttons.find(btn => btn.text().includes('Search'))
      await searchTab.trigger('click')
      
      const searchInput = wrapper.find('input[type="text"]')
      await searchInput.setValue('Test Query')
      await searchInput.trigger('keydown.enter')
      await vi.runAllTimersAsync()
      
      // Select the search result
      const searchItems = wrapper.findAll('button[type="button"]').filter(btn => 
        btn.text().includes('Test Place')
      )
      await searchItems[0].trigger('click')
      
      const addButton = buttons.find(btn => btn.text().includes('Plus Add'))
      
      await addButton.trigger('click')
      
      expect(wrapper.emitted('picked')).toBeTruthy()
      expect(wrapper.emitted('picked')[0][0]).toMatchObject({
        _source: 'search',
        google_place_id: 'test123'
      })
    })

    it('closes modal after successful add', async () => {
      const buttons = wrapper.findAll('button[type="button"]')
      const addButton = buttons.find(btn => btn.text().includes('Plus Add'))
      
      await addButton.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
    })
  })

  describe('loading states', () => {
    it('shows loading spinner when loading', async () => {
      mockGetAiSuggestions.mockImplementation(() => new Promise(resolve => setTimeout(resolve, 100)))
      
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()  // Wait for watch to trigger
      
      // Check loading state immediately after opening
      expect(wrapper.find('.animate-spin').exists()).toBe(true)
      expect(wrapper.text()).toContain('AI is picking the best places')
    })

    it('disables buttons when loading', async () => {
      mockGetAiSuggestions.mockImplementation(() => 
        new Promise(resolve => setTimeout(() => {
          resolve({
            data: {
              data: [
                {
                  internal_place_id: 1,
                  name: 'Suggested Place',
                  address: 'Suggested Address',
                  category: 'restaurant',
                  reason: 'Great food'
                }
              ]
            }
          })
        }, 100))
      )
      
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()  // Wait for watch to trigger
      
      const closeButtons = wrapper.findAll('button[aria-label="Close"]')
      const closeButton = closeButtons[1] // Take the second one (the actual close button)
      
      // Check if button is disabled (either has disabled attribute or disabled class)
      const isDisabled = closeButton.attributes('disabled') !== undefined || 
                        closeButton.classes().includes('disabled') ||
                        closeButton.classes().includes('opacity-50')
      
      expect(isDisabled).toBe(true)
    })
  })

  describe('error handling', () => {
    it('shows error message when suggestions fail', async () => {
      mockGetAiSuggestions.mockRejectedValue(new Error('API Error'))
      
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.error).toBe('Failed to load suggestions')
      expect(wrapper.text()).toContain('Failed to load suggestions')
    })

    it('shows error message when search fails', async () => {
      mockSearchExternalPlaces.mockRejectedValue(new Error('Search Error'))
      
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()
      await vi.runAllTimersAsync()
      
      // Switch to search tab (button with "Search" text)
      const buttons = wrapper.findAll('button[type="button"]')
      const searchTab = buttons.find(btn => btn.text().includes('Search'))
      await searchTab.trigger('click')
      
      const searchInput = wrapper.find('input[type="text"]')
      await searchInput.setValue('Test Query')
      await searchInput.trigger('keydown.enter')
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.error).toBeTruthy()
      expect(wrapper.text()).toContain('Search error')
    })

    it('handles 404 errors gracefully', async () => {
      const error = new Error('Not found')
      error.response = { status: 404 }
      mockSearchExternalPlaces.mockRejectedValue(error)
      
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()
      await vi.runAllTimersAsync()
      
      // Switch to search tab (button with "Search" text)
      const buttons = wrapper.findAll('button[type="button"]')
      const searchTab = buttons.find(btn => btn.text().includes('Search'))
      await searchTab.trigger('click')
      
      const searchInput = wrapper.find('input[type="text"]')
      await searchInput.setValue('Test Query')
      await searchInput.trigger('keydown.enter')
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.items).toEqual([])
      expect(wrapper.vm.error).toBe('')
    })
  })

  describe('empty states', () => {
    it('shows no suggestions message when no AI suggestions', async () => {
      mockGetAiSuggestions.mockResolvedValue({ data: { data: [] } })
      
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      expect(wrapper.text()).toContain('No suggestions found for this trip')
    })

    it('shows no results message when search returns empty', async () => {
      mockSearchExternalPlaces.mockResolvedValue({ data: { data: [] } })
      
      // Create modal with modelValue: false first
      wrapper = createWrapper({ modelValue: false })
      
      // Then open it to trigger the watch
      wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()
      await vi.runAllTimersAsync()
      
      // Switch to search tab (button with "Search" text)
      const buttons = wrapper.findAll('button[type="button"]')
      const searchTab = buttons.find(btn => btn.text().includes('Search'))
      await searchTab.trigger('click')
      
      const searchInput = wrapper.find('input[type="text"]')
      await searchInput.setValue('Test Query')
      await searchInput.trigger('keydown.enter')
      await vi.runAllTimersAsync()
      
      expect(wrapper.text()).toContain('No results found')
    })
  })

  describe('modal interactions', () => {
    it('closes modal when close button is clicked', async () => {
      wrapper = createWrapper()
      
      const closeButton = wrapper.find('button[aria-label="Close"]')
      await closeButton.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
    })

    it('closes modal when backdrop is clicked', async () => {
      wrapper = createWrapper()
      
      const backdrop = wrapper.find('.absolute.inset-0.bg-black\\/60')
      await backdrop.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
    })

    it('closes modal when cancel button is clicked', async () => {
      wrapper = createWrapper()
      
      const cancelButton = wrapper.findAll('button[type="button"]').filter(btn => 
        btn.text().includes('Cancel')
      )[0]
      
      await cancelButton.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
    })

    it('resets state when modal opens', async () => {
      wrapper = createWrapper()
      
      // Change some state
      wrapper.vm.tab = 'search'
      wrapper.vm.q = 'test'
      wrapper.vm.selected = { name: 'test' }
      
      // Close and reopen
      await wrapper.setProps({ modelValue: false })
      await wrapper.setProps({ modelValue: true })
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.tab).toBe('suggested')
      expect(wrapper.vm.q).toBe('')
      expect(wrapper.vm.selected).toBeNull()
    })
  })

  describe('styling', () => {
    it('has proper modal styling classes', () => {
      wrapper = createWrapper()
      
      const modal = wrapper.find('.relative.w-full')
      expect(modal.classes()).toContain('max-w-2xl')
      expect(modal.classes()).toContain('rounded-2xl')
      expect(modal.classes()).toContain('border')
      expect(modal.classes()).toContain('border-white/15')
      expect(modal.classes()).toContain('bg-white/10')
      expect(modal.classes()).toContain('backdrop-blur-xl')
      expect(modal.classes()).toContain('shadow-2xl')
      expect(modal.classes()).toContain('text-white')
    })

    it('has proper tab styling', () => {
      wrapper = createWrapper()
      
      const tabContainer = wrapper.find('.inline-flex.gap-1.p-1')
      expect(tabContainer.classes()).toContain('rounded-xl')
      expect(tabContainer.classes()).toContain('border')
      expect(tabContainer.classes()).toContain('border-white/10')
      expect(tabContainer.classes()).toContain('bg-black/20')
    })
  })
})
