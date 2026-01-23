import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AddPlaceModal from '@/components/trips/AddPlaceModal.vue'

describe('AddPlaceModal Component', () => {
  let wrapper

  beforeEach(() => {
    vi.clearAllMocks()
  })

  const createWrapper = (props = {}) => {
    return mount(AddPlaceModal, {
      props: {
        modelValue: true,
        ...props
      },
      global: {
        stubs: {
          Teleport: true,
          Transition: true
        }
      }
    })
  }

  describe('basic rendering', () => {
    it('renders modal when modelValue is true', () => {
      wrapper = createWrapper({ modelValue: true })
      
      expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
      expect(wrapper.find('.fixed.inset-0').exists()).toBe(true)
    })

    it('does not render modal when modelValue is false', () => {
      wrapper = createWrapper({ modelValue: false })
      
      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    })

    it('renders close button', () => {
      wrapper = createWrapper()
      
      const closeButton = wrapper.find('button[type="button"]')
      expect(closeButton.exists()).toBe(true)
      expect(closeButton.text()).toContain('Anuluj')
    })

    it('renders backdrop', () => {
      wrapper = createWrapper()
      
      const backdrop = wrapper.find('.absolute.inset-0.bg-black\\/60')
      expect(backdrop.exists()).toBe(true)
    })
  })

  describe('place variant', () => {
    it('renders place form fields', () => {
      wrapper = createWrapper({ variant: 'place' })
      
      expect(wrapper.find('input[type="text"]').exists()).toBe(true)
      expect(wrapper.find('select').exists()).toBe(true)
      expect(wrapper.find('button[type="button"]').exists()).toBe(true)
    })

    it('renders name input with correct attributes', () => {
      wrapper = createWrapper({ variant: 'place' })
      
      const nameInput = wrapper.find('input[type="text"]')
      expect(nameInput.attributes('placeholder')).toBe('Nazwa miejsca')
      expect(nameInput.attributes('autocomplete')).toBe('off')
    })

    it('renders category select with options', () => {
      wrapper = createWrapper({ variant: 'place' })
      
      const select = wrapper.find('select')
      const options = select.findAll('option')
      
      expect(options.length).toBeGreaterThan(0)
      expect(options[0].attributes('value')).toBe('other')
    })

    it('displays coordinates when lat and lng are provided', () => {
      wrapper = createWrapper({ 
        variant: 'place',
        lat: 50.123456,
        lng: 20.654321
      })
      
      expect(wrapper.vm.coordsLabel).toBe('50.123456, 20.654321')
    })

    it('displays placeholder when coordinates are missing', () => {
      wrapper = createWrapper({ 
        variant: 'place',
        lat: null,
        lng: null
      })
      
      expect(wrapper.text()).toContain('—')
      expect(wrapper.text()).toContain('Najpierw wybierz punkt na mapie')
    })
  })

  describe('custom variant', () => {
    it('renders custom slot content', () => {
      wrapper = mount(AddPlaceModal, {
        props: { modelValue: true, variant: 'custom' },
        slots: {
          body: '<div class="custom-body">Custom Content</div>'
        },
        global: {
          stubs: {
            Teleport: true,
            Transition: true
          }
        }
      })
      
      expect(wrapper.find('.custom-body').exists()).toBe(true)
      expect(wrapper.find('.custom-body').text()).toBe('Custom Content')
    })

    it('renders custom title slot', () => {
      wrapper = mount(AddPlaceModal, {
        props: { modelValue: true, variant: 'custom' },
        slots: {
          title: '<h1>Custom Title</h1>'
        },
        global: {
          stubs: {
            Teleport: true,
            Transition: true
          }
        }
      })
      
      expect(wrapper.text()).toContain('Custom Title')
    })

    it('renders custom subtitle slot', () => {
      wrapper = mount(AddPlaceModal, {
        props: { modelValue: true, variant: 'custom' },
        slots: {
          subtitle: '<p>Custom Subtitle</p>'
        },
        global: {
          stubs: {
            Teleport: true,
            Transition: true
          }
        }
      })
      
      expect(wrapper.text()).toContain('Custom Subtitle')
    })
  })

  describe('form interactions', () => {
    it('updates name input value', async () => {
      wrapper = createWrapper({ variant: 'place' })
      
      const nameInput = wrapper.find('input[type="text"]')
      await nameInput.setValue('Test Place')
      
      expect(wrapper.vm.name).toBe('Test Place')
    })

    it('updates category select value', async () => {
      wrapper = createWrapper({ variant: 'place' })
      
      const select = wrapper.find('select')
      await select.setValue('food')
      
      expect(wrapper.vm.category).toBe('food')
    })
  })

  describe('validation', () => {
    it('disables submit button when busy', () => {
      wrapper = createWrapper({ 
        variant: 'place',
        busy: true,
        lat: 50.123456,
        lng: 20.654321
      })
      
      const submitButton = wrapper.find('button[type="button"]')
      expect(submitButton.attributes('disabled')).toBeDefined()
    })

    it('disables submit button when name is empty', () => {
      wrapper = createWrapper({ 
        variant: 'place',
        lat: 50.123456,
        lng: 20.654321
      })
      
      expect(wrapper.vm.canSubmitPlace).toBe(false)
    })

    it('disables submit button when coordinates are missing', () => {
      wrapper = createWrapper({ 
        variant: 'place',
        lat: null,
        lng: null
      })
      
      expect(wrapper.vm.canSubmitPlace).toBe(false)
    })

    it('enables submit button when all required fields are filled', () => {
      wrapper = createWrapper({ 
        variant: 'place',
        lat: 50.123456,
        lng: 20.654321
      })
      
      wrapper.vm.name = 'Test Place'
      expect(wrapper.vm.canSubmitPlace).toBe(true)
    })
  })

  describe('modal interactions', () => {
    it('closes modal when close button is clicked', async () => {
      wrapper = createWrapper()
      
      const closeButton = wrapper.find('button[type="button"]')
      await closeButton.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
      expect(wrapper.emitted('close')).toBeTruthy()
    })

    it('closes modal when backdrop is clicked', async () => {
      wrapper = createWrapper({ closeOnBackdrop: true })
      
      const backdrop = wrapper.find('.absolute.inset-0.bg-black\\/60')
      await backdrop.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
    })

    it('does not close modal when backdrop is clicked and closeOnBackdrop is false', async () => {
      wrapper = createWrapper({ closeOnBackdrop: false })
      
      const backdrop = wrapper.find('.absolute.inset-0.bg-black\\/60')
      await backdrop.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeFalsy()
    })

    it('does not close modal when busy', async () => {
      wrapper = createWrapper({ busy: true })
      
      const closeButton = wrapper.find('button[type="button"]')
      await closeButton.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeFalsy()
    })

    it('emits open event when modal opens', async () => {
      wrapper = createWrapper({ modelValue: false })
      
      await wrapper.setProps({ modelValue: true })
      await wrapper.vm.$nextTick()
      
      expect(wrapper.emitted('open')).toBeTruthy()
    })
  })

  describe('computed properties', () => {
    it('computes coords label correctly', () => {
      wrapper = createWrapper({ 
        lat: 50.123456,
        lng: 20.654321
      })
      
      expect(wrapper.vm.coordsLabel).toBe('50.123456, 20.654321')
    })

    it('computes coords label as dash when coordinates are null', () => {
      wrapper = createWrapper({ 
        lat: null,
        lng: null
      })
      
      expect(wrapper.vm.coordsLabel).toBe('—')
    })

    it('detects subtitle slot correctly', () => {
      wrapper = mount(AddPlaceModal, {
        props: { modelValue: true },
        slots: {
          subtitle: '<p>Subtitle</p>'
        },
        global: {
          stubs: {
            Teleport: true,
            Transition: true
          }
        }
      })
      
      expect(wrapper.vm.hasSubtitleSlot).toBe(true)
    })

    it('detects actions slot correctly', () => {
      wrapper = mount(AddPlaceModal, {
        props: { modelValue: true },
        slots: {
          actions: '<button>Action</button>'
        },
        global: {
          stubs: {
            Teleport: true,
            Transition: true
          }
        }
      })
      
      expect(wrapper.vm.hasActionsSlot).toBe(true)
    })
  })

  describe('styling', () => {
    it('applies custom maxWidthClass', () => {
      wrapper = createWrapper({ maxWidthClass: 'max-w-lg' })
      
      const modal = wrapper.find('.relative.w-full')
      expect(modal.classes()).toContain('max-w-lg')
    })

    it('has proper modal styling classes', () => {
      wrapper = createWrapper()
      
      const modal = wrapper.find('.relative.w-full')
      expect(modal.classes()).toContain('rounded-2xl')
      expect(modal.classes()).toContain('border')
      expect(modal.classes()).toContain('border-white/15')
      expect(modal.classes()).toContain('bg-white/10')
      expect(modal.classes()).toContain('backdrop-blur-xl')
      expect(modal.classes()).toContain('shadow-2xl')
      expect(modal.classes()).toContain('text-white')
    })
  })
})
