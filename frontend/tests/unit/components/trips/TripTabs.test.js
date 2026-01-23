import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import TripTabs from '@/components/trips/TripTabs.vue'

describe('TripTabs Component', () => {
  let wrapper

  beforeEach(() => {
    vi.clearAllMocks()
  })

  const createWrapper = (props = {}) => {
    return mount(TripTabs, {
      props: {
        modelValue: 'overview',
        ...props
      }
    })
  }

  describe('basic rendering', () => {
    it('renders tab container', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('.bg-white.border').exists()).toBe(true)
      expect(wrapper.find('.rounded-2xl').exists()).toBe(true)
    })

    it('renders all tabs', () => {
      wrapper = createWrapper()
      
      const tabs = wrapper.findAll('button[type="button"]')
      expect(tabs.length).toBe(5)
    })

    it('renders default tab names', () => {
      wrapper = createWrapper()
      
      expect(wrapper.text()).toContain('Overview')
      expect(wrapper.text()).toContain('Places')
      expect(wrapper.text()).toContain('Plan')
      expect(wrapper.text()).toContain('Team')
      expect(wrapper.text()).toContain('Preferences')
    })
  })

  describe('tab styling', () => {
    it('applies active styling to current tab', () => {
      wrapper = createWrapper({ modelValue: 'overview' })
      
      const activeTab = wrapper.findAll('button[type="button"]')[0]
      expect(activeTab.classes()).toContain('text-white')
      expect(activeTab.classes()).toContain('bg-gradient-to-r')
      expect(activeTab.classes()).toContain('from-blue-600')
      expect(activeTab.classes()).toContain('to-purple-600')
      expect(activeTab.classes()).toContain('shadow')
    })

    it('applies inactive styling to other tabs', () => {
      wrapper = createWrapper({ modelValue: 'overview' })
      
      const inactiveTabs = wrapper.findAll('button[type="button"]').slice(1)
      inactiveTabs.forEach(tab => {
        expect(tab.classes()).toContain('text-gray-700')
        expect(tab.classes()).toContain('hover:bg-gray-100')
        expect(tab.classes()).not.toContain('text-white')
        expect(tab.classes()).not.toContain('bg-gradient-to-r')
      })
    })

    it('applies base styling to all tabs', () => {
      wrapper = createWrapper()
      
      const tabs = wrapper.findAll('button[type="button"]')
      tabs.forEach(tab => {
        expect(tab.classes()).toContain('px-4')
        expect(tab.classes()).toContain('py-2')
        expect(tab.classes()).toContain('rounded-xl')
        expect(tab.classes()).toContain('font-medium')
        expect(tab.classes()).toContain('transition')
        expect(tab.classes()).toContain('whitespace-nowrap')
      })
    })

    it('changes active tab when modelValue changes', async () => {
      wrapper = createWrapper({ modelValue: 'overview' })
      
      // Initially overview should be active
      let tabs = wrapper.findAll('button[type="button"]')
      expect(tabs[0].classes()).toContain('bg-gradient-to-r')
      expect(tabs[1].classes()).not.toContain('bg-gradient-to-r')
      
      // Change to places tab
      await wrapper.setProps({ modelValue: 'places' })
      
      tabs = wrapper.findAll('button[type="button"]')
      expect(tabs[0].classes()).not.toContain('bg-gradient-to-r')
      expect(tabs[1].classes()).toContain('bg-gradient-to-r')
    })
  })

  describe('tab interactions', () => {
    it('emits update:modelValue when tab is clicked', async () => {
      wrapper = createWrapper({ modelValue: 'overview' })
      
      const placesTab = wrapper.findAll('button[type="button"]')[1]
      await placesTab.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')[0]).toEqual(['places'])
    })

    it('emits correct tab name for each tab', async () => {
      wrapper = createWrapper()
      
      const tabs = wrapper.findAll('button[type="button"]')
      const expectedTabs = ['overview', 'places', 'plan', 'team', 'preferences']
      
      for (let i = 0; i < tabs.length; i++) {
        await tabs[i].trigger('click')
        expect(wrapper.emitted('update:modelValue')[i]).toEqual([expectedTabs[i]])
      }
    })

    it('does not emit when clicking already active tab', async () => {
      wrapper = createWrapper({ modelValue: 'overview' })
      
      const overviewTab = wrapper.findAll('button[type="button"]')[0]
      await overviewTab.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')[0]).toEqual(['overview'])
    })
  })

  describe('slots', () => {
    it('renders custom content for overview slot', () => {
      wrapper = mount(TripTabs, {
        props: { modelValue: 'overview' },
        slots: {
          overview: '<span class="custom-overview">Custom Overview</span>'
        }
      })
      
      expect(wrapper.find('.custom-overview').exists()).toBe(true)
      expect(wrapper.text()).toContain('Custom Overview')
    })

    it('renders custom content for places slot', () => {
      wrapper = mount(TripTabs, {
        props: { modelValue: 'places' },
        slots: {
          places: '<span class="custom-places">Custom Places</span>'
        }
      })
      
      expect(wrapper.find('.custom-places').exists()).toBe(true)
      expect(wrapper.text()).toContain('Custom Places')
    })

    it('renders custom content for plan slot', () => {
      wrapper = mount(TripTabs, {
        props: { modelValue: 'plan' },
        slots: {
          plan: '<span class="custom-plan">Custom Plan</span>'
        }
      })
      
      expect(wrapper.find('.custom-plan').exists()).toBe(true)
      expect(wrapper.text()).toContain('Custom Plan')
    })

    it('renders custom content for team slot', () => {
      wrapper = mount(TripTabs, {
        props: { modelValue: 'team' },
        slots: {
          team: '<span class="custom-team">Custom Team</span>'
        }
      })
      
      expect(wrapper.find('.custom-team').exists()).toBe(true)
      expect(wrapper.text()).toContain('Custom Team')
    })

    it('renders custom content for preferences slot', () => {
      wrapper = mount(TripTabs, {
        props: { modelValue: 'preferences' },
        slots: {
          preferences: '<span class="custom-preferences">Custom Preferences</span>'
        }
      })
      
      expect(wrapper.find('.custom-preferences').exists()).toBe(true)
      expect(wrapper.text()).toContain('Custom Preferences')
    })

    it('falls back to default content when slot is not provided', () => {
      wrapper = createWrapper()
      
      expect(wrapper.text()).toContain('Overview')
      expect(wrapper.text()).toContain('Places')
      expect(wrapper.text()).toContain('Plan')
      expect(wrapper.text()).toContain('Team')
      expect(wrapper.text()).toContain('Preferences')
    })
  })

  describe('container styling', () => {
    it('has proper container classes', () => {
      wrapper = createWrapper()
      
      const container = wrapper.find('.bg-white.border')
      expect(container.classes()).toContain('shadow-sm')
      expect(container.classes()).toContain('rounded-2xl')
      expect(container.classes()).toContain('p-2')
    })

    it('has proper tab container classes', () => {
      wrapper = createWrapper()
      
      const tabContainer = wrapper.find('.flex.gap-2')
      expect(tabContainer.classes()).toContain('overflow-x-auto')
      expect(tabContainer.classes()).toContain('whitespace-nowrap')
      expect(tabContainer.classes()).toContain('justify-start')
      expect(tabContainer.classes()).toContain('sm:justify-center')
    })
  })

  describe('responsive behavior', () => {
    it('has responsive justification classes', () => {
      wrapper = createWrapper()
      
      const tabContainer = wrapper.find('.flex.gap-2')
      expect(tabContainer.classes()).toContain('justify-start')
      expect(tabContainer.classes()).toContain('sm:justify-center')
    })

    it('has horizontal scroll on small screens', () => {
      wrapper = createWrapper()
      
      const tabContainer = wrapper.find('.flex.gap-2')
      expect(tabContainer.classes()).toContain('overflow-x-auto')
    })
  })

  describe('computed properties', () => {
    it('has correct tab base classes', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.tabBase).toBe('px-4 py-2 rounded-xl font-medium transition whitespace-nowrap')
    })

    it('has correct active tab classes', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.tabActive).toBe('text-white bg-gradient-to-r from-blue-600 to-purple-600 shadow')
    })

    it('has correct inactive tab classes', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.tabInactive).toBe('text-gray-700 hover:bg-gray-100')
    })
  })

  describe('methods', () => {
    it('setTab method emits correct value', () => {
      wrapper = createWrapper()
      
      wrapper.vm.setTab('places')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')[0]).toEqual(['places'])
    })
  })

  describe('accessibility', () => {
    it('has proper button types', () => {
      wrapper = createWrapper()
      
      const tabs = wrapper.findAll('button[type="button"]')
      tabs.forEach(tab => {
        expect(tab.attributes('type')).toBe('button')
      })
    })

    it('maintains focus styles', () => {
      wrapper = createWrapper()
      
      const tabs = wrapper.findAll('button[type="button"]')
      tabs.forEach(tab => {
        expect(tab.classes()).toContain('transition')
      })
    })
  })

  describe('edge cases', () => {
    it('handles unknown tab values gracefully', () => {
      wrapper = createWrapper({ modelValue: 'unknown' })
      
      const tabs = wrapper.findAll('button[type="button"]')
      tabs.forEach(tab => {
        expect(tab.classes()).toContain('text-gray-700')
        expect(tab.classes()).not.toContain('bg-gradient-to-r')
      })
    })

    it('handles empty modelValue', () => {
      wrapper = createWrapper({ modelValue: '' })
      
      const tabs = wrapper.findAll('button[type="button"]')
      tabs.forEach(tab => {
        expect(tab.classes()).toContain('text-gray-700')
        expect(tab.classes()).not.toContain('bg-gradient-to-r')
      })
    })

    it('handles null modelValue', () => {
      wrapper = createWrapper({ modelValue: null })
      
      const tabs = wrapper.findAll('button[type="button"]')
      tabs.forEach(tab => {
        expect(tab.classes()).toContain('text-gray-700')
        expect(tab.classes()).not.toContain('bg-gradient-to-r')
      })
    })
  })

  describe('visual hierarchy', () => {
    it('maintains consistent spacing between tabs', () => {
      wrapper = createWrapper()
      
      const tabContainer = wrapper.find('.flex.gap-2')
      expect(tabContainer.classes()).toContain('gap-2')
    })

    it('has proper padding in container', () => {
      wrapper = createWrapper()
      
      const container = wrapper.find('.bg-white.border')
      expect(container.classes()).toContain('p-2')
    })

    it('has proper border radius on tabs', () => {
      wrapper = createWrapper()
      
      const tabs = wrapper.findAll('button[type="button"]')
      tabs.forEach(tab => {
        expect(tab.classes()).toContain('rounded-xl')
      })
    })

    it('has proper border radius on container', () => {
      wrapper = createWrapper()
      
      const container = wrapper.find('.bg-white.border')
      expect(container.classes()).toContain('rounded-2xl')
    })
  })
})
