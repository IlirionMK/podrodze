import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import StatsCard from '@/components/admin/StatsCard.vue'

describe('StatsCard Component', () => {
  let wrapper

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders stats card with default props', () => {
    wrapper = mount(StatsCard, {
      props: {
        title: 'Total Users',
        value: 1234,
        icon: '👥'
      }
    })

    expect(wrapper.find('.bg-gray-700').exists()).toBe(true)
    expect(wrapper.text()).toContain('Total Users')
    expect(wrapper.text()).toContain('1234')
    expect(wrapper.text()).toContain('👥')
  })

  it('displays title correctly', () => {
    wrapper = mount(StatsCard, {
      props: {
        title: 'Active Trips',
        value: 42,
        icon: '🚗'
      }
    })

    const titleElement = wrapper.find('.text-gray-300')
    expect(titleElement.text()).toBe('Active Trips')
  })

  it('displays value correctly with number', () => {
    wrapper = mount(StatsCard, {
      props: {
        title: 'Total Revenue',
        value: 9876,
        icon: '💰'
      }
    })

    const valueElement = wrapper.find('.text-white.text-2xl')
    expect(valueElement.text()).toBe('9876')
  })

  it('displays value correctly with string', () => {
    wrapper = mount(StatsCard, {
      props: {
        title: 'Status',
        value: 'Active',
        icon: '✅'
      }
    })

    const valueElement = wrapper.find('.text-white.text-2xl')
    expect(valueElement.text()).toBe('Active')
  })

  it('displays icon correctly', () => {
    wrapper = mount(StatsCard, {
      props: {
        title: 'Test',
        value: 1,
        icon: '🧪'
      }
    })

    const iconElement = wrapper.find('.text-3xl')
    expect(iconElement.text()).toBe('🧪')
  })

  it('has proper styling classes', () => {
    wrapper = mount(StatsCard, {
      props: {
        title: 'Test',
        value: 1,
        icon: '🧪'
      }
    })

    const card = wrapper.find('.bg-gray-700')
    expect(card.classes()).toContain('rounded-lg')
    expect(card.classes()).toContain('p-6')
    expect(card.classes()).toContain('shadow')
    expect(card.classes()).toContain('flex')
    expect(card.classes()).toContain('items-center')
    expect(card.classes()).toContain('gap-4')
  })

  it('has proper title styling', () => {
    wrapper = mount(StatsCard, {
      props: {
        title: 'Test Title',
        value: 1,
        icon: '🧪'
      }
    })

    const titleElement = wrapper.find('.text-gray-300')
    expect(titleElement.classes()).toContain('text-sm')
  })

  it('has proper value styling', () => {
    wrapper = mount(StatsCard, {
      props: {
        title: 'Test Title',
        value: 100,
        icon: '🧪'
      }
    })

    const valueElement = wrapper.find('.text-white.text-2xl')
    expect(valueElement.classes()).toContain('font-bold')
  })

  it('renders icon and content in separate containers', () => {
    wrapper = mount(StatsCard, {
      props: {
        title: 'Test',
        value: 1,
        icon: '🧪'
      }
    })

    const iconContainer = wrapper.find('.text-3xl')
    const contentContainer = wrapper.find('.flex.items-center.gap-4 > div:last-child')
    
    expect(iconContainer.exists()).toBe(true)
    expect(contentContainer.exists()).toBe(true)
  })

  it('handles empty values', () => {
    wrapper = mount(StatsCard, {
      props: {
        title: 'Empty Test',
        value: '',
        icon: '📝'
      }
    })

    const valueElement = wrapper.find('.text-white.text-2xl')
    expect(valueElement.text()).toBe('')
  })

  it('handles zero values', () => {
    wrapper = mount(StatsCard, {
      props: {
        title: 'Zero Test',
        value: 0,
        icon: '0️⃣'
      }
    })

    const valueElement = wrapper.find('.text-white.text-2xl')
    expect(valueElement.text()).toBe('0')
  })
})
