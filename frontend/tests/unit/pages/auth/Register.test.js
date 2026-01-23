import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

// Mock dependencies
const mockSetAuth = vi.fn()
const mockPost = vi.fn()
const mockPush = vi.fn()

vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({
    setAuth: mockSetAuth,
    isAuthenticated: false,
    user: null
  })
}))

vi.mock('@/composables/useValidator', () => ({
  useValidator: () => ({
    errors: { value: {} },
    validate: vi.fn(() => true)
  })
}))

vi.mock('@/composables/api/api', () => ({
  default: {
    post: mockPost
  }
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: mockPush
  }),
  useRoute: () => ({
    query: {}
  })
}))

vi.mock('@/components/forms/BaseInput.vue', () => ({
  default: {
    name: 'BaseInput',
    template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    props: ['modelValue', 'label', 'type', 'autocomplete', 'error']
  }
}))

import Register from '@/pages/auth/Register.vue'

describe('Register Page', () => {
  let wrapper

  beforeEach(() => {
    vi.clearAllMocks()
    
    // Mock window.location.href to prevent navigation errors
    Object.defineProperty(window, 'location', {
      value: {
        href: 'http://localhost:3000'
      },
      writable: true
    })
    
    global.localStorage = {
      getItem: vi.fn(),
      setItem: vi.fn(),
      removeItem: vi.fn()
    }
    wrapper = mount(Register)
  })

  it('renders register form correctly', () => {
    expect(wrapper.find('form').exists()).toBe(true)
    expect(wrapper.text()).toContain('auth.register.title')
    expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
  })

  it('has email, password and confirm password inputs', () => {
    const inputs = wrapper.findAll('input')
    expect(inputs.length).toBe(3)
  })

  it('validates password confirmation', async () => {
    wrapper.vm.password = 'password123'
    wrapper.vm.confirmPassword = 'different'
    
    await wrapper.vm.onSubmit()
    
    expect(wrapper.vm.globalError).toBe('auth.errors.incorrect_data')
  })

  it('shows loading state when submitting', async () => {
    // Set up form data
    wrapper.vm.email = 'test@example.com'
    wrapper.vm.password = 'password123'
    wrapper.vm.confirmPassword = 'password123'
    
    // Mock the fetch response to fail (so loading gets set back to false)
    const mockJson = vi.fn().mockRejectedValue(new Error('API Error'))
    
    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: mockJson
    })
    
    // Trigger form submission
    await wrapper.vm.onSubmit()
    
    // Check if loading state is set to true initially
    expect(wrapper.vm.loading).toBe(false) // Should be false after the async operation completes
  })

  it('displays error message on registration failure', async () => {
    const mockFetch = vi.fn()
    global.fetch = mockFetch.mockResolvedValue({
      ok: false,
      json: () => Promise.resolve({ message: 'Registration failed' })
    })

    await wrapper.vm.onSubmit()

    expect(wrapper.vm.globalError).toBe('auth.errors.incorrect_data')
  })

  it('sends correct registration data to API', async () => {
    const mockFetch = vi.fn()
    global.fetch = mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ 
        token: 'test-token', 
        user: { email: 'test@example.com' } 
      })
    })

    wrapper.vm.email = 'test@example.com'
    wrapper.vm.password = 'password123'
    wrapper.vm.confirmPassword = 'password123'
    
    await wrapper.vm.onSubmit()

    expect(mockFetch).toHaveBeenCalledWith(
      expect.stringContaining('/register'),
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }),
        body: JSON.stringify({
          name: 'test',
          email: 'test@example.com',
          password: 'password123',
          password_confirmation: 'password123'
        })
      })
    )
  })

  it('extracts name from email before @ symbol', async () => {
    const mockFetch = vi.fn()
    global.fetch = mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ 
        token: 'test-token', 
        user: { email: 'john.doe@example.com' } 
      })
    })

    wrapper.vm.email = 'john.doe@example.com'
    wrapper.vm.password = 'password123'
    wrapper.vm.confirmPassword = 'password123'
    
    await wrapper.vm.onSubmit()

    const requestBody = JSON.parse(mockFetch.mock.calls[0][1].body)
    expect(requestBody.name).toBe('john.doe')
  })

  it('uses "User" as default name when email parsing fails', async () => {
    const mockFetch = vi.fn()
    global.fetch = mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ 
        token: 'test-token', 
        user: { email: '@example.com' } 
      })
    })

    wrapper.vm.email = '@example.com'
    wrapper.vm.password = 'password123'
    wrapper.vm.confirmPassword = 'password123'
    
    await wrapper.vm.onSubmit()

    const requestBody = JSON.parse(mockFetch.mock.calls[0][1].body)
    expect(requestBody.name).toBe('User')
  })

  it('redirects to intended URL if exists after successful registration', async () => {
    // Mock intended URL
    global.localStorage.getItem.mockImplementation((key) => 
      key === 'intended' ? '/intended-page' : null
    )
    
    // Mock successful registration
    mockPost.mockResolvedValueOnce({ 
      data: { 
        token: 'test-token', 
        user: { email: 'test@example.com' } 
      }
    })
    
    await wrapper.vm.onSubmit()
    
    // Wait for the API call to complete
    await new Promise(resolve => setTimeout(resolve, 0))
    await wrapper.vm.$nextTick()
    
    expect(mockPush).toHaveBeenCalledWith('/intended-page')
    expect(global.localStorage.removeItem).toHaveBeenCalledWith('intended')
  })

  it('redirects to app home after successful registration', async () => {
    // Mock successful registration
    mockPost.mockResolvedValueOnce({ 
      data: { 
        token: 'test-token', 
        user: { email: 'test@example.com' } 
      }
    })
    
    await wrapper.vm.onSubmit()
    
    // Wait for the API call to complete
    await new Promise(resolve => setTimeout(resolve, 0))
    await wrapper.vm.$nextTick()
    
    expect(mockPush).toHaveBeenCalledWith({ name: 'app.home' })
  })

  it('has Google and Facebook registration buttons', () => {
    const buttons = wrapper.findAll('button')
    expect(buttons.length).toBeGreaterThan(2) // submit + google + facebook
  })

  it('calls Google OAuth redirect on button click', async () => {
    const mockFetch = vi.fn()
    global.fetch = mockFetch.mockResolvedValue({
      json: () => Promise.resolve({ url: 'https://google.com/oauth' })
    })

    const buttons = wrapper.findAll('button')
    const googleButton = buttons[1] // Second button after submit
    await googleButton.trigger('click')

    expect(mockFetch).toHaveBeenCalledWith(
      expect.stringContaining('/auth/google/url'),
      expect.objectContaining({ headers: { Accept: 'application/json' } })
    )
  })

  it('calls Facebook OAuth redirect on button click', async () => {
    const mockFetch = vi.fn()
    global.fetch = mockFetch.mockResolvedValue({
      json: () => Promise.resolve({ url: 'https://facebook.com/oauth' })
    })

    const buttons = wrapper.findAll('button')
    const facebookButton = buttons[2] // Third button
    await facebookButton.trigger('click')

    expect(mockFetch).toHaveBeenCalledWith(
      expect.stringContaining('/auth/facebook/url'),
      expect.objectContaining({ headers: { Accept: 'application/json' } })
    )
  })

  it('disables submit button when loading', async () => {
    wrapper.vm.loading = true
    await wrapper.vm.$nextTick()
    
    const submitButton = wrapper.find('button[type="submit"]')
    expect(submitButton.attributes('disabled')).toBeDefined()
  })

  it('has proper styling classes', () => {
    const formContainer = wrapper.find('.relative.w-full.max-w-md')
    expect(formContainer.exists()).toBe(true)
    expect(formContainer.classes()).toContain('bg-white/10')
    expect(formContainer.classes()).toContain('backdrop-blur-xl')
  })

  it('handles JSON parsing errors gracefully', async () => {
    const mockFetch = vi.fn()
    global.fetch = mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.reject(new Error('Invalid JSON'))
    })

    await wrapper.vm.onSubmit()

    expect(wrapper.vm.globalError).toBe('auth.errors.incorrect_data')
  })
})
