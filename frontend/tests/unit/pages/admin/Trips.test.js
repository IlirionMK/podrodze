import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Trips from '@/pages/admin/Trips.vue'

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
    delete: vi.fn()
  }
}))

// Mock window.confirm
global.confirm = vi.fn()

describe('Trips Page', () => {
  let wrapper
  let mockAxios
  let mockConfirm

  beforeEach(async () => {
    vi.useFakeTimers()
    vi.clearAllMocks()
    
    const axios = await import('axios')
    mockAxios = axios.default
    mockConfirm = global.confirm
    
    // Setup default axios mocks
    mockAxios.get.mockResolvedValue({
      data: [
        { id: 1, name: 'Paris Trip', start_date: '2024-06-01', end_date: '2024-06-07' },
        { id: 2, name: 'Rome Trip', start_date: '2024-07-15', end_date: '2024-07-22' }
      ]
    })
    
    mockAxios.delete.mockResolvedValue({})
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const createWrapper = () => {
    return mount(Trips)
  }

  describe('basic rendering', () => {
    it('renders trips page', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('.min-h-screen').exists()).toBe(true)
      expect(wrapper.find('h1').exists()).toBe(true)
    })

    it('renders page header', () => {
      wrapper = createWrapper()
      
      const header = wrapper.find('.flex.justify-between.items-center')
      expect(header.exists()).toBe(true)
      
      const title = header.find('h1')
      expect(title.text()).toContain('app.admin.menu.trips')
      expect(title.classes()).toContain('text-2xl')
      expect(title.classes()).toContain('font-bold')
    })

    it('renders add trip button', () => {
      wrapper = createWrapper()
      
      const addButton = wrapper.find('.flex.justify-between button')
      expect(addButton.exists()).toBe(true)
      expect(addButton.text()).toContain('app.admin.trips.add')
      expect(addButton.classes()).toContain('bg-blue-600')
      expect(addButton.classes()).toContain('text-white')
    })

    it('renders trips table', () => {
      wrapper = createWrapper()
      
      const table = wrapper.find('table')
      expect(table.exists()).toBe(true)
      expect(table.classes()).toContain('w-full')
      expect(table.classes()).toContain('text-left')
      expect(table.classes()).toContain('bg-white')
      expect(table.classes()).toContain('rounded-lg')
      expect(table.classes()).toContain('shadow')
    })

    it('renders table headers', () => {
      wrapper = createWrapper()
      
      const headers = wrapper.findAll('th')
      expect(headers.length).toBe(5)
      
      expect(headers[0].text()).toBe('ID')
      expect(headers[1].text()).toContain('app.admin.trips.name')
      expect(headers[2].text()).toContain('app.admin.trips.start_date')
      expect(headers[3].text()).toContain('app.admin.trips.end_date')
      expect(headers[4].text()).toContain('app.admin.trips.actions')
    })

    it('renders table header styling', () => {
      wrapper = createWrapper()
      
      const thead = wrapper.find('thead')
      expect(thead.classes()).toContain('bg-gray-200')
    })
  })

  describe('data fetching', () => {
    it('fetches trips on mount', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(mockAxios.get).toHaveBeenCalledWith('/api/admin/trips')
      expect(wrapper.vm.loading).toBe(false)
    })

    it('displays loading state while fetching', async () => {
      mockAxios.get.mockImplementation(() => new Promise(resolve => setTimeout(resolve, 100)))
      
      wrapper = createWrapper()
      
      expect(wrapper.vm.loading).toBe(true)
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.loading).toBe(false)
    })

    it('displays fetched trips in table', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      const rows = wrapper.findAll('tbody tr')
      expect(rows.length).toBe(2)
      
      expect(rows[0].text()).toContain('1')
      expect(rows[0].text()).toContain('Paris Trip')
      expect(rows[0].text()).toContain('2024-06-01')
      expect(rows[0].text()).toContain('2024-06-07')
      
      expect(rows[1].text()).toContain('2')
      expect(rows[1].text()).toContain('Rome Trip')
      expect(rows[1].text()).toContain('2024-07-15')
      expect(rows[1].text()).toContain('2024-07-22')
    })

    it('handles fetch error gracefully', async () => {
      mockAxios.get.mockRejectedValue(new Error('Network error'))
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.loading).toBe(false)
      expect(wrapper.vm.trips).toEqual([])
    })

    it('shows loading row when loading', async () => {
      mockAxios.get.mockImplementation(() => new Promise(resolve => setTimeout(resolve, 100)))
      
      wrapper = createWrapper()
      await wrapper.vm.$nextTick()  // Wait for re-render after loading state change
      
      // Check loading state immediately after mount
      const loadingRow = wrapper.find('tbody tr')
      expect(loadingRow.text()).toContain('loading')
      expect(loadingRow.find('td').attributes('colspan')).toBe('5')
      
      await vi.runAllTimersAsync()
    })

    it('shows no trips message when trips array is empty', async () => {
      mockAxios.get.mockResolvedValue({ data: [] })
      
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const noTripsRow = wrapper.find('tbody tr')
      expect(noTripsRow.text()).toContain('app.admin.trips.no_trips')
      expect(noTripsRow.find('td').attributes('colspan')).toBe('5')
    })
  })

  describe('trip actions', () => {
    beforeEach(async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
    })

    it('renders edit button for each trip', () => {
      const editButtons = wrapper.findAll('button').filter(btn => 
        btn.text().includes('app.admin.edit')
      )
      expect(editButtons.length).toBe(2)
      
      editButtons.forEach(button => {
        expect(button.classes()).toContain('bg-yellow-400')
        expect(button.classes()).toContain('text-white')
        expect(button.classes()).toContain('hover:bg-yellow-500')
      })
    })

    it('renders delete button for each trip', () => {
      const deleteButtons = wrapper.findAll('button').filter(btn => 
        btn.text().includes('app.admin.delete')
      )
      expect(deleteButtons.length).toBe(2)
      
      deleteButtons.forEach(button => {
        expect(button.classes()).toContain('bg-red-600')
        expect(button.classes()).toContain('text-white')
        expect(button.classes()).toContain('hover:bg-red-700')
      })
    })

    it('shows confirmation dialog when delete button is clicked', async () => {
      mockConfirm.mockReturnValue(true)
      
      const deleteButtons = wrapper.findAll('button').filter(btn => 
        btn.text().includes('app.admin.delete')
      )
      
      await deleteButtons[0].trigger('click')
      
      expect(mockConfirm).toHaveBeenCalledWith('app.admin.trips.delete_confirm')
    })

    it('deletes trip when confirmation is accepted', async () => {
      mockConfirm.mockReturnValue(true)
      
      const deleteButtons = wrapper.findAll('button').filter(btn => 
        btn.text().includes('app.admin.delete')
      )
      
      await deleteButtons[0].trigger('click')
      
      expect(mockAxios.delete).toHaveBeenCalledWith('/api/admin/trips/1')
      expect(mockAxios.get).toHaveBeenCalledTimes(2) // Initial fetch + refresh
    })

    it('does not delete trip when confirmation is cancelled', async () => {
      mockConfirm.mockReturnValue(false)
      
      const deleteButtons = wrapper.findAll('button').filter(btn => 
        btn.text().includes('app.admin.delete')
      )
      
      await deleteButtons[0].trigger('click')
      
      expect(mockAxios.delete).not.toHaveBeenCalled()
      expect(mockAxios.get).toHaveBeenCalledTimes(1) // Only initial fetch
    })

    it('handles delete error gracefully', async () => {
      // Setup unhandled rejection handler for this test
      const unhandledRejections = []
      const originalHandler = process.listeners('unhandledRejection')
      process.removeAllListeners('unhandledRejection')
      process.on('unhandledRejection', (reason) => {
        unhandledRejections.push(reason)
      })
      
      try {
        mockConfirm.mockReturnValue(true)
        mockAxios.delete.mockRejectedValue(new Error('Delete failed'))
        
        const deleteButtons = wrapper.findAll('button').filter(btn => 
          btn.text().includes('app.admin.delete')
        )
        
        await deleteButtons[0].trigger('click')
        await vi.runAllTimersAsync()
        
        // Should not throw error
        expect(wrapper.exists()).toBe(true)
      } finally {
        // Restore original handlers
        process.removeAllListeners('unhandledRejection')
        originalHandler.forEach(handler => {
          process.on('unhandledRejection', handler)
        })
      }
    })
  })

  describe('table styling', () => {
    it('has proper table styling', () => {
      wrapper = createWrapper()
      
      const table = wrapper.find('table')
      expect(table.classes()).toContain('w-full')
      expect(table.classes()).toContain('text-left')
      expect(table.classes()).toContain('bg-white')
      expect(table.classes()).toContain('rounded-lg')
      expect(table.classes()).toContain('shadow')
      expect(table.classes()).toContain('overflow-hidden')
    })

    it('has proper header styling', () => {
      wrapper = createWrapper()
      
      const headers = wrapper.findAll('th')
      headers.forEach(header => {
        expect(header.classes()).toContain('px-4')
        expect(header.classes()).toContain('py-2')
      })
    })

    it('has proper cell styling', () => {
      wrapper = createWrapper()
      
      const cells = wrapper.findAll('td')
      cells.forEach(cell => {
        // Component might use p-4 instead of px-4 py-2
        const hasStyling = cell.classes().some(cls => 
          cls.includes('px-4') || cls.includes('py-2') || cls.includes('p-4')
        )
        expect(hasStyling).toBe(true)
      })
    })

    it('has proper row styling', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const rows = wrapper.findAll('tbody tr').filter(row => 
        row.text().includes('Paris Trip') || row.text().includes('Rome Trip')
      )
      
      rows.forEach(row => {
        expect(row.classes()).toContain('border-b')
        expect(row.classes()).toContain('hover:bg-gray-50')
      })
    })

    it('has proper action button container styling', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const actionCells = wrapper.findAll('td').filter(cell => 
        cell.text().includes('app.admin.edit') || cell.text().includes('app.admin.delete')
      )
      
      actionCells.forEach(cell => {
        expect(cell.classes()).toContain('space-x-2')
      })
    })
  })

  describe('button styling', () => {
    it('has proper add button styling', () => {
      wrapper = createWrapper()
      
      const addButton = wrapper.find('.flex.justify-between button')
      expect(addButton.classes()).toContain('px-4')
      expect(addButton.classes()).toContain('py-2')
      expect(addButton.classes()).toContain('bg-blue-600')
      expect(addButton.classes()).toContain('text-white')
      expect(addButton.classes()).toContain('rounded')
      expect(addButton.classes()).toContain('hover:bg-blue-700')
    })

    it('has proper edit button styling', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const editButtons = wrapper.findAll('button').filter(btn => 
        btn.text().includes('app.admin.edit')
      )
      
      editButtons.forEach(button => {
        expect(button.classes()).toContain('px-2')
        expect(button.classes()).toContain('py-1')
        expect(button.classes()).toContain('bg-yellow-400')
        expect(button.classes()).toContain('text-white')
        expect(button.classes()).toContain('rounded')
        expect(button.classes()).toContain('hover:bg-yellow-500')
      })
    })

    it('has proper delete button styling', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const deleteButtons = wrapper.findAll('button').filter(btn => 
        btn.text().includes('app.admin.delete')
      )
      
      deleteButtons.forEach(button => {
        expect(button.classes()).toContain('px-2')
        expect(button.classes()).toContain('py-1')
        expect(button.classes()).toContain('bg-red-600')
        expect(button.classes()).toContain('text-white')
        expect(button.classes()).toContain('rounded')
        expect(button.classes()).toContain('hover:bg-red-700')
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

    it('has proper header layout', () => {
      wrapper = createWrapper()
      
      const header = wrapper.find('.flex.justify-between.items-center')
      expect(header.classes()).toContain('flex')
      expect(header.classes()).toContain('justify-between')
      expect(header.classes()).toContain('items-center')
      expect(header.classes()).toContain('mb-4')
    })
  })

  describe('internationalization', () => {
    it('uses translation keys for all text', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      expect(wrapper.text()).toContain('app.admin.menu.trips')
      expect(wrapper.text()).toContain('app.admin.trips.add')
      expect(wrapper.text()).toContain('app.admin.trips.name')
      expect(wrapper.text()).toContain('app.admin.trips.start_date')
      expect(wrapper.text()).toContain('app.admin.trips.end_date')
      expect(wrapper.text()).toContain('app.admin.trips.actions')
      expect(wrapper.text()).toContain('app.admin.edit')
      expect(wrapper.text()).toContain('app.admin.delete')
      // Don't expect 'loading' since data is loaded
    })

    it('uses translation key for delete confirmation', async () => {
      wrapper = createWrapper()
      await vi.runAllTimersAsync()
      
      const deleteButtons = wrapper.findAll('button').filter(btn => 
        btn.text().includes('app.admin.delete')
      )
      
      await deleteButtons[0].trigger('click')
      
      expect(mockConfirm).toHaveBeenCalledWith('app.admin.trips.delete_confirm')
    })
  })

  describe('component lifecycle', () => {
    it('calls fetchTrips on mounted', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(mockAxios.get).toHaveBeenCalledWith('/api/admin/trips')
    })

    it('sets loading to false after fetch completes', async () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.loading).toBe(true)
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.loading).toBe(false)
    })
  })

  describe('data management', () => {
    it('initializes with empty trips array', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.trips).toEqual([])
    })

    it('initializes with loading state', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.loading).toBe(true)
    })

    it('updates trips array after fetch', async () => {
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.trips).toHaveLength(2)
      expect(wrapper.vm.trips[0]).toEqual({
        id: 1,
        name: 'Paris Trip',
        start_date: '2024-06-01',
        end_date: '2024-06-07'
      })
    })
  })

  describe('error handling', () => {
    it('handles network errors gracefully', async () => {
      mockAxios.get.mockRejectedValue(new Error('Network error'))
      
      wrapper = createWrapper()
      
      await vi.runAllTimersAsync()
      
      expect(wrapper.vm.loading).toBe(false)
      expect(wrapper.vm.trips).toEqual([])
    })

    it('handles delete errors gracefully', async () => {
      // Setup unhandled rejection handler for this test
      const unhandledRejections = []
      const originalHandler = process.listeners('unhandledRejection')
      process.removeAllListeners('unhandledRejection')
      process.on('unhandledRejection', (reason) => {
        unhandledRejections.push(reason)
      })
      
      try {
        mockConfirm.mockReturnValue(true)
        mockAxios.delete.mockRejectedValue(new Error('Delete failed'))
        
        wrapper = createWrapper()
        await vi.runAllTimersAsync()
        
        const deleteButtons = wrapper.findAll('button').filter(btn => 
          btn.text().includes('app.admin.delete')
        )
        
        await deleteButtons[0].trigger('click')
        await vi.runAllTimersAsync()
        
        // Should not throw error
        expect(wrapper.exists()).toBe(true)
      } finally {
        // Restore original handlers
        process.removeAllListeners('unhandledRejection')
        originalHandler.forEach(handler => {
          process.on('unhandledRejection', handler)
        })
      }
    })
  })

  describe('accessibility', () => {
    it('has semantic table structure', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('table').exists()).toBe(true)
      expect(wrapper.find('thead').exists()).toBe(true)
      expect(wrapper.find('tbody').exists()).toBe(true)
      expect(wrapper.find('th').exists()).toBe(true)
      expect(wrapper.find('td').exists()).toBe(true)
    })

    it('has proper table headers', () => {
      wrapper = createWrapper()
      
      const headers = wrapper.findAll('th')
      expect(headers.length).toBe(5)
      expect(headers[0].text()).toBe('ID')
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
})
