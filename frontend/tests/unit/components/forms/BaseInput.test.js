import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseInput from '@/components/forms/BaseInput.vue'

describe('BaseInput Component', () => {
  let wrapper

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders input with default props', () => {
    wrapper = mount(BaseInput)
    
    expect(wrapper.find('input').exists()).toBe(true)
    expect(wrapper.find('input').attributes('type')).toBe('text')
    expect(wrapper.find('input').attributes('value')).toBe('')
  })

  it('renders with label when provided', () => {
    wrapper = mount(BaseInput, {
      props: {
        label: 'Email Address'
      }
    })

    const label = wrapper.find('label')
    expect(label.exists()).toBe(true)
    expect(label.text()).toBe('Email Address')
  })

  it('does not render label when not provided', () => {
    wrapper = mount(BaseInput)
    
    expect(wrapper.find('label').exists()).toBe(false)
  })

  it('renders with correct input type', () => {
    wrapper = mount(BaseInput, {
      props: {
        type: 'email'
      }
    })

    expect(wrapper.find('input').attributes('type')).toBe('email')
  })

  it('renders password input with toggle button', () => {
    wrapper = mount(BaseInput, {
      props: {
        type: 'password'
      }
    })

    expect(wrapper.find('input').attributes('type')).toBe('password')
    expect(wrapper.find('button').exists()).toBe(true)
  })

  it('toggles password visibility', async () => {
    wrapper = mount(BaseInput, {
      props: {
        type: 'password'
      }
    })

    const button = wrapper.find('button')
    const input = wrapper.find('input')

    // Initially password should be hidden
    expect(input.attributes('type')).toBe('password')

    // Click to show password
    await button.trigger('click')
    expect(input.attributes('type')).toBe('text')

    // Click again to hide password
    await button.trigger('click')
    expect(input.attributes('type')).toBe('password')
  })

  it('emits update:modelValue when input changes', async () => {
    wrapper = mount(BaseInput, {
      props: {
        modelValue: 'initial'
      }
    })

    const input = wrapper.find('input')
    await input.setValue('new value')

    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['new value'])
  })

  it('displays error message when error prop is provided', () => {
    wrapper = mount(BaseInput, {
      props: {
        error: 'This field is required'
      }
    })

    const errorElement = wrapper.find('.text-red-300')
    expect(errorElement.exists()).toBe(true)
    expect(errorElement.text()).toBe('This field is required')
  })

  it('applies error styling when error is present', () => {
    wrapper = mount(BaseInput, {
      props: {
        label: 'Test Label',
        error: 'Error message'
      }
    })

    const input = wrapper.find('input')
    const label = wrapper.find('label')

    expect(input.classes()).toContain('border-red-400')
    expect(label.classes()).toContain('text-red-300')
  })

  it('applies normal styling when no error', () => {
    wrapper = mount(BaseInput, {
      props: {
        label: 'Test Label'
      }
    })

    const input = wrapper.find('input')
    const label = wrapper.find('label')

    expect(input.classes()).not.toContain('border-red-400')
    expect(label.classes()).toContain('text-white/90')
  })

  it('sets placeholder when provided', () => {
    wrapper = mount(BaseInput, {
      props: {
        placeholder: 'Enter your email'
      }
    })

    expect(wrapper.find('input').attributes('placeholder')).toBe('Enter your email')
  })

  it('sets autocomplete when provided', () => {
    wrapper = mount(BaseInput, {
      props: {
        autocomplete: 'email'
      }
    })

    expect(wrapper.find('input').attributes('autocomplete')).toBe('email')
  })

  it('uses default autocomplete when not provided', () => {
    wrapper = mount(BaseInput)
    
    expect(wrapper.find('input').attributes('autocomplete')).toBe('off')
  })

  it('handles numeric modelValue', () => {
    wrapper = mount(BaseInput, {
      props: {
        modelValue: 123
      }
    })

    expect(wrapper.find('input').attributes('value')).toBe('123')
  })

  it('has proper input styling classes', () => {
    wrapper = mount(BaseInput)

    const input = wrapper.find('input')
    expect(input.classes()).toContain('w-full')
    expect(input.classes()).toContain('rounded-lg')
    expect(input.classes()).toContain('px-3')
    expect(input.classes()).toContain('py-2')
    expect(input.classes()).toContain('pr-10')
    expect(input.classes()).toContain('transition')
    expect(input.classes()).toContain('border')
    expect(input.classes()).toContain('bg-white/10')
    expect(input.classes()).toContain('backdrop-blur-xl')
    expect(input.classes()).toContain('text-white')
    expect(input.classes()).toContain('placeholder-white/40')
    expect(input.classes()).toContain('border-white/30')
  })

  it('has focus styling classes', () => {
    wrapper = mount(BaseInput)

    const input = wrapper.find('input')
    expect(input.classes()).toContain('focus:ring-2')
    expect(input.classes()).toContain('focus:ring-blue-300')
    expect(input.classes()).toContain('focus:border-blue-400')
  })

  it('has disabled styling classes', () => {
    wrapper = mount(BaseInput)

    const input = wrapper.find('input')
    expect(input.classes()).toContain('disabled:opacity-70')
  })

  it('sets aria-invalid when error is present', () => {
    wrapper = mount(BaseInput, {
      props: {
        error: 'Error message'
      }
    })

    expect(wrapper.find('input').attributes('aria-invalid')).toBe('true')
  })

  it('sets aria-invalid to false when no error', () => {
    wrapper = mount(BaseInput)

    expect(wrapper.find('input').attributes('aria-invalid')).toBe('false')
  })

  it('does not show password toggle for non-password inputs', () => {
    wrapper = mount(BaseInput, {
      props: {
        type: 'text'
      }
    })

    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('has proper container structure', () => {
    wrapper = mount(BaseInput, {
      props: {
        label: 'Test Label',
        error: 'Test Error'
      }
    })

    const container = wrapper.find('.flex.flex-col.gap-1')
    expect(container.exists()).toBe(true)
    expect(container.find('label').exists()).toBe(true)
    expect(container.find('.relative').exists()).toBe(true)
    expect(container.find('.text-red-300').exists()).toBe(true)
  })

  it('password toggle button has proper styling', () => {
    wrapper = mount(BaseInput, {
      props: {
        type: 'password'
      }
    })

    const button = wrapper.find('button')
    expect(button.classes()).toContain('absolute')
    expect(button.classes()).toContain('right-3')
    expect(button.classes()).toContain('top-1/2')
    expect(button.classes()).toContain('-translate-y-1/2')
    expect(button.classes()).toContain('text-white/60')
    expect(button.classes()).toContain('hover:text-white')
  })

  it('shows correct icon based on password visibility', async () => {
    wrapper = mount(BaseInput, {
      props: {
        type: 'password'
      }
    })

    const button = wrapper.find('button')
    
    // Initially should show eye icon (password hidden)
    expect(wrapper.find('svg').exists()).toBe(true)
    
    // After click should show eye-slash icon (password visible)
    await button.trigger('click')
    expect(wrapper.findAll('svg').length).toBe(1) // Still one SVG, but different one
  })
})
