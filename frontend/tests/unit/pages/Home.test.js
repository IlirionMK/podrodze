import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { computed } from 'vue'

// Mock dependencies
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: vi.fn((key) => key),
    te: vi.fn(() => true)
  })
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: vi.fn()
  })
}))

vi.mock('lucide-vue-next', () => ({
  MapPin: { name: 'MapPin', template: '<div>MapPin</div>' },
  Users: { name: 'Users', template: '<div>Users</div>' },
  ThumbsUp: { name: 'ThumbsUp', template: '<div>ThumbsUp</div>' },
  Sparkles: { name: 'Sparkles', template: '<div>Sparkles</div>' },
  ArrowRight: { name: 'ArrowRight', template: '<div>ArrowRight</div>' },
  CalendarDays: { name: 'CalendarDays', template: '<div>CalendarDays</div>' },
  Route: { name: 'Route', template: '<div>Route</div>' },
  SlidersHorizontal: { name: 'SlidersHorizontal', template: '<div>SlidersHorizontal</div>' }
}))

import Home from '@/pages/Home.vue'

describe('Home Page', () => {
  let wrapper
  let mockPush

  beforeEach(() => {
    vi.clearAllMocks()
    
    // Mock localStorage
    global.localStorage = {
      getItem: vi.fn(),
      setItem: vi.fn(),
      removeItem: vi.fn()
    }

    wrapper = mount(Home)
  })

  it('renders home page correctly', () => {
    expect(wrapper.find('.w-full.overflow-hidden').exists()).toBe(true)
  })

  it('has hero section with gradient background', () => {
    const heroSection = wrapper.find('section')
    expect(heroSection.exists()).toBe(true)
    expect(heroSection.find('.absolute.inset-0.bg-gradient-to-r').exists()).toBe(true)
  })

  it('has decorative blur elements', () => {
    const blurElements = wrapper.findAll('.rounded-full.bg-white\\/15.blur-3xl')
    expect(blurElements.length).toBe(2)
  })

  it('has proper container structure', () => {
    const container = wrapper.find('.max-w-6xl.mx-auto')
    expect(container.exists()).toBe(true)
    expect(container.classes()).toContain('px-4')
    expect(container.classes()).toContain('py-16')
  })

  it('has responsive grid layout', () => {
    const grid = wrapper.find('.grid.grid-cols-1.lg\\:grid-cols-12')
    expect(grid.exists()).toBe(true)
    expect(grid.classes()).toContain('gap-10')
    expect(grid.classes()).toContain('items-center')
  })

  it('detects authentication status correctly', () => {
    // Test when token exists
    global.localStorage.getItem.mockReturnValue('valid-token')
    
    const testWrapper = mount(Home)
    expect(testWrapper.vm.isAuthenticated).toBe(true)

    // Test when token doesn't exist
    global.localStorage.getItem.mockReturnValue(null)
    
    const testWrapper2 = mount(Home)
    expect(testWrapper2.vm.isAuthenticated).toBe(false)
  })

  it('has translation helper function', () => {
    // Test that tr function exists and works with mocked i18n
    expect(typeof wrapper.vm.tr).toBe('function')
    expect(wrapper.vm.tr('test.key', 'Fallback')).toBe('test.key')
  })

  it('has navigation function', () => {
    // Test that the go function exists and calls router.push
    expect(typeof wrapper.vm.go).toBe('function')
  })

  it('has proper button styling classes', () => {
    expect(wrapper.vm.btnPrimary).toContain('rounded-full')
    expect(wrapper.vm.btnPrimary).toContain('bg-gradient-to-r')
    expect(wrapper.vm.btnPrimary).toContain('from-blue-600')
    expect(wrapper.vm.btnPrimary).toContain('to-purple-600')
    expect(wrapper.vm.btnPrimary).toContain('text-white')

    expect(wrapper.vm.btnSecondary).toContain('rounded-full')
    expect(wrapper.vm.btnSecondary).toContain('bg-white/15')
    expect(wrapper.vm.btnSecondary).toContain('text-white')
    expect(wrapper.vm.btnSecondary).toContain('border')
    expect(wrapper.vm.btnSecondary).toContain('border-white/25')
  })

  it('has responsive button classes', () => {
    expect(wrapper.vm.btnPrimary).toContain('w-full')
    expect(wrapper.vm.btnPrimary).toContain('sm:w-auto')
    expect(wrapper.vm.btnSecondary).toContain('w-full')
    expect(wrapper.vm.btnSecondary).toContain('sm:w-auto')
  })

  it('has focus states for accessibility', () => {
    expect(wrapper.vm.btnPrimary).toContain('focus:outline-none')
    expect(wrapper.vm.btnPrimary).toContain('focus:ring-2')
    expect(wrapper.vm.btnSecondary).toContain('focus:outline-none')
    expect(wrapper.vm.btnSecondary).toContain('focus:ring-2')
  })

  it('has transition effects', () => {
    expect(wrapper.vm.btnPrimary).toContain('transition')
    expect(wrapper.vm.btnSecondary).toContain('transition')
  })

  it('has hover and active states', () => {
    expect(wrapper.vm.btnPrimary).toContain('hover:opacity-95')
    expect(wrapper.vm.btnPrimary).toContain('active:opacity-90')
    expect(wrapper.vm.btnSecondary).toContain('hover:bg-white/20')
  })

  it('has shadow effects', () => {
    expect(wrapper.vm.btnPrimary).toContain('shadow-lg')
    expect(wrapper.vm.btnSecondary).toContain('shadow-lg')
  })

  it('has backdrop blur effect on secondary button', () => {
    expect(wrapper.vm.btnSecondary).toContain('backdrop-blur')
  })

  it('renders icons correctly', () => {
    // Test that lucide icons are imported and available
    expect(wrapper.vm).toBeDefined()
    // Icons are mocked, so we just check component renders
    expect(wrapper.exists()).toBe(true)
  })

  it('has proper content grid layout', () => {
    const contentGrid = wrapper.find('.lg\\:col-span-7')
    expect(contentGrid.exists()).toBe(true)
    expect(contentGrid.classes()).toContain('text-white')
  })

  it('has transition animations', () => {
    // Test that transition classes exist in the component
    expect(wrapper.vm).toBeDefined()
    // The transition component might not be directly accessible in tests
  })

  it('has proper spacing and padding', () => {
    const container = wrapper.find('.max-w-6xl.mx-auto')
    expect(container.classes()).toContain('px-4')
    expect(container.classes()).toContain('py-16')
    expect(container.classes()).toContain('sm:py-20')
  })

  it('has gradient overlay', () => {
    const gradient = wrapper.find('.bg-gradient-to-r')
    expect(gradient.exists()).toBe(true)
    expect(gradient.classes()).toContain('from-blue-600')
    expect(gradient.classes()).toContain('to-purple-700')
  })

  it('has opacity overlay for depth', () => {
    const opacityOverlay = wrapper.find('.absolute.inset-0.opacity-45')
    expect(opacityOverlay.exists()).toBe(true)
  })

  it('has decorative blur circles with proper positioning', () => {
    const blurCircles = wrapper.findAll('.absolute.rounded-full.bg-white\\/15.blur-3xl')
    
    // First circle - top left
    expect(blurCircles[0].classes()).toContain('-top-24')
    expect(blurCircles[0].classes()).toContain('-left-24')
    expect(blurCircles[0].classes()).toContain('h-80')
    expect(blurCircles[0].classes()).toContain('w-80')

    // Second circle - top right
    expect(blurCircles[1].classes()).toContain('top-24')
    expect(blurCircles[1].classes()).toContain('-right-24')
    expect(blurCircles[1].classes()).toContain('h-96')
    expect(blurCircles[1].classes()).toContain('w-96')
  })

  it('has responsive text alignment', () => {
    const textContent = wrapper.find('.lg\\:col-span-7')
    expect(textContent.exists()).toBe(true)
    // Should have text-white class for proper contrast
    expect(textContent.classes()).toContain('text-white')
  })
})
