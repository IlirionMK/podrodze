import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Topbar from '@/components/admin/Topbar.vue'

// Mock vue-router
vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: vi.fn()
  })
}))

describe('Topbar Component', () => {
  let wrapper
  let mockRouter

  beforeEach(() => {
    vi.clearAllMocks()
    
    // Mock localStorage
    const localStorageMock = {
      removeItem: vi.fn(),
      getItem: vi.fn(),
      setItem: vi.fn()
    }
    global.localStorage = localStorageMock
    
    // Mock router
    mockRouter = { push: vi.fn() }
    vi.doMock('vue-router', () => ({
      useRouter: () => mockRouter
    }))
    
    wrapper = mount(Topbar, {
      global: {
        mocks: {
          $router: mockRouter
        }
      }
    })
  })

  it('renders topbar correctly', () => {
    expect(wrapper.find('header').exists()).toBe(true)
    expect(wrapper.find('h1').exists()).toBe(true)
    expect(wrapper.find('button').exists()).toBe(true)
  })

  it('displays admin panel title', () => {
    const title = wrapper.find('h1')
    expect(title.text()).toBe('Admin Panel')
    expect(title.classes()).toContain('text-xl')
    expect(title.classes()).toContain('font-bold')
  })

  it('has logout button', () => {
    const button = wrapper.find('button')
    expect(button.exists()).toBe(true)
    expect(button.text()).toBe('Logout')
  })

  it('has proper header styling', () => {
    const header = wrapper.find('header')
    expect(header.classes()).toContain('bg-gray-900')
    expect(header.classes()).toContain('flex')
    expect(header.classes()).toContain('justify-between')
    expect(header.classes()).toContain('items-center')
    expect(header.classes()).toContain('p-4')
    expect(header.classes()).toContain('shadow-md')
    expect(header.classes()).toContain('text-white')
  })

  it('has proper button styling', () => {
    const button = wrapper.find('button')
    expect(button.classes()).toContain('bg-red-500')
    expect(button.classes()).toContain('hover:bg-red-600')
    expect(button.classes()).toContain('px-4')
    expect(button.classes()).toContain('py-2')
    expect(button.classes()).toContain('rounded')
  })

  it('has proper title styling', () => {
    const title = wrapper.find('h1')
    expect(title.classes()).toContain('text-xl')
    expect(title.classes()).toContain('font-bold')
  })

  it('calls logout function when button is clicked', async () => {
    const button = wrapper.find('button')
    await button.trigger('click')
    
    // Check if localStorage.removeItem was called for token and role
    expect(global.localStorage.removeItem).toHaveBeenCalledWith('token')
    expect(global.localStorage.removeItem).toHaveBeenCalledWith('role')
  })

  it('has proper layout structure', () => {
      const header = wrapper.find('header')
      expect(header.find('h1').exists()).toBe(true)
      expect(header.find('button').exists()).toBe(true)
      
      // Check that h1 and button are direct children of header
      const children = header.element.children
      expect(children.length).toBe(2)
    })

  it('maintains responsive design', () => {
    const header = wrapper.find('header')
    expect(header.classes()).toContain('flex')
    expect(header.classes()).toContain('justify-between')
    expect(header.classes()).toContain('items-center')
  })

  it('has proper text color', () => {
    const header = wrapper.find('header')
    const title = wrapper.find('h1')
    
    expect(header.classes()).toContain('text-white')
    expect(title.classes()).not.toContain('text-white') // Title inherits from header
  })
})
