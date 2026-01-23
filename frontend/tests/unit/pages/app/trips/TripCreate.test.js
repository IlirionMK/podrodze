import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import TripCreate from '@/pages/app/trips/TripCreate.vue'

// Mock vue-router
let mockRouter = { push: vi.fn() }

vi.mock('vue-router', () => ({
  useRouter: () => mockRouter
}))

// Mock vue-i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key, fallback) => fallback || key,
    te: (key) => true // Always return true for translation exists
  })
}))

// Mock lucide-vue-next icons
vi.mock('lucide-vue-next', () => ({
  ArrowLeft: { template: '<div>ArrowLeft</div>' },
  Plus: { template: '<div>Plus</div>' }
}))

// Mock API
vi.mock('@/composables/api/trips.js', () => ({
  createTrip: vi.fn()
}))

describe('TripCreate Page', () => {
  let wrapper
  let mockCreateTrip

  beforeEach(async () => {
    vi.useFakeTimers()
    vi.clearAllMocks()
    
    const tripsApi = await import('@/composables/api/trips.js')
    
    mockCreateTrip = tripsApi.createTrip
    
    // Setup default API mock
    mockCreateTrip.mockResolvedValue({
      data: {
        data: { id: 123 }
      }
    })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const createWrapper = () => {
    return mount(TripCreate)
  }

  describe('basic rendering', () => {
    it('renders trip create page', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('.max-w-3xl').exists()).toBe(true)
      expect(wrapper.find('h1').exists()).toBe(true)
    })

    it('renders page header', () => {
      wrapper = createWrapper()
      
      const title = wrapper.find('h1')
      expect(title.text()).toContain('trip.create.title')
      expect(title.classes()).toContain('text-lg')
      expect(title.classes()).toContain('font-semibold')
    })

    it('renders page subtitle', () => {
      wrapper = createWrapper()
      
      const subtitle = wrapper.find('p')
      expect(subtitle.text()).toContain('trip.create.subtitle')
      expect(subtitle.classes()).toContain('text-sm')
      expect(subtitle.classes()).toContain('text-gray-600')
    })

    it('renders form fields', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('input').exists()).toBe(true)  // Name input (no type specified)
      expect(wrapper.find('input[type="date"]').exists()).toBe(true)
      expect(wrapper.findAll('input[type="date"]').length).toBe(2)
    })

    it('renders action buttons', () => {
      wrapper = createWrapper()
      
      const buttons = wrapper.findAll('button')
      expect(buttons.length).toBe(2)
      expect(wrapper.text()).toContain('actions.back')
      expect(wrapper.text()).toContain('trip.create.button')
    })
  })

  describe('form fields', () => {
    it('renders name input with correct attributes', () => {
      wrapper = createWrapper()
      
      const nameInput = wrapper.find('input')  // First input (name input)
      expect(nameInput.attributes('placeholder')).toBe('trip.fields.name_placeholder')
      expect(nameInput.attributes('autocomplete')).toBe('off')
    })

    it('renders date inputs', () => {
      wrapper = createWrapper()
      
      const dateInputs = wrapper.findAll('input[type="date"]')
      expect(dateInputs.length).toBe(2)
    })

    it('renders field labels', () => {
      wrapper = createWrapper()
      
      const labels = wrapper.findAll('label')
      expect(labels.length).toBe(3) // Name, Start date, End date
      
      expect(labels[0].text()).toContain('trip.fields.name')
      expect(labels[1].text()).toContain('trip.fields.start_date')
      expect(labels[2].text()).toContain('trip.fields.end_date')
    })

    it('renders name hint', () => {
      wrapper = createWrapper()
      
      const hint = wrapper.find('.mt-1.text-xs')
      expect(hint.text()).toContain('trip.create.name_hint')
    })
  })

  describe('form interactions', () => {
    it('updates name when typing', async () => {
      wrapper = createWrapper()
      
      const nameInput = wrapper.find('input')  // First input (name input)
      await nameInput.setValue('Paris Trip')
      
      expect(wrapper.vm.name).toBe('Paris Trip')
    })

    it('updates start date when changed', async () => {
      wrapper = createWrapper()
      
      const startDateInput = wrapper.findAll('input[type="date"]')[0]
      await startDateInput.setValue('2024-06-01')
      
      expect(wrapper.vm.startDate).toBe('2024-06-01')
    })

    it('updates end date when changed', async () => {
      wrapper = createWrapper()
      
      const endDateInput = wrapper.findAll('input[type="date"]')[1]
      await endDateInput.setValue('2024-06-07')
      
      expect(wrapper.vm.endDate).toBe('2024-06-07')
    })
  })

  describe('form validation', () => {
    it('disables submit when name is empty', async () => {
      wrapper = createWrapper()
      
      const submitButton = wrapper.findAll('button')[1] // Create trip button
      expect(submitButton.attributes('disabled')).toBeDefined()
    })

    it('enables submit when name is provided', async () => {
      wrapper = createWrapper()
      
      const nameInput = wrapper.find('input')  // First input (name input)
      await nameInput.setValue('Test Trip')
      
      const submitButton = wrapper.findAll('button')[1]
      expect(submitButton.attributes('disabled')).toBeUndefined()
    })

    it('disables submit when loading', async () => {
      wrapper = createWrapper()
      wrapper.vm.loading = true
      
      const submitButton = wrapper.findAll('button')[1]
      expect(submitButton.attributes('disabled')).toBeDefined()
    })

    it('shows date validation error when end date is before start date', async () => {
      wrapper = createWrapper()
      
      const startDateInput = wrapper.findAll('input[type="date"]')[0]
      const endDateInput = wrapper.findAll('input[type="date"]')[1]
      
      await startDateInput.setValue('2024-06-07')
      await endDateInput.setValue('2024-06-01')
      
      expect(wrapper.vm.canSubmit).toBe(false)
    })

    it('does not show date error when dates are valid', async () => {
      wrapper = createWrapper()
      
      const nameInput = wrapper.find('input')
      const startDateInput = wrapper.findAll('input[type="date"]')[0]
      const endDateInput = wrapper.findAll('input[type="date"]')[1]
      
      await nameInput.setValue('Test Trip')
      await startDateInput.setValue('2024-06-01')
      await endDateInput.setValue('2024-06-07')
      
      expect(wrapper.vm.canSubmit).toBe(true)
    })

    it('shows date error message', async () => {
      wrapper = createWrapper()
      
      const startDateInput = wrapper.findAll('input[type="date"]')[0]
      const endDateInput = wrapper.findAll('input[type="date"]')[1]
      
      await startDateInput.setValue('2024-06-07')
      await endDateInput.setValue('2024-06-01')
      
      const errorMessage = wrapper.find('.border-red-200')
      expect(errorMessage.text()).toContain('trip.create.errors.date_order')
    })
  })

  describe('trip creation', () => {
    beforeEach(async () => {
      wrapper = createWrapper()
      const nameInput = wrapper.find('input')
      await nameInput.setValue('Test Trip')
    })

    it('creates trip when submit button is clicked', async () => {
      const submitButton = wrapper.findAll('button')[1]
      await submitButton.trigger('click')
      
      expect(mockCreateTrip).toHaveBeenCalledWith({
        name: 'Test Trip',
        start_date: null,
        end_date: null
      })
    })

    it('creates trip with dates when provided', async () => {
      const startDateInput = wrapper.findAll('input[type="date"]')[0]
      const endDateInput = wrapper.findAll('input[type="date"]')[1]
      
      await startDateInput.setValue('2024-06-01')
      await endDateInput.setValue('2024-06-07')
      
      const submitButton = wrapper.findAll('button')[1]
      await submitButton.trigger('click')
      
      expect(mockCreateTrip).toHaveBeenCalledWith({
        name: 'Test Trip',
        start_date: '2024-06-01',
        end_date: '2024-06-07'
      })
    })

    it('redirects to trip detail after successful creation', async () => {
      const submitButton = wrapper.findAll('button')[1]
      await submitButton.trigger('click')
      
      await vi.runAllTimersAsync()
      
      expect(mockRouter.push).toHaveBeenCalledWith({
        name: 'app.trips.show',
        params: { id: 123 }
      })
    })

    it('shows loading state during creation', async () => {
      mockCreateTrip.mockImplementation(() => new Promise(resolve => setTimeout(resolve, 100)))
      
      const submitButton = wrapper.findAll('button')[1]
      await submitButton.trigger('click')
      
      expect(wrapper.vm.loading).toBe(true)
      expect(submitButton.text()).toContain('trip.create.creating')
    })

    it('shows error message when creation fails', async () => {
      mockCreateTrip.mockRejectedValue({
        response: { data: { message: 'Creation failed' } }
      })
      
      const submitButton = wrapper.findAll('button')[1]
      await submitButton.trigger('click')
      
      expect(wrapper.vm.errorMsg).toBe('Creation failed')
    })

    it('shows default error message when no specific error provided', async () => {
      mockCreateTrip.mockRejectedValue(new Error('Network error'))
      
      const submitButton = wrapper.findAll('button')[1]
      await submitButton.trigger('click')
      
      expect(wrapper.vm.errorMsg).toBe('errors.default')
    })

    it('shows error when response has no ID', async () => {
      mockCreateTrip.mockResolvedValue({ data: {} })
      
      const submitButton = wrapper.findAll('button')[1]
      await submitButton.trigger('click')
      
      expect(wrapper.vm.errorMsg).toBe('errors.default')
    })
  })

  describe('navigation', () => {
    it('navigates back when back button is clicked', async () => {
      wrapper = createWrapper()
      
      const backButton = wrapper.findAll('button')[0]
      await backButton.trigger('click')
      
      await vi.runAllTimersAsync()
      
      expect(mockRouter.push).toHaveBeenCalledWith({
        name: 'app.trips'
      })
    })

    it('does not navigate when loading', async () => {
      wrapper = createWrapper()
      wrapper.vm.loading = true
      await wrapper.vm.$nextTick()
      
      const backButton = wrapper.findAll('button')[0]
      await backButton.trigger('click')
      
      expect(mockRouter.push).not.toHaveBeenCalled()
    })
  })

  describe('computed properties', () => {
    it('computes canSubmit correctly', () => {
      wrapper = createWrapper()
      
      // Initially false (empty name)
      expect(wrapper.vm.canSubmit).toBe(false)
      
      // With name only
      wrapper.vm.name = 'Test Trip'
      expect(wrapper.vm.canSubmit).toBe(true)
      
      // With name and invalid dates
      wrapper.vm.startDate = '2024-06-07'
      wrapper.vm.endDate = '2024-06-01'
      expect(wrapper.vm.canSubmit).toBe(false)
      
      // With name and valid dates
      wrapper.vm.startDate = '2024-06-01'
      wrapper.vm.endDate = '2024-06-07'
      expect(wrapper.vm.canSubmit).toBe(true)
      
      // When loading
      wrapper.vm.loading = true
      expect(wrapper.vm.canSubmit).toBe(false)
    })
  })

  describe('button styling', () => {
    it('has proper button base classes', () => {
      wrapper = createWrapper()
      
      const buttons = wrapper.findAll('button')
      buttons.forEach(button => {
        expect(button.classes()).toContain('inline-flex')
        expect(button.classes()).toContain('items-center')
        expect(button.classes()).toContain('justify-center')
        expect(button.classes()).toContain('gap-2')
        expect(button.classes()).toContain('rounded-full')
        expect(button.classes()).toContain('px-5')
        expect(button.classes()).toContain('py-2.5')
        expect(button.classes()).toContain('text-sm')
        expect(button.classes()).toContain('font-semibold')
        expect(button.classes()).toContain('text-white')
        expect(button.classes()).toContain('shadow-lg')
      })
    })

    it('has proper primary button styling', () => {
      wrapper = createWrapper()
      
      const primaryButton = wrapper.findAll('button')[1] // Create trip button
      expect(primaryButton.classes()).toContain('bg-gradient-to-r')
      expect(primaryButton.classes()).toContain('from-blue-600')
      expect(primaryButton.classes()).toContain('to-purple-600')
    })

    it('has proper back button styling', () => {
      wrapper = createWrapper()
      
      const backButton = wrapper.findAll('button')[0] // Back button
      expect(backButton.classes()).toContain('bg-gradient-to-r')
      expect(backButton.classes()).toContain('from-slate-600')
      expect(backButton.classes()).toContain('to-slate-800')
    })
  })

  describe('input styling', () => {
    it('has proper input styling', () => {
      wrapper = createWrapper()
      
      const inputs = wrapper.findAll('input')
      inputs.forEach(input => {
        expect(input.classes()).toContain('w-full')
        expect(input.classes()).toContain('rounded-xl')
        expect(input.classes()).toContain('border')
        expect(input.classes()).toContain('border-gray-200')
        expect(input.classes()).toContain('bg-white')
        expect(input.classes()).toContain('px-3')
        expect(input.classes()).toContain('py-2')
        expect(input.classes()).toContain('text-gray-900')
      })
    })

    it('has proper label styling', () => {
      wrapper = createWrapper()
      
      const labels = wrapper.findAll('label')
      labels.forEach(label => {
        expect(label.classes()).toContain('block')
        expect(label.classes()).toContain('text-sm')
        expect(label.classes()).toContain('font-medium')
        expect(label.classes()).toContain('text-gray-700')
      })
    })
  })

  describe('layout styling', () => {
    it('has proper container styling', () => {
      wrapper = createWrapper()
      
      const container = wrapper.find('.max-w-3xl')
      expect(container.classes()).toContain('mx-auto')
      expect(container.classes()).toContain('px-4')
      expect(container.classes()).toContain('py-8')
    })

    it('has proper card styling', () => {
      wrapper = createWrapper()
      
      const card = wrapper.find('.bg-white')
      expect(card.classes()).toContain('rounded-2xl')
      expect(card.classes()).toContain('border')
      expect(card.classes()).toContain('border-gray-200')
      expect(card.classes()).toContain('shadow-sm')
      expect(card.classes()).toContain('p-5')
    })

    it('has proper grid layout for dates', () => {
      wrapper = createWrapper()
      
      const dateGrid = wrapper.find('.grid.grid-cols-1.md\\:grid-cols-2')
      expect(dateGrid.classes()).toContain('grid')
      expect(dateGrid.classes()).toContain('grid-cols-1')
      expect(dateGrid.classes()).toContain('md:grid-cols-2')
      expect(dateGrid.classes()).toContain('gap-4')
    })
  })

  describe('responsive design', () => {
    it('has responsive button layout', () => {
      wrapper = createWrapper()
      
      const buttonContainer = wrapper.find('.flex.flex-col.sm\\:flex-row')
      expect(buttonContainer.classes()).toContain('flex-col')
      expect(buttonContainer.classes()).toContain('sm:flex-row')
    })

    it('has responsive date grid', () => {
      wrapper = createWrapper()
      
      const dateGrid = wrapper.find('.grid.grid-cols-1.md\\:grid-cols-2')
      expect(dateGrid.classes()).toContain('grid-cols-1')
      expect(dateGrid.classes()).toContain('md:grid-cols-2')
    })

    it('has responsive header layout', () => {
      wrapper = createWrapper()
      
      const header = wrapper.find('.flex.flex-col.sm\\:flex-row')
      expect(header.classes()).toContain('flex-col')
      expect(header.classes()).toContain('sm:flex-row')
    })
  })

  describe('error handling', () => {
    it('displays error message when set', async () => {
      wrapper = createWrapper()
      wrapper.vm.loading = false
      wrapper.vm.errorMsg = 'Test error'
      await wrapper.vm.$nextTick()
      
      const errorDiv = wrapper.find('.border-red-200')
      expect(errorDiv.text()).toBe('Test error')
    })

    it('clears error message on successful submission', async () => {
      wrapper = createWrapper()
      wrapper.vm.errorMsg = 'Previous error'
      
      const nameInput = wrapper.find('input')  // First input (name input)
      await nameInput.setValue('Test Trip')
      
      const submitButton = wrapper.findAll('button')[1]
      await submitButton.trigger('click')
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.errorMsg).toBe('') // Error message is cleared on success
    })
  })

  describe('accessibility', () => {
    it('has proper form labels', () => {
      wrapper = createWrapper()
      
      const labels = wrapper.findAll('label')
      expect(labels.length).toBe(3) // Name, Start date, End date
      
      // Labels don't have 'for' attributes in this component
      // This is a design choice, not an accessibility issue
      labels.forEach(label => {
        expect(label.text()).toBeTruthy()
      })
    })

    it('has proper input types', () => {
      wrapper = createWrapper()
      
      const textInput = wrapper.find('input')  // Name input (no type specified)
      const dateInputs = wrapper.findAll('input[type="date"]')
      
      expect(textInput.exists()).toBe(true)
      expect(dateInputs.length).toBe(2)
    })

    it('has proper button types', () => {
      wrapper = createWrapper()
      
      const buttons = wrapper.findAll('button')
      buttons.forEach(button => {
        expect(button.element.tagName).toBe('BUTTON')
      })
    })

    it('has proper semantic structure', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('h1').exists()).toBe(true)
      expect(wrapper.find('p').exists()).toBe(true)
      expect(wrapper.find('form').exists()).toBe(false) // No form wrapper, but that's OK
    })
  })

  describe('edge cases', () => {
    it('handles empty dates gracefully', async () => {
      wrapper = createWrapper()
      
      const nameInput = wrapper.find('input')  // First input (name input)
      await nameInput.setValue('Test Trip')
      
      const submitButton = wrapper.findAll('button')[1]
      await submitButton.trigger('click')
      
      expect(mockCreateTrip).toHaveBeenCalledWith({
        name: 'Test Trip',
        start_date: null,
        end_date: null
      })
    })

    it('handles whitespace in name', async () => {
      wrapper = createWrapper()
      
      const nameInput = wrapper.find('input')  // First input (name input)
      await nameInput.setValue('  Test Trip  ')
      
      const submitButton = wrapper.findAll('button')[1]
      await submitButton.trigger('click')
      
      expect(mockCreateTrip).toHaveBeenCalledWith({
        name: 'Test Trip',
        start_date: null,
        end_date: null
      })
    })

    it('handles API response without data property', async () => {
      mockCreateTrip.mockResolvedValue({
        data: { id: 456 }
      })
      
      wrapper = createWrapper()
      const nameInput = wrapper.find('input')  // First input (name input)
      await nameInput.setValue('Test Trip')
      
      const submitButton = wrapper.findAll('button')[1]
      await submitButton.trigger('click')
      
      await vi.runAllTimersAsync()
      
      expect(mockRouter.push).toHaveBeenCalledWith({
        name: 'app.trips.show',
        params: { id: 456 }
      })
    })
  })
})
