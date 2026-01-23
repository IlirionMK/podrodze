import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import TripHeaderBar from '@/components/trips/TripHeaderBar.vue'

describe('TripHeaderBar Component', () => {
  let wrapper
  let mockFormatDate

  beforeEach(() => {
    vi.clearAllMocks()
    mockFormatDate = vi.fn((date) => date ? 'Formatted Date' : '—')
  })

  const createWrapper = (props = {}) => {
    return mount(TripHeaderBar, {
      props: {
        trip: {
          id: 1,
          name: 'Test Trip',
          start_date: '2024-06-01',
          end_date: '2024-06-07'
        },
        stats: {
          places: 5,
          members: 3
        },
        bannerImage: 'http://example.com/banner.jpg',
        formatDate: mockFormatDate,
        ...props
      }
    })
  }

  describe('basic rendering', () => {
    it('renders trip header correctly', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('.relative.h-56').exists()).toBe(true)
      expect(wrapper.find('img').exists()).toBe(true)
      expect(wrapper.find('h1').exists()).toBe(true)
    })

    it('displays trip name', () => {
      wrapper = createWrapper()
      
      const title = wrapper.find('h1')
      expect(title.text()).toBe('Test Trip')
    })

    it('displays banner image', () => {
      wrapper = createWrapper()
      
      const image = wrapper.find('img')
      expect(image.attributes('src')).toBe('http://example.com/banner.jpg')
      expect(image.attributes('alt')).toBe('Trip banner')
    })

    it('has proper image styling', () => {
      wrapper = createWrapper()
      
      const image = wrapper.find('img')
      expect(image.classes()).toContain('w-full')
      expect(image.classes()).toContain('h-full')
      expect(image.classes()).toContain('object-cover')
    })
  })

  describe('gradient overlay', () => {
    it('renders gradient overlay', () => {
      wrapper = createWrapper()
      
      const gradient = wrapper.find('.absolute.inset-0.bg-gradient-to-t')
      expect(gradient.exists()).toBe(true)
    })

    it('has proper gradient classes', () => {
      wrapper = createWrapper()
      
      const gradient = wrapper.find('.absolute.inset-0.bg-gradient-to-t')
      expect(gradient.classes()).toContain('from-black/70')
      expect(gradient.classes()).toContain('via-black/30')
      expect(gradient.classes()).toContain('to-black/10')
    })
  })

  describe('date range', () => {
    it('displays formatted date range', () => {
      wrapper = createWrapper()
      
      expect(mockFormatDate).toHaveBeenCalledWith('2024-06-01')
      expect(mockFormatDate).toHaveBeenCalledWith('2024-06-07')
      expect(wrapper.text()).toContain('Formatted Date — Formatted Date')
    })

    it('handles missing dates gracefully', () => {
      wrapper = createWrapper({
        trip: {
          name: 'Test Trip',
          start_date: null,
          end_date: null
        }
      })
      
      expect(wrapper.text()).toContain('— — —')
    })

    it('computes date range correctly', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.dateRange).toBe('Formatted Date — Formatted Date')
    })
  })

  describe('stats display', () => {
    it('displaces places count', () => {
      wrapper = createWrapper()
      
      expect(wrapper.text()).toContain('Miejsca: 5')
    })

    it('displays members count', () => {
      wrapper = createWrapper()
      
      expect(wrapper.text()).toContain('Uczestnicy: 3')
    })

    it('displays stats with proper styling', () => {
      wrapper = createWrapper()
      
      const statSpans = wrapper.findAll('.px-3.py-1.rounded-full')
      expect(statSpans.length).toBe(3) // Date + places + members
      
      statSpans.forEach(span => {
        expect(span.classes()).toContain('bg-white/10')
        expect(span.classes()).toContain('border')
        expect(span.classes()).toContain('border-white/15')
      })
    })

    it('highlights stat numbers', () => {
      wrapper = createWrapper()
      
      const statNumbers = wrapper.findAll('.font-semibold')
      expect(statNumbers.length).toBe(2) // places and members numbers
      
      expect(statNumbers[0].text()).toBe('5')
      expect(statNumbers[1].text()).toBe('3')
    })
  })

  describe('content positioning', () => {
    it('positions content at bottom', () => {
      wrapper = createWrapper()
      
      const contentContainer = wrapper.find('.absolute.bottom-6')
      expect(contentContainer.exists()).toBe(true)
    })

    it('has proper content positioning classes', () => {
      wrapper = createWrapper()
      
      const contentContainer = wrapper.find('.absolute.bottom-6')
      expect(contentContainer.classes()).toContain('left-6')
      expect(contentContainer.classes()).toContain('right-6')
      expect(contentContainer.classes()).toContain('text-white')
      expect(contentContainer.classes()).toContain('drop-shadow')
    })

    it('uses min-w-0 for text truncation', () => {
      wrapper = createWrapper()
      
      const textContainer = wrapper.find('.min-w-0')
      expect(textContainer.exists()).toBe(true)
    })
  })

  describe('title styling', () => {
    it('has proper title classes', () => {
      wrapper = createWrapper()
      
      const title = wrapper.find('h1')
      expect(title.classes()).toContain('text-3xl')
      expect(title.classes()).toContain('md:text-4xl')
      expect(title.classes()).toContain('font-bold')
      expect(title.classes()).toContain('leading-tight')
      expect(title.classes()).toContain('truncate')
    })

    it('truncates long titles', () => {
      wrapper = createWrapper({
        trip: {
          name: 'This is a very long trip name that should be truncated to fit in the available space without breaking the layout'
        }
      })
      
      const title = wrapper.find('h1')
      expect(title.classes()).toContain('truncate')
    })
  })

  describe('responsive design', () => {
    it('has responsive height classes', () => {
      wrapper = createWrapper()
      
      const container = wrapper.find('.relative')
      expect(container.classes()).toContain('h-56')
      expect(container.classes()).toContain('md:h-80')
    })

    it('has responsive title size', () => {
      wrapper = createWrapper()
      
      const title = wrapper.find('h1')
      expect(title.classes()).toContain('text-3xl')
      expect(title.classes()).toContain('md:text-4xl')
    })

    it('has responsive stats layout', () => {
      wrapper = createWrapper()
      
      const statsContainer = wrapper.find('.text-sm.opacity-90')
      expect(statsContainer.classes()).toContain('flex')
      expect(statsContainer.classes()).toContain('flex-wrap')
      expect(statsContainer.classes()).toContain('gap-2')
    })
  })

  describe('edge cases', () => {
    it('handles missing trip data', () => {
      wrapper = createWrapper({
        trip: {},
        stats: {},
        bannerImage: ''
      })
      
      expect(wrapper.find('h1').text()).toBe('')
      expect(wrapper.text()).toContain('— — —')
    })

    it('handles missing stats', () => {
      wrapper = createWrapper({
        stats: {}
      })
      
      expect(wrapper.text()).toContain('Miejsca:')
      expect(wrapper.text()).toContain('Uczestnicy:')
    })

    it('handles missing banner image', () => {
      wrapper = createWrapper({
        bannerImage: ''
      })
      
      const image = wrapper.find('img')
      expect(image.attributes('src')).toBe('')
    })

    it('handles single day trip', () => {
      wrapper = createWrapper({
        trip: {
          name: 'Day Trip',
          start_date: '2024-06-01',
          end_date: '2024-06-01'
        }
      })
      
      expect(mockFormatDate).toHaveBeenCalledWith('2024-06-01')
      expect(mockFormatDate).toHaveBeenCalledWith('2024-06-01')
      expect(wrapper.text()).toContain('Formatted Date — Formatted Date')
    })
  })

  describe('translations', () => {
    it('uses translation keys for stats labels', () => {
      wrapper = createWrapper()
      
      expect(wrapper.text()).toContain('Miejsca:')
      expect(wrapper.text()).toContain('Uczestnicy:')
    })

    it('falls back to fallback text', () => {
      wrapper = createWrapper()
      
      // Should show translated text
      expect(wrapper.text()).toContain('Miejsca:')
      expect(wrapper.text()).toContain('Uczestnicy:')
    })
  })

  describe('component structure', () => {
    it('has proper container structure', () => {
      wrapper = createWrapper()
      
      const container = wrapper.find('.relative.h-56')
      expect(container.find('img').exists()).toBe(true)
      expect(container.find('.absolute.inset-0').exists()).toBe(true)
      expect(container.find('.absolute.bottom-6').exists()).toBe(true)
    })

    it('has proper text structure', () => {
      wrapper = createWrapper()
      
      const textContainer = wrapper.find('.absolute.bottom-6 .min-w-0')
      expect(textContainer.find('h1').exists()).toBe(true)
      expect(textContainer.find('.text-sm.opacity-90').exists()).toBe(true)
    })

    it('has proper stats structure', () => {
      wrapper = createWrapper()
      
      const statsContainer = wrapper.find('.text-sm.opacity-90')
      const statSpans = statsContainer.findAll('.px-3.py-1.rounded-full')
      expect(statSpans.length).toBe(3)
    })
  })

  describe('accessibility', () => {
    it('has alt text for banner image', () => {
      wrapper = createWrapper()
      
      const image = wrapper.find('img')
      expect(image.attributes('alt')).toBe('Trip banner')
    })

    it('maintains text contrast with drop shadow', () => {
      wrapper = createWrapper()
      
      const textContainer = wrapper.find('.absolute.bottom-6')
      expect(textContainer.classes()).toContain('text-white')
      expect(textContainer.classes()).toContain('drop-shadow')
    })
  })
})
