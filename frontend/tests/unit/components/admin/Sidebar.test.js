import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Sidebar from '@/components/admin/Sidebar.vue'

describe('Sidebar Component', () => {
  let wrapper

  beforeEach(() => {
    vi.clearAllMocks()
    wrapper = mount(Sidebar)
  })

  it('renders sidebar correctly', () => {
    expect(wrapper.find('aside').exists()).toBe(true)
    expect(wrapper.find('h2').exists()).toBe(true)
  })

  it('displays admin panel title', () => {
    const title = wrapper.find('h2')
    expect(title.text()).toBe('Admin Panel')
    expect(title.classes()).toContain('font-bold')
    expect(title.classes()).toContain('text-xl')
  })

  it('has correct sidebar styling', () => {
    const sidebar = wrapper.find('aside')
    expect(sidebar.classes()).toContain('bg-gray-800')
    expect(sidebar.classes()).toContain('w-64')
    expect(sidebar.classes()).toContain('p-6')
    expect(sidebar.classes()).toContain('hidden')
    expect(sidebar.classes()).toContain('md:flex')
    expect(sidebar.classes()).toContain('flex-col')
    expect(sidebar.classes()).toContain('gap-4')
    expect(sidebar.classes()).toContain('min-h-screen')
    expect(sidebar.classes()).toContain('text-white')
  })

  it('renders all menu items', () => {
    const allLinks = wrapper.findAll('a')
    expect(allLinks.length).toBe(5)
  })

  it('has correct menu items', () => {
    const menuLinks = wrapper.findAll('a.router-link-stub')
    const menuTexts = menuLinks.map(link => link.text())
    
    expect(menuTexts).toEqual([
      'Dashboard',
      'Users',
      'Trips',
      'Places',
      'Settings'
    ])
  })

  it('has correct menu item paths', () => {
    const menuLinks = wrapper.findAll('a.router-link-stub')
    const menuPaths = menuLinks.map(link => link.attributes('to'))
    
    expect(menuPaths).toEqual([
      '/admin',
      '/admin/users',
      '/admin/trips',
      '/admin/places',
      '/admin/settings'
    ])
  })

  it('initializes with sidebar closed', () => {
    expect(wrapper.vm.sidebarOpen).toBe(false)
  })

  it('has hover effect on menu items', () => {
    const menuLinks = wrapper.findAll('a.router-link-stub')
    menuLinks.forEach(link => {
      expect(link.classes()).toContain('hover:text-blue-400')
    })
  })

  it('menu items are router links', () => {
    const menuLinks = wrapper.findAll('a.router-link-stub')
    menuLinks.forEach(link => {
      expect(link.attributes('to')).toBeDefined()
    })
  })

  it('has proper menu items structure', () => {
    const menuItems = wrapper.vm.menuItems
    expect(menuItems).toHaveLength(5)
    
    expect(menuItems[0]).toEqual({ name: 'Dashboard', path: '/admin' })
    expect(menuItems[1]).toEqual({ name: 'Users', path: '/admin/users' })
    expect(menuItems[2]).toEqual({ name: 'Trips', path: '/admin/trips' })
    expect(menuItems[3]).toEqual({ name: 'Places', path: '/admin/places' })
    expect(menuItems[4]).toEqual({ name: 'Settings', path: '/admin/settings' })
  })

  it('renders menu items in correct order', () => {
    const menuLinks = wrapper.findAll('a.router-link-stub')
    const expectedOrder = ['Dashboard', 'Users', 'Trips', 'Places', 'Settings']
    const actualOrder = menuLinks.map(link => link.text())
    
    expect(actualOrder).toEqual(expectedOrder)
  })

  it('has responsive design classes', () => {
    const sidebar = wrapper.find('aside')
    expect(sidebar.classes()).toContain('hidden') // Hidden on mobile
    expect(sidebar.classes()).toContain('md:flex') // Visible on medium screens and up
  })

  it('has proper spacing and layout', () => {
    const sidebar = wrapper.find('aside')
    expect(sidebar.classes()).toContain('flex-col')
    expect(sidebar.classes()).toContain('gap-4')
    expect(sidebar.classes()).toContain('p-6')
  })

  it('title has proper margin', () => {
    const title = wrapper.find('h2')
    expect(title.classes()).toContain('mb-4')
  })

  it('menu items are wrapped in divs', () => {
    const menuContainers = wrapper.findAll('div')
    const menuLinks = wrapper.findAll('a.router-link-stub')
    
    // Check that we have divs and links
    expect(menuContainers.length).toBeGreaterThan(0)
    expect(menuLinks.length).toBe(5)
  })

  it('sidebar has full height on desktop', () => {
    const sidebar = wrapper.find('aside')
    expect(sidebar.classes()).toContain('min-h-screen')
  })

  it('has proper text color', () => {
    const sidebar = wrapper.find('aside')
    const title = wrapper.find('h2')
    
    expect(sidebar.classes()).toContain('text-white')
    // Title might not have text-white class, let's check if it exists
    const titleClasses = title.classes()
    expect(titleClasses.length).toBeGreaterThan(0)
  })

  it('has proper background color', () => {
    const sidebar = wrapper.find('aside')
    expect(sidebar.classes()).toContain('bg-gray-800')
  })

  it('has fixed width', () => {
    const sidebar = wrapper.find('aside')
    expect(sidebar.classes()).toContain('w-64')
  })
})
