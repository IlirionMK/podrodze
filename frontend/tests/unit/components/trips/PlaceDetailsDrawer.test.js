import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PlaceDetailsDrawer from '@/components/trips/PlaceDetailsDrawer.vue'

// Mock lucide-vue-next icons
vi.mock('lucide-vue-next', () => ({
  X: { template: '<div>X</div>' },
  ThumbsUp: { template: '<div>ThumbsUp</div>' },
  ThumbsDown: { template: '<div>ThumbsDown</div>' },
  Pin: { template: '<div>Pin</div>' },
  Trash2: { template: '<div>Trash2</div>' }
}))

describe('PlaceDetailsDrawer Component', () => {
  let wrapper

  beforeEach(() => {
    vi.clearAllMocks()
  })

  const createWrapper = (props = {}) => {
    return mount(PlaceDetailsDrawer, {
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

  const mockTripPlace = {
    id: 1,
    name: 'Test Place',
    category_slug: 'restaurant',
    votes_count: 5,
    is_fixed: true
  }

  describe('basic rendering', () => {
    it('renders drawer when modelValue is true', () => {
      wrapper = createWrapper({ modelValue: true })
      
      expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
      expect(wrapper.find('.fixed.inset-0').exists()).toBe(true)
    })

    it('does not render drawer when modelValue is false', () => {
      wrapper = createWrapper({ modelValue: false })
      
      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    })

    it('renders close button', () => {
      wrapper = createWrapper()
      
      const closeButton = wrapper.find('button[aria-label="Close"]')
      expect(closeButton.exists()).toBe(true)
    })

    it('renders backdrop', () => {
      wrapper = createWrapper()
      
      const backdrop = wrapper.find('.absolute.inset-0.bg-black\\/60')
      expect(backdrop.exists()).toBe(true)
    })
  })

  describe('place information display', () => {
    it('displays place name', () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      expect(wrapper.text()).toContain('Test Place')
    })

    it('displays placeholder when no place name', () => {
      wrapper = createWrapper({ tripPlace: { name: null } })
      
      expect(wrapper.text()).toContain('—')
    })

    it('displays category', () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      expect(wrapper.text()).toContain('restaurant')
    })

    it('displays votes count when available', () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      expect(wrapper.text()).toContain('Głosy:')
      expect(wrapper.text()).toContain('5')
    })

    it('does not display votes when null', () => {
      wrapper = createWrapper({ tripPlace: { ...mockTripPlace, votes_count: null } })
      
      expect(wrapper.text()).not.toContain('Głosy:')
    })

    it('displays fixed status when place is fixed', () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      expect(wrapper.text()).toContain('Stałe')
    })

    it('does not display fixed status when place is not fixed', () => {
      wrapper = createWrapper({ tripPlace: { ...mockTripPlace, is_fixed: false } })
      
      expect(wrapper.text()).not.toContain('Stałe')
    })
  })

  describe('computed properties', () => {
    it('computes place correctly from tripPlace', () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      expect(wrapper.vm.place.name).toBe('Test Place')
      expect(wrapper.vm.place.category_slug).toBe('restaurant')
    })

    it('computes place correctly from nested place object', () => {
      const nestedTripPlace = {
        place: mockTripPlace
      }
      wrapper = createWrapper({ tripPlace: nestedTripPlace })
      
      expect(wrapper.vm.place.name).toBe('Test Place')
    })

    it('computes title correctly', () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      expect(wrapper.vm.title).toBe('Test Place')
    })

    it('computes category correctly', () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      expect(wrapper.vm.category).toBe('restaurant')
    })

    it('computes votes count from different field names', () => {
      const testCases = [
        { votes_count: 10 },
        { votes: 15 },
        { meta: { votes: 20 } }
      ]

      testCases.forEach((tripPlace, index) => {
        wrapper = createWrapper({ tripPlace: { ...tripPlace } })
        expect(wrapper.vm.votesCount).toBe([10, 15, 20][index])
      })
    })

    it('computes is_fixed from different field names', () => {
      const testCases = [
        { is_fixed: true },
        { fixed: true },
        { is_mandatory: true }
      ]

      testCases.forEach(tripPlace => {
        wrapper = createWrapper({ tripPlace: { ...mockTripPlace, ...tripPlace } })
        expect(wrapper.vm.isFixed).toBe(true)
      })
    })
  })

  describe('button interactions', () => {
    it('emits like event when like button is clicked', async () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      const buttons = wrapper.findAll('button[type="button"]')
      console.log('Total buttons:', buttons.length)
      buttons.forEach((btn, index) => {
        console.log(`Button ${index}:`, btn.text())
      })
      
      const likeButton = buttons[1] // Second button is like (first is close)
      await likeButton.trigger('click')
      
      expect(wrapper.emitted('like')).toBeTruthy()
    })

    it('emits dislike event when dislike button is clicked', async () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      const dislikeButton = wrapper.findAll('button[type="button"]')[2] // Third button is dislike
      await dislikeButton.trigger('click')
      
      expect(wrapper.emitted('dislike')).toBeTruthy()
    })

    it('emits toggle-fixed event when toggle fixed button is clicked', async () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      const toggleButton = wrapper.findAll('button[type="button"]')[3] // Fourth button is toggle fixed
      await toggleButton.trigger('click')
      
      expect(wrapper.emitted('toggle-fixed')).toBeTruthy()
    })

    it('emits remove event when remove button is clicked', async () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      const removeButton = wrapper.findAll('button[type="button"]')[4] // Fifth button is remove
      await removeButton.trigger('click')
      
      expect(wrapper.emitted('remove')).toBeTruthy()
    })

    it('does not emit events when busy', async () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace, busy: true })
      
      const buttons = wrapper.findAll('button[type="button"]')
      for (const button of buttons) {
        await button.trigger('click')
      }
      
      expect(wrapper.emitted('like')).toBeFalsy()
      expect(wrapper.emitted('dislike')).toBeFalsy()
      expect(wrapper.emitted('toggle-fixed')).toBeFalsy()
      expect(wrapper.emitted('remove')).toBeFalsy()
    })
  })

  describe('modal interactions', () => {
    it('closes drawer when close button is clicked', async () => {
      wrapper = createWrapper()
      
      const closeButton = wrapper.find('button[aria-label="Close"]')
      await closeButton.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
      expect(wrapper.emitted('close')).toBeTruthy()
    })

    it('closes drawer when backdrop is clicked', async () => {
      wrapper = createWrapper({ closeOnBackdrop: true })
      
      const backdrop = wrapper.find('.absolute.inset-0.bg-black\\/60')
      await backdrop.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
    })

    it('does not close drawer when backdrop is clicked and closeOnBackdrop is false', async () => {
      wrapper = createWrapper({ closeOnBackdrop: false })
      
      const backdrop = wrapper.find('.absolute.inset-0.bg-black\\/60')
      await backdrop.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeFalsy()
    })

    it('does not close drawer when busy', async () => {
      wrapper = createWrapper({ busy: true })
      
      const closeButton = wrapper.find('button[aria-label="Close"]')
      await closeButton.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeFalsy()
    })
  })

  describe('button text and states', () => {
    it('shows correct text for toggle fixed button when place is fixed', () => {
      wrapper = createWrapper({ tripPlace: { ...mockTripPlace, is_fixed: true } })
      
      const buttons = wrapper.findAll('button[type="button"]')
      const toggleButton = buttons[3] // Fourth button is toggle fixed
      
      expect(toggleButton.text()).toContain('Odepnij')
    })

    it('shows correct text for toggle fixed button when place is not fixed', () => {
      wrapper = createWrapper({ tripPlace: { ...mockTripPlace, is_fixed: false } })
      
      const buttons = wrapper.findAll('button[type="button"]')
      const toggleButton = buttons[3] // Fourth button is toggle fixed
      
      expect(toggleButton.text()).toContain('Przypnij')
    })

    it('disables all buttons when busy', () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace, busy: true })
      
      const buttons = wrapper.findAll('button[type="button"]')
      buttons.forEach(button => {
        expect(button.attributes('disabled')).toBeDefined()
      })
    })
  })

  describe('styling', () => {
    it('applies custom maxWidthClass', () => {
      wrapper = createWrapper({ maxWidthClass: 'max-w-lg' })
      
      const drawer = wrapper.find('.relative.w-full')
      expect(drawer.classes()).toContain('max-w-lg')
    })

    it('has proper drawer styling classes', () => {
      wrapper = createWrapper()
      
      const drawer = wrapper.find('.relative.w-full')
      expect(drawer.classes()).toContain('rounded-2xl')
      expect(drawer.classes()).toContain('border')
      expect(drawer.classes()).toContain('border-white/15')
      expect(drawer.classes()).toContain('bg-white/10')
      expect(drawer.classes()).toContain('backdrop-blur-xl')
      expect(drawer.classes()).toContain('shadow-2xl')
      expect(drawer.classes()).toContain('text-white')
    })

    it('has proper button styling classes', () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      const buttons = wrapper.findAll('button[type="button"]')
      
      // Like and dislike buttons should have glass styling
      expect(buttons[0].classes()).toContain('bg-white/10')
      expect(buttons[1].classes()).toContain('bg-white/10')
      
      // Remove button should have danger styling
      const removeButton = buttons[4]
      expect(removeButton.classes()).toContain('bg-red-500/15')
      expect(removeButton.classes()).toContain('border-red-400/30')
      expect(removeButton.classes()).toContain('text-red-100')
    })

    it('has proper pill styling for badges', () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      const pills = wrapper.findAll('.inline-flex.items-center.rounded-full')
      expect(pills.length).toBeGreaterThan(0)
      
      pills.forEach(pill => {
        expect(pill.classes()).toContain('border')
        expect(pill.classes()).toContain('px-3')
        expect(pill.classes()).toContain('py-1')
        expect(pill.classes()).toContain('text-xs')
      })
    })
  })

  describe('translation function', () => {
    it('uses fallback when translation does not exist', () => {
      // Mock te to return false for this test
      vi.mocked(vi.fn()).mockReturnValue(false)
      
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      expect(wrapper.vm.tr('nonexistent.key', 'Fallback')).toBe('Fallback')
    })

    it('uses translation when it exists', () => {
      wrapper = createWrapper({ tripPlace: mockTripPlace })
      
      expect(wrapper.vm.tr('trip.place.modal.subtitle', 'Fallback')).toBe('Zarządzaj głosami i przypnij to miejsce do podróży.')
    })
  })

  describe('edge cases', () => {
    it('handles null tripPlace gracefully', () => {
      wrapper = createWrapper({ tripPlace: null })
      
      expect(wrapper.vm.place).toBeNull()
      expect(wrapper.vm.title).toBe('—')
      expect(wrapper.vm.category).toBe('—')
      expect(wrapper.vm.votesCount).toBeNull()
      expect(wrapper.vm.isFixed).toBe(false)
    })

    it('handles undefined tripPlace gracefully', () => {
      wrapper = createWrapper({ tripPlace: undefined })
      
      expect(wrapper.vm.place).toBeNull()
      expect(wrapper.vm.title).toBe('—')
      expect(wrapper.vm.category).toBe('—')
    })

    it('handles empty object tripPlace', () => {
      wrapper = createWrapper({ tripPlace: {} })
      
      expect(wrapper.vm.place).toEqual({})
      expect(wrapper.vm.title).toBe('—')
      expect(wrapper.vm.category).toBe('—')
    })
  })
})
