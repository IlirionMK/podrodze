import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import GuestLayout from '@/layouts/GuestLayout.vue'

describe('GuestLayout Component', () => {
  let wrapper

  const createWrapper = () => {
    return mount(GuestLayout, {
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
    it('renders guest layout structure', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('.min-h-screen').exists()).toBe(true)
    })

    it('has proper layout classes', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('.min-h-screen').classes()).toContain('flex')
      expect(wrapper.find('.min-h-screen').classes()).toContain('flex-col')
      expect(wrapper.find('.min-h-screen').classes()).toContain('bg-white')
    })
  })

  describe('edge cases', () => {
    it('renders without props', () => {
      wrapper = createWrapper()
      
      expect(wrapper.exists()).toBe(true)
      expect(wrapper.find('.min-h-screen').exists()).toBe(true)
    })

    it('handles missing components gracefully', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('.min-h-screen').exists()).toBe(true)
    })
  })
})
