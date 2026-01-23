import { describe, it, expect, beforeEach } from 'vitest'
import { ref } from 'vue'
import { useValidator } from '@/composables/useValidator'

describe('useValidator', () => {
  let validator

  beforeEach(() => {
    validator = useValidator()
  })

  it('initializes with empty errors', () => {
    expect(validator.errors.value).toEqual({})
  })

  it('returns true when no validation rules are provided', () => {
    const result = validator.validate({})
    expect(result).toBe(true)
    expect(validator.errors.value).toEqual({})
  })

  it('validates required fields correctly', () => {
    const fields = {
      email: {
        value: '',
        required: true,
        messages: { required: 'Email is required' }
      }
    }

    const result = validator.validate(fields)
    
    expect(result).toBe(false)
    expect(validator.errors.value.email).toBe('Email is required')
  })

  it('passes validation for non-empty required fields', () => {
    const fields = {
      email: {
        value: 'test@example.com',
        required: true,
        messages: { required: 'Email is required' }
      }
    }

    const result = validator.validate(fields)
    
    expect(result).toBe(true)
    expect(validator.errors.value.email).toBeUndefined()
  })

  it('validates email format correctly', () => {
    const fields = {
      email: {
        value: 'invalid-email',
        required: true,
        email: true,
        messages: { 
          required: 'Email is required',
          email: 'Invalid email format'
        }
      }
    }

    const result = validator.validate(fields)
    
    expect(result).toBe(false)
    expect(validator.errors.value.email).toBe('Invalid email format')
  })

  it('accepts valid email formats', () => {
    const validEmails = [
      'test@example.com',
      'user.name@domain.co.uk',
      'user+tag@example.org',
      'user123@test-domain.com'
    ]

    validEmails.forEach(email => {
      const fields = {
        email: {
          value: email,
          required: true,
          email: true,
          messages: { 
            required: 'Email is required',
            email: 'Invalid email format'
          }
        }
      }

      const result = validator.validate(fields)
      expect(result).toBe(true)
      expect(validator.errors.value.email).toBeUndefined()
    })
  })

  it('rejects invalid email formats', () => {
    const invalidEmails = [
      'invalid-email',
      '@example.com',
      'test@',
      'test.example.com',
      'test@.com',
      'test@com',
      '',
      'test space@example.com'
    ]

    invalidEmails.forEach(email => {
      const fields = {
        email: {
          value: email,
          required: true,
          email: true,
          messages: { 
            required: 'Email is required',
            email: 'Invalid email format'
          }
        }
      }

      const result = validator.validate(fields)
      expect(result).toBe(false)
      // Should fail on required check first for empty emails
      if (email === '') {
        expect(validator.errors.value.email).toBe('Email is required')
      } else {
        expect(validator.errors.value.email).toBe('Invalid email format')
      }
    })
  })

  it('validates minimum length correctly', () => {
    const fields = {
      password: {
        value: '123',
        required: true,
        min: 6,
        messages: { 
          required: 'Password is required',
          min: 'Password must be at least 6 characters'
        }
      }
    }

    const result = validator.validate(fields)
    
    expect(result).toBe(false)
    expect(validator.errors.value.password).toBe('Password must be at least 6 characters')
  })

  it('passes validation for fields meeting minimum length', () => {
    const fields = {
      password: {
        value: 'password123',
        required: true,
        min: 6,
        messages: { 
          required: 'Password is required',
          min: 'Password must be at least 6 characters'
        }
      }
    }

    const result = validator.validate(fields)
    
    expect(result).toBe(true)
    expect(validator.errors.value.password).toBeUndefined()
  })

  it('validates multiple fields correctly', () => {
    const fields = {
      email: {
        value: 'invalid-email',
        required: true,
        email: true,
        messages: { 
          required: 'Email is required',
          email: 'Invalid email format'
        }
      },
      password: {
        value: '123',
        required: true,
        min: 6,
        messages: { 
          required: 'Password is required',
          min: 'Password must be at least 6 characters'
        }
      },
      name: {
        value: 'John Doe',
        required: true,
        messages: { 
          required: 'Name is required'
        }
      }
    }

    const result = validator.validate(fields)
    
    expect(result).toBe(false)
    expect(validator.errors.value.email).toBe('Invalid email format')
    expect(validator.errors.value.password).toBe('Password must be at least 6 characters')
    expect(validator.errors.value.name).toBeUndefined()
  })

  it('clears previous errors on new validation', () => {
    // First validation with errors
    const fields1 = {
      email: {
        value: '',
        required: true,
        messages: { required: 'Email is required' }
      }
    }
    
    validator.validate(fields1)
    expect(validator.errors.value.email).toBe('Email is required')

    // Second validation without errors
    const fields2 = {
      email: {
        value: 'test@example.com',
        required: true,
        messages: { required: 'Email is required' }
      }
    }
    
    const result = validator.validate(fields2)
    expect(result).toBe(true)
    expect(validator.errors.value).toEqual({})
  })

  it('handles empty field values correctly', () => {
    const fields = {
      email: {
        value: null,
        required: true,
        messages: { required: 'Email is required' }
      },
      password: {
        value: undefined,
        required: true,
        messages: { required: 'Password is required' }
      }
    }

    const result = validator.validate(fields)
    
    expect(result).toBe(false)
    expect(validator.errors.value.email).toBe('Email is required')
    expect(validator.errors.value.password).toBe('Password is required')
  })

  it('skips validation for non-required empty fields', () => {
    const fields = {
      optionalField: {
        value: '',
        required: false,
        messages: { required: 'This field is required' }
      }
    }

    const result = validator.validate(fields)
    
    expect(result).toBe(true)
    expect(validator.errors.value.optionalField).toBeUndefined()
  })

  it('applies multiple validation rules to a single field', () => {
    const fields = {
      email: {
        value: 'short',
        required: true,
        email: true,
        min: 10,
        messages: { 
          required: 'Email is required',
          email: 'Invalid email format',
          min: 'Email must be at least 10 characters'
        }
      }
    }

    const result = validator.validate(fields)
    
    expect(result).toBe(false)
    // Should stop at first failed validation (email format)
    expect(validator.errors.value.email).toBe('Invalid email format')
  })

  it('returns errors object reference for reactive updates', () => {
    const errors = validator.errors
    expect(errors).toBe(validator.errors)
  })
})
