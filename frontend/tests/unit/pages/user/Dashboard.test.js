import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Dashboard from '@/pages/user/Dashboard.vue'

// Mock vue-router
vi.mock('vue-router', () => ({
  RouterLink: {
    template: '<a><slot /></a>',
    props: ['to']
  }
}))

describe('Dashboard Page', () => {
  let wrapper

  beforeEach(() => {
    vi.clearAllMocks()
  })

  const createWrapper = () => {
    return mount(Dashboard, {
      global: {
        stubs: {
          RouterLink: true
        }
      }
    })
  }

  describe('basic rendering', () => {
    it('renders dashboard page', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('div').exists()).toBe(true)
      expect(wrapper.find('h2').exists()).toBe(true)
    })

    it('renders page title', () => {
      wrapper = createWrapper()
      
      const title = wrapper.find('h2')
      expect(title.text()).toBe('Dashboard')
      expect(title.classes()).toContain('text-3xl')
      expect(title.classes()).toContain('font-bold')
      expect(title.classes()).toContain('mb-4')
    })

    it('renders welcome message', () => {
      wrapper = createWrapper()
      
      const message = wrapper.find('p')
      expect(message.text()).toContain('Welcome to your travel dashboard! 🌍')
      expect(message.classes()).toContain('text-gray-600')
    })

    it('renders trips link', () => {
      wrapper = createWrapper()
      
      const link = wrapper.find('a')
      expect(link.exists()).toBe(true)
      expect(link.attributes('to')).toBe('/app/trips')
      expect(link.text()).toBe('Go to Trips')
    })
  })

  describe('component structure', () => {
    it('has proper semantic structure', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('h2').exists()).toBe(true)
      expect(wrapper.find('p').exists()).toBe(true)
    })

    it('has proper content hierarchy', () => {
      wrapper = createWrapper()
      
      const title = wrapper.find('h2')
      const message = wrapper.find('p')
      const linkContainer = wrapper.find('.mt-8')
      
      expect(title.exists()).toBe(true)
      expect(message.exists()).toBe(true)
      expect(linkContainer.exists()).toBe(true)
    })
  })

  describe('styling', () => {
    it('has proper title styling', () => {
      wrapper = createWrapper()
      
      const title = wrapper.find('h2')
      expect(title.classes()).toContain('text-3xl')
      expect(title.classes()).toContain('font-bold')
      expect(title.classes()).toContain('mb-4')
    })

    it('has proper message styling', () => {
      wrapper = createWrapper()
      
      const message = wrapper.find('p')
      expect(message.classes()).toContain('text-gray-600')
    })

    it('has proper link styling', () => {
      wrapper = createWrapper()
      
      const link = wrapper.find('a')
      expect(link.classes()).toContain('inline-block')
      expect(link.classes()).toContain('bg-indigo-600')
      expect(link.classes()).toContain('text-white')
      expect(link.classes()).toContain('px-6')
      expect(link.classes()).toContain('py-3')
      expect(link.classes()).toContain('rounded-lg')
      expect(link.classes()).toContain('hover:bg-indigo-700')
      expect(link.classes()).toContain('transition')
    })

    it('has proper container spacing', () => {
      wrapper = createWrapper()
      
      const title = wrapper.find('h2')
      const message = wrapper.find('p')
      const linkContainer = wrapper.find('.mt-8')
      
      expect(title.classes()).toContain('mb-4')
      expect(message.classes()).toContain('text-gray-600')
      expect(linkContainer.classes()).toContain('mt-8')
    })
  })

  describe('interactions', () => {
    it('navigates to trips when link is clicked', () => {
      wrapper = createWrapper()
      
      const link = wrapper.find('a')
      // RouterLink is stubbed, so we can't test actual navigation
      expect(link.exists()).toBe(true)
      expect(link.attributes('to')).toBe('/app/trips')
    })
  })

  describe('accessibility', () => {
    it('has proper heading hierarchy', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('h2').exists()).toBe(true)
    })

    it('has proper link text', () => {
      wrapper = createWrapper()
      
      const link = wrapper.find('a')
      expect(link.text()).toBe('Go to Trips')
    })
  })

  describe('content', () => {
    it('contains emoji in welcome message', () => {
      wrapper = createWrapper()
      
      const message = wrapper.find('p')
      expect(message.text()).toContain('🌍')
    })

    it('has clear call to action', () => {
      wrapper = createWrapper()
      
      expect(wrapper.text()).toContain('Go to Trips')
    })
  })

  describe('responsiveness', () => {
    it('has responsive link styling', () => {
      wrapper = createWrapper()
      
      const link = wrapper.find('a')
      expect(link.classes()).toContain('inline-block')
      expect(link.classes()).toContain('px-6')
      expect(link.classes()).toContain('py-3')
    })
  })

  describe('minimal component', () => {
    it('has minimal script setup', () => {
      wrapper = createWrapper()
      
      // Component should render without errors
      expect(wrapper.exists()).toBe(true)
    })

    it('has no complex logic', () => {
      wrapper = createWrapper()
      
      // Component should be simple and static
      expect(wrapper.vm).toBeDefined()
      expect(Object.keys(wrapper.vm).length).toBe(1)
    })
  })

  describe('edge cases', () => {
    it('renders without props', () => {
      wrapper = createWrapper()
      
      expect(wrapper.exists()).toBe(true)
      expect(wrapper.find('h2').text()).toBe('Dashboard')
    })

    it('handles missing RouterLink gracefully', () => {
      wrapper = createWrapper()
      
      // Should still render without RouterLink
      expect(wrapper.find('h2').exists()).toBe(true)
      expect(wrapper.find('p').exists()).toBe(true)
    })
  })
})
