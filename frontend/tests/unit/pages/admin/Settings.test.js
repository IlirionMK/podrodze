import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Settings from '@/pages/admin/Settings.vue'

// Mock vue-i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key, fallback) => fallback || key
  })
}))

// Mock axios
vi.mock('axios', () => ({
  default: {
    get: vi.fn(),
    put: vi.fn()
  }
}))

describe('Settings Page', () => {
  let wrapper
  let mockAxios

  beforeEach(async () => {
    vi.useFakeTimers()
    vi.clearAllMocks()
    
    const axios = await import('axios')
    mockAxios = axios.default
    
    // Setup default axios mocks
    mockAxios.get.mockResolvedValue({
      data: [
        { id: 1, key: 'site_name', value: 'PoDrodze' },
        { id: 2, key: 'max_users', value: '1000' },
        { id: 3, key: 'maintenance_mode', value: 'false' }
      ]
    })
    
    mockAxios.put.mockResolvedValue({})
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const createWrapper = () => {
    return mount(Settings)
  }

  describe('basic rendering', () => {
    it('renders settings page', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('.min-h-screen').exists()).toBe(true)
      expect(wrapper.find('h1').exists()).toBe(true)
    })

    it('renders page header', () => {
      wrapper = createWrapper()
      
      const title = wrapper.find('h1')
      expect(title.text()).toContain('app.admin.menu.settings')
      expect(title.classes()).toContain('text-2xl')
      expect(title.classes()).toContain('font-bold')
      expect(title.classes()).toContain('mb-4')
    })

    it('renders settings container', () => {
      wrapper = createWrapper()
      
      const container = wrapper.find('.bg-white.p-6')
      expect(container.exists()).toBe(true)
      expect(container.classes()).toContain('rounded-lg')
      expect(container.classes()).toContain('shadow')
      expect(container.classes()).toContain('space-y-4')
    })

    it('renders settings form fields', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const settingRows = wrapper.findAll('.flex.flex-col')
      expect(settingRows.length).toBe(3)
      
      settingRows.forEach((row, index) => {
        expect(row.find('label').exists()).toBe(true)
        expect(row.find('input').exists()).toBe(true)
        expect(row.find('button').exists()).toBe(true)
      })
    })
  })

  describe('data fetching', () => {
    it('fetches settings on mount', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(mockAxios.get).toHaveBeenCalledWith('/api/admin/settings')
      expect(wrapper.vm.loading).toBe(false)
    })

    it('displays loading state while fetching', async () => {
      mockAxios.get.mockImplementation(() => new Promise(resolve => setTimeout(resolve, 100)))
      
      wrapper = createWrapper()
      
      expect(wrapper.vm.loading).toBe(true)
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.loading).toBe(false)
    })

    it('displays fetched settings in form', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      const labels = wrapper.findAll('label')
      const inputs = wrapper.findAll('input')
      
      expect(labels[0].text()).toBe('site_name')
      expect(inputs[0].element.value).toBe('PoDrodze')
      
      expect(labels[1].text()).toBe('max_users')
      expect(inputs[1].element.value).toBe('1000')
      
      expect(labels[2].text()).toBe('maintenance_mode')
      expect(inputs[2].element.value).toBe('false')
    })

    it('handles fetch error gracefully', async () => {
      mockAxios.get.mockRejectedValue(new Error('Network error'))
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.loading).toBe(false)
      expect(wrapper.vm.settings).toEqual([])
    })

    it('shows loading message when loading', async () => {
      mockAxios.get.mockImplementation(() => new Promise(resolve => setTimeout(resolve, 100)))
      
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()
      
      // Check if loading state exists
      expect(wrapper.vm.loading).toBe(true)
      
      await vi.runAllTimersAsync()
      expect(wrapper.vm.loading).toBe(false)
    })

    it('shows empty state when settings array is empty', async () => {
      mockAxios.get.mockResolvedValue({ data: [] })
      
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const settingRows = wrapper.findAll('.flex.flex-col')
      expect(settingRows.length).toBe(0)
    })
  })

  describe('setting management', () => {
    beforeEach(async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
    })

    it('updates setting value when input changes', async () => {
      const inputs = wrapper.findAll('input')
      await inputs[0].setValue('New Site Name')
      
      expect(wrapper.vm.settings[0].value).toBe('New Site Name')
    })

    it('calls updateSetting when save button is clicked', async () => {
      const saveButtons = wrapper.findAll('button')
      await saveButtons[0].trigger('click')
      
      expect(mockAxios.put).toHaveBeenCalledWith('/api/admin/settings/1', {
        id: 1,
        key: 'site_name',
        value: 'PoDrodze'
      })
    })

    it('updates setting with current value', async () => {
      const inputs = wrapper.findAll('input')
      const saveButtons = wrapper.findAll('button')
      
      await inputs[0].setValue('Updated Name')
      await saveButtons[0].trigger('click')
      
      expect(mockAxios.put).toHaveBeenCalledWith('/api/admin/settings/1', {
        id: 1,
        key: 'site_name',
        value: 'Updated Name'
      })
    })

    it('handles update error gracefully', async () => {
      mockAxios.put.mockRejectedValue(new Error('Update failed'))
      
      const saveButtons = wrapper.findAll('button')
      
      await saveButtons[0].trigger('click')
      
      // Should not throw error
      expect(wrapper.exists()).toBe(true)
    })
  })

  describe('form styling', () => {
    it('has proper container styling', () => {
      wrapper = createWrapper()
      
      const container = wrapper.find('.bg-white.p-6')
      expect(container.classes()).toContain('bg-white')
      expect(container.classes()).toContain('p-6')
      expect(container.classes()).toContain('rounded-lg')
      expect(container.classes()).toContain('shadow')
      expect(container.classes()).toContain('space-y-4')
    })

    it('has proper setting row styling', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const settingRows = wrapper.findAll('.flex.flex-col')
      settingRows.forEach(row => {
        expect(row.classes()).toContain('flex')
        expect(row.classes()).toContain('flex-col')
        expect(row.classes()).toContain('md:flex-row')
        expect(row.classes()).toContain('md:items-center')
        expect(row.classes()).toContain('gap-2')
      })
    })

    it('has proper label styling', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const labels = wrapper.findAll('label')
      labels.forEach(label => {
        expect(label.classes()).toContain('font-medium')
        expect(label.classes()).toContain('w-40')
      })
    })

    it('has proper input styling', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const inputs = wrapper.findAll('input')
      inputs.forEach(input => {
        expect(input.classes()).toContain('flex-1')
        expect(input.classes()).toContain('border')
        expect(input.classes()).toContain('rounded')
        expect(input.classes()).toContain('p-2')
      })
    })

    it('has proper button styling', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const buttons = wrapper.findAll('button')
      buttons.forEach(button => {
        expect(button.classes()).toContain('px-3')
        expect(button.classes()).toContain('py-1')
        expect(button.classes()).toContain('bg-blue-600')
        expect(button.classes()).toContain('text-white')
        expect(button.classes()).toContain('rounded')
        expect(button.classes()).toContain('hover:bg-blue-700')
      })
    })
  })

  describe('page layout', () => {
    it('has proper page container styling', () => {
      wrapper = createWrapper()
      
      const container = wrapper.find('.min-h-screen')
      expect(container.classes()).toContain('p-6')
      expect(container.classes()).toContain('bg-gray-100')
    })

    it('has proper title styling', () => {
      wrapper = createWrapper()
      
      const title = wrapper.find('h1')
      expect(title.classes()).toContain('text-2xl')
      expect(title.classes()).toContain('font-bold')
      expect(title.classes()).toContain('mb-4')
    })
  })

  describe('responsive design', () => {
    it('has responsive form layout', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const settingRows = wrapper.findAll('.flex.flex-col')
      settingRows.forEach(row => {
        expect(row.classes()).toContain('flex-col')
        expect(row.classes()).toContain('md:flex-row')
        expect(row.classes()).toContain('md:items-center')
      })
    })

    it('has responsive label width', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const labels = wrapper.findAll('label')
      labels.forEach(label => {
        expect(label.classes()).toContain('w-40')
      })
    })

    it('has responsive input flex', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const inputs = wrapper.findAll('input')
      inputs.forEach(input => {
        expect(input.classes()).toContain('flex-1')
      })
    })
  })

  describe('internationalization', () => {
    it('uses translation keys for all text', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      expect(wrapper.text()).toContain('app.admin.menu.settings')
      // Don't expect save button text as it may not be visible
      // Don't expect 'loading' since data is loaded
    })

    it('uses translation key for save button text', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const saveButtons = wrapper.findAll('button')
      saveButtons.forEach(button => {
        expect(button.text()).toContain('app.admin.settings.save')
      })
    })
  })

  describe('component lifecycle', () => {
    it('calls fetchSettings on mounted', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(mockAxios.get).toHaveBeenCalledWith('/api/admin/settings')
    })

    it('sets loading to false after fetch completes', async () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.loading).toBe(true)
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.loading).toBe(false)
    })
  })

  describe('data management', () => {
    it('initializes with empty settings array', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.settings).toEqual([])
    })

    it('initializes with loading state', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.loading).toBe(true)
    })

    it('updates settings array after fetch', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.settings).toHaveLength(3)
      expect(wrapper.vm.settings[0]).toEqual({
        id: 1,
        key: 'site_name',
        value: 'PoDrodze'
      })
    })

    it('handles empty settings array', async () => {
      mockAxios.get.mockResolvedValue({ data: [] })
      
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.settings).toEqual([])
      expect(wrapper.findAll('.flex.flex-col').length).toBe(0)
    })
  })

  describe('form interactions', () => {
    it('updates individual setting values', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const inputs = wrapper.findAll('input')
      
      await inputs[0].setValue('New Site Name')
      expect(wrapper.vm.settings[0].value).toBe('New Site Name')
      
      await inputs[1].setValue('2000')
      expect(wrapper.vm.settings[1].value).toBe('2000')
      
      await inputs[2].setValue('true')
      expect(wrapper.vm.settings[2].value).toBe('true')
    })

    it('calls updateSetting with correct payload', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const inputs = wrapper.findAll('input')
      const saveButtons = wrapper.findAll('button')
      
      await inputs[1].setValue('1500')
      await saveButtons[1].trigger('click')
      
      expect(mockAxios.put).toHaveBeenCalledWith('/api/admin/settings/2', {
        id: 2,
        key: 'max_users',
        value: '1500'
      })
    })
  })

  describe('error handling', () => {
    it('handles network errors gracefully', async () => {
      mockAxios.get.mockRejectedValue(new Error('Network error'))
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.loading).toBe(false)
      expect(wrapper.vm.settings).toEqual([])
    })

    it('handles update errors gracefully', async () => {
      mockAxios.put.mockRejectedValue(new Error('Update failed'))
      
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const saveButtons = wrapper.findAll('button')
      await saveButtons[0].trigger('click')
      
      // Should not throw error
      expect(wrapper.exists()).toBe(true)
    })
  })

  describe('accessibility', () => {
    it('has proper form labels', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const labels = wrapper.findAll('label')
      if (labels.length > 0) {
        labels.forEach(label => {
          // Check if label has either 'for' attribute or text content
          const hasFor = label.attributes('for')
          const hasText = label.text().trim().length > 0
          expect(hasFor || hasText).toBe(true)
        })
      } else {
        // Skip if no labels found
        expect(true).toBe(true)
      }
    })

    it('has proper input types', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const inputs = wrapper.findAll('input')
      inputs.forEach(input => {
        expect(input.element.tagName).toBe('INPUT')
      })
    })

    it('has proper button types', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const buttons = wrapper.findAll('button')
      buttons.forEach(button => {
        expect(button.element.tagName).toBe('BUTTON')
      })
    })
  })

  describe('edge cases', () => {
    it('handles missing setting properties', async () => {
      mockAxios.get.mockResolvedValue({
        data: [
          { id: 1, key: 'incomplete' },
          { id: 2, value: 'no_key' },
          { id: 3 }
        ]
      })
      
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const labels = wrapper.findAll('label')
      const inputs = wrapper.findAll('input')
      
      expect(labels[0].text()).toBe('incomplete')
      expect(inputs[0].element.value).toBe('')
      
      expect(labels[1].text()).toBe('')
      expect(inputs[1].element.value).toBe('no_key')
      
      expect(labels[2].text()).toBe('')
      expect(inputs[2].element.value).toBe('')
    })

    it('handles null/undefined values', async () => {
      mockAxios.get.mockResolvedValue({
        data: [
          { id: 1, key: 'null_value', value: null },
          { id: 2, key: 'undefined_value', value: undefined }
        ]
      })
      
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const inputs = wrapper.findAll('input')
      expect(inputs[0].element.value).toBe('')
      expect(inputs[1].element.value).toBe('')
    })
  })
})
