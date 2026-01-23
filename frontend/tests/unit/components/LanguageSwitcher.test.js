import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'

describe('LanguageSwitcher', () => {
  beforeEach(() => {
    // Clear localStorage mock before each test
    vi.clearAllMocks()
  })

  it('renders language buttons', () => {
    const wrapper = mount(LanguageSwitcher)
    
    expect(wrapper.find('button').text()).toBe('PL')
    expect(wrapper.findAll('button')[1].text()).toBe('EN')
  })

  it('highlights active language', () => {
    const wrapper = mount(LanguageSwitcher)
    
    const buttons = wrapper.findAll('button')
    expect(buttons[0].classes()).toContain('bg-blue-600')
    expect(buttons[0].classes()).toContain('text-white')
    expect(buttons[1].classes()).toContain('bg-white')
    expect(buttons[1].classes()).toContain('text-gray-700')
  })

  it('changes language when button is clicked', async () => {
    const wrapper = mount(LanguageSwitcher)
    
    const enButton = wrapper.findAll('button')[1]
    await enButton.trigger('click')
    
    expect(global.localStorage.setItem).toHaveBeenCalledWith('lang', 'en')
  })

  it('does not change language when same language is clicked', async () => {
    const wrapper = mount(LanguageSwitcher)
    
    const plButton = wrapper.find('button')
    await plButton.trigger('click')
    
    // Should not call setItem when same language is clicked
    expect(global.localStorage.setItem).not.toHaveBeenCalled()
  })

  it('applies correct hover states', () => {
    const wrapper = mount(LanguageSwitcher)
    
    const buttons = wrapper.findAll('button')
    // Check if buttons have hover classes - verify they have transition and hover effects
    expect(buttons[0].attributes('class')).toContain('transition')
    expect(buttons[1].attributes('class')).toContain('transition')
    // At least one should have hover:bg-gray-100 (the inactive one)
    const hasGrayHover = buttons.some(btn => 
      btn.attributes('class').includes('hover:bg-gray-100')
    )
    expect(hasGrayHover).toBe(true)
  })
})
