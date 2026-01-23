import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Footer from '@/components/Footer.vue'

describe('Footer', () => {
  it('renders footer structure', () => {
    const wrapper = mount(Footer)
    
    expect(wrapper.find('footer').exists()).toBe(true)
    expect(wrapper.find('.max-w-6xl').exists()).toBe(true)
  })

  it('displays brand name and logo', () => {
    const wrapper = mount(Footer)
    
    expect(wrapper.text()).toContain('PoDrodze')
    expect(wrapper.find('footer').classes()).toContain('bg-[#0d1117]')
  })

  it('shows current year in copyright', () => {
    const wrapper = mount(Footer)
    const currentYear = new Date().getFullYear()
    
    expect(wrapper.text()).toContain(`© ${currentYear} PoDrodze`)
  })

  it('has proper footer sections', () => {
    const wrapper = mount(Footer)
    
    // Check for section headers
    expect(wrapper.text()).toContain('footer.section.project')
    expect(wrapper.text()).toContain('footer.section.team')
  })

  it('has proper grid layout', () => {
    const wrapper = mount(Footer)
    
    const gridContainer = wrapper.find('.grid')
    expect(gridContainer.classes()).toContain('grid-cols-1')
    expect(gridContainer.classes()).toContain('md:grid-cols-3')
  })

  it('displays footer links', () => {
    const wrapper = mount(Footer)
    
    expect(wrapper.text()).toContain('footer.links.about_us')
    expect(wrapper.text()).toContain('footer.links.contact')
  })

  it('has proper styling classes', () => {
    const wrapper = mount(Footer)
    
    const footer = wrapper.find('footer')
    expect(footer.classes()).toContain('text-gray-300')
    expect(footer.classes()).toContain('mt-20')
    expect(footer.classes()).toContain('border-t')
  })
})
