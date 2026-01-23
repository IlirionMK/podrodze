import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PlacesWorkspace from '@/components/trips/panels/PlacesWorkspace.vue'

// Mock lucide-vue-next icons
vi.mock('lucide-vue-next', () => ({
  Plus: { template: '<div>Plus</div>' },
  RefreshCw: { template: '<div>RefreshCw</div>' },
  Search: { template: '<div>Search</div>' },
  ExternalLink: { template: '<div>ExternalLink</div>' },
  Pin: { template: '<div>Pin</div>' },
  Utensils: { template: '<div>Utensils</div>' },
  Landmark: { template: '<div>Landmark</div>' },
  Trees: { template: '<div>Trees</div>' },
  Sparkles: { template: '<div>Sparkles</div>' },
  MoonStar: { template: '<div>MoonStar</div>' },
  HelpCircle: { template: '<div>HelpCircle</div>' },
  UserRound: { template: '<div>UserRound</div>' },
  ChevronDown: { template: '<div>ChevronDown</div>' }
}))

// Mock TripMap
vi.mock('@/components/trips/TripMap.vue', () => ({
  default: {
    template: '<div class="trip-map">TripMap</div>',
    props: ['trip', 'places', 'selectedTripPlaceId'],
    emits: ['places-changed']
  }
}))

describe('PlacesWorkspace Component', () => {
  let wrapper

  beforeEach(() => {
    vi.clearAllMocks()
  })

  const createWrapper = (props = {}) => {
    return mount(PlacesWorkspace, {
      props: {
        trip: { id: 1, name: 'Test Trip' },
        places: [
          {
            id: 1,
            place: { name: 'Place 1', category_slug: 'food' },
            is_fixed: false,
            added_by: { name: 'John' }
          },
          {
            id: 2,
            place: { name: 'Place 2', category_slug: 'museum' },
            is_fixed: true,
            created_by: { name: 'Jane' }
          }
        ],
        categories: ['all', 'food', 'museum', 'nature'],
        filteredPlaces: [
          {
            id: 1,
            place: { name: 'Place 1', category_slug: 'food' },
            is_fixed: false,
            added_by: { name: 'John' }
          }
        ],
        placeQuery: '',
        categoryFilter: 'all',
        sortKey: 'name_asc',
        ...props
      },
      global: {
        stubs: {
          TripMap: true
        }
      }
    })
  }

  describe('basic rendering', () => {
    it('renders workspace layout', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('.grid.grid-cols-1').exists()).toBe(true)
      expect(wrapper.find('.lg\\:col-span-7').exists()).toBe(true)
      expect(wrapper.find('.lg\\:col-span-5').exists()).toBe(true)
    })

    it('renders places section', () => {
      wrapper = createWrapper()
      
      expect(wrapper.find('.bg-white.p-6').exists()).toBe(true)
      expect(wrapper.find('h2').exists()).toBe(true)
    })

    it('renders map section', () => {
      wrapper = createWrapper()
      
      expect(wrapper.findComponent({ name: 'TripMap' }).exists()).toBe(true)
    })

    it('renders action buttons', () => {
      wrapper = createWrapper()
      
      const buttons = wrapper.findAll('button[type="button"]')
      expect(buttons.length).toBeGreaterThan(0)
      
      // Should have Add place and Refresh buttons
      expect(wrapper.text()).toContain('Dodaj miejsce')
      expect(wrapper.text()).toContain('Odśwież')
    })
  })

  describe('search and filters', () => {
    it('renders search input', () => {
      wrapper = createWrapper()
      
      const searchInput = wrapper.find('input[type="text"]')
      expect(searchInput.exists()).toBe(true)
      expect(searchInput.attributes('placeholder')).toBe('')
    })

    it('renders category filter', () => {
      wrapper = createWrapper()
      
      const categorySelect = wrapper.findAll('select')[0]
      expect(categorySelect.exists()).toBe(true)
      
      const options = categorySelect.findAll('option')
      expect(options.length).toBe(4) // all, food, museum, nature
    })

    it('renders sort filter', () => {
      wrapper = createWrapper()
      
      const sortSelect = wrapper.findAll('select')[1]
      expect(sortSelect.exists()).toBe(true)
      
      const options = sortSelect.findAll('option')
      expect(options.length).toBe(4) // name_asc, name_desc, cat_asc, cat_desc
    })

    it('updates search query', async () => {
      wrapper = createWrapper()
      
      const searchInput = wrapper.find('input[type="text"]')
      await searchInput.setValue('test query')
      
      expect(wrapper.emitted('update:placeQuery')).toBeTruthy()
      expect(wrapper.emitted('update:placeQuery')[0]).toEqual(['test query'])
    })

    it('updates category filter', async () => {
      wrapper = createWrapper()
      
      const categorySelect = wrapper.findAll('select')[0]
      await categorySelect.setValue('food')
      
      expect(wrapper.emitted('update:categoryFilter')).toBeTruthy()
      expect(wrapper.emitted('update:categoryFilter')[0]).toEqual(['food'])
    })

    it('updates sort key', async () => {
      wrapper = createWrapper()
      
      const sortSelect = wrapper.findAll('select')[1]
      await sortSelect.setValue('name_desc')
      
      expect(wrapper.emitted('update:sortKey')).toBeTruthy()
      expect(wrapper.emitted('update:sortKey')[0]).toEqual(['name_desc'])
    })
  })

  describe('places list', () => {
    it('renders filtered places', () => {
      wrapper = createWrapper()
      
      const placeButtons = wrapper.findAll('button[type="button"]').filter(btn => 
        btn.text().includes('Place 1')
      )
      expect(placeButtons.length).toBe(1)
    })

    it('shows empty state when no places', () => {
      wrapper = createWrapper({ filteredPlaces: [] })
      
      expect(wrapper.text()).toContain('Brak miejsc')
      expect(wrapper.text()).toContain('Dodaj pierwsze miejsce, aby rozpocząć planowanie.')
    })

    it('shows loading state', () => {
      wrapper = createWrapper({ placesLoading: true })
      
      expect(wrapper.text()).toContain('Ładowanie...')
    })

    it('highlights selected place', () => {
      wrapper = createWrapper({ selectedTripPlaceId: 1 })
      
      const selectedPlace = wrapper.findAll('button[type="button"]').filter(btn => 
        btn.text().includes('Place 1')
      )[0]
      
      expect(selectedPlace.classes()).toContain('border-blue-200')
      expect(selectedPlace.classes()).toContain('bg-blue-50')
      expect(selectedPlace.classes()).toContain('ring-2')
    })

    it('emits select-place when place is clicked', async () => {
      wrapper = createWrapper()
      
      const placeButton = wrapper.findAll('button[type="button"]').filter(btn => 
        btn.text().includes('Place 1')
      )[0]
      
      await placeButton.trigger('click')
      
      expect(wrapper.emitted('select-place')).toBeTruthy()
      expect(wrapper.emitted('select-place')[0]).toEqual([1])
    })
  })

  describe('place item rendering', () => {
    it('displays place name', () => {
      wrapper = createWrapper()
      
      expect(wrapper.text()).toContain('Place 1')
    })

    it('displays category icon', () => {
      wrapper = createWrapper()
      
      const placeButton = wrapper.findAll('button[type="button"]').filter(btn => 
        btn.text().includes('Place 1')
      )[0]
      
      // Check if icon is rendered (mocked as div with icon name)
      expect(placeButton.text()).toContain('Utensils')
    })

    it('displays category label', () => {
      wrapper = createWrapper()
      
      expect(wrapper.text()).toContain('food')
    })

    it('displays fixed badge for fixed places', () => {
      wrapper = createWrapper({
        filteredPlaces: [
          {
            id: 2,
            place: { name: 'Fixed Place', category_slug: 'museum' },
            is_fixed: true
          }
        ]
      })
      
      expect(wrapper.text()).toContain('Stałe')
      // Check if icon is rendered (mocked as div with icon name)
      expect(wrapper.text()).toContain('Pin')
    })

    it('displays added by information', () => {
      wrapper = createWrapper()
      
      expect(wrapper.text()).toContain('Dodane przez John')
      // Check if icon is rendered (mocked as div with icon name)
      expect(wrapper.text()).toContain('UserRound')
    })

    it('shows open label', () => {
      wrapper = createWrapper()
      
      expect(wrapper.text()).toContain('Otwórz miejsce')
      // Check if icon is rendered (mocked as div with icon name)
      expect(wrapper.text()).toContain('ExternalLink')
    })
  })

  describe('action buttons', () => {
    it('emits open-add-place when add button is clicked', async () => {
      wrapper = createWrapper()
      
      const addButton = wrapper.findAll('button[type="button"]').filter(btn => 
        btn.text().includes('Dodaj miejsce')
      )[0]
      
      await addButton.trigger('click')
      
      expect(wrapper.emitted('open-add-place')).toBeTruthy()
    })

    it('emits refresh-places when refresh button is clicked', async () => {
      wrapper = createWrapper()
      
      const refreshButton = wrapper.findAll('button[type="button"]').filter(btn => 
        btn.text().includes('Odśwież')
      )[0]
      
      await refreshButton.trigger('click')
      
      expect(wrapper.emitted('refresh-places')).toBeTruthy()
    })

    it('has proper button styling', () => {
      wrapper = createWrapper()
      
      const buttons = wrapper.findAll('button[type="button"]')
      
      // Primary button (Add place)
      const addButton = buttons.filter(btn => btn.text().includes('Dodaj miejsce'))[0]
      if (addButton) {
        expect(addButton.classes()).toContain('bg-gradient-to-r')
        expect(addButton.classes()).toContain('from-blue-600')
        expect(addButton.classes()).toContain('to-purple-600')
      }
      
      // Secondary button (Refresh)
      const refreshButton = buttons.filter(btn => btn.text().includes('Odśwież'))[0]
      if (refreshButton) {
        expect(refreshButton.classes()).toContain('border')
        expect(refreshButton.classes()).toContain('bg-white')
        expect(refreshButton.classes()).toContain('text-gray-900')
      }
    })
  })

  describe('map integration', () => {
    it('passes correct props to TripMap', () => {
      wrapper = createWrapper()
      
      const tripMap = wrapper.findComponent({ name: 'TripMap' })
      expect(tripMap.props('trip')).toEqual({ id: 1, name: 'Test Trip' })
      expect(tripMap.props('places')).toHaveLength(2)
      expect(tripMap.props('selectedTripPlaceId')).toBeNull()
    })

    it('emits refresh-places when TripMap emits places-changed', async () => {
      wrapper = createWrapper()
      
      const tripMap = wrapper.findComponent({ name: 'TripMap' })
      await tripMap.vm.$emit('places-changed')
      
      expect(wrapper.emitted('refresh-places')).toBeTruthy()
    })

    it('shows places count in map section', () => {
      wrapper = createWrapper()
      
      expect(wrapper.text()).toContain('2')
    })

    it('renders clear selection button when slot is provided', () => {
      wrapper = mount(PlacesWorkspace, {
        props: {
          trip: { id: 1 },
          places: [],
          categories: [],
          filteredPlaces: [],
          placeQuery: '',
          categoryFilter: 'all',
          sortKey: 'name_asc'
        },
        slots: {
          clearSelection: '<button>Clear Selection</button>'
        },
        global: {
          stubs: {
            TripMap: true
          }
        }
      })
      
      expect(wrapper.text()).toContain('Clear Selection')
    })
  })

  describe('utility functions', () => {
    it('identifies fixed places correctly', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.isFixed({ is_fixed: true })).toBe(true)
      expect(wrapper.vm.isFixed({ fixed: true })).toBe(true)
      expect(wrapper.vm.isFixed({ is_mandatory: true })).toBe(true)
      expect(wrapper.vm.isFixed({ is_fixed: false })).toBe(false)
      expect(wrapper.vm.isFixed({})).toBe(false)
    })

    it('returns correct category icons', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.categoryIcon('food')).toBeDefined()
      expect(wrapper.vm.categoryIcon('museum')).toBeDefined()
      expect(wrapper.vm.categoryIcon('nature')).toBeDefined()
      expect(wrapper.vm.categoryIcon('attraction')).toBeDefined()
      expect(wrapper.vm.categoryIcon('nightlife')).toBeDefined()
      expect(wrapper.vm.categoryIcon('unknown')).toBeDefined()
    })

    it('returns correct category labels', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.categoryLabel('food')).toBe('Jedzenie')
      expect(wrapper.vm.categoryLabel('')).toBe('—')
    })

    it('extracts added by name correctly', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.addedByName({ added_by: { name: 'John' } })).toBe('John')
      expect(wrapper.vm.addedByName({ created_by: { name: 'Jane' } })).toBe('Jane')
      expect(wrapper.vm.addedByName({ addedBy: { name: 'Bob' } })).toBe('Bob')
      expect(wrapper.vm.addedByName({ creator: { name: 'Alice' } })).toBe('Alice')
      expect(wrapper.vm.addedByName({ user: { name: 'Tom' } })).toBe('Tom')
      expect(wrapper.vm.addedByName({ created_by_name: 'Jerry' })).toBe('Jerry')
      expect(wrapper.vm.addedByName({ added_by_name: 'Sam' })).toBe('Sam')
      expect(wrapper.vm.addedByName({})).toBeNull()
    })
  })

  describe('computed properties', () => {
    it('computes local labels correctly', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.localLabels.title).toBe('Miejsca')
      expect(wrapper.vm.localLabels.addBtn).toBe('Dodaj miejsce')
      expect(wrapper.vm.localLabels.refreshBtn).toBe('Odśwież')
    })

    it('uses custom labels when provided', () => {
      wrapper = createWrapper({
        labels: {
          title: 'Custom Title',
          addBtn: 'Custom Add',
          refreshBtn: 'Custom Refresh'
        }
      })
      
      expect(wrapper.vm.localLabels.title).toBe('Custom Title')
      expect(wrapper.vm.localLabels.addBtn).toBe('Custom Add')
      expect(wrapper.vm.localLabels.refreshBtn).toBe('Custom Refresh')
    })

    it('computes v-model bindings correctly', () => {
      wrapper = createWrapper()
      
      expect(wrapper.vm.queryModel).toBe('')
      expect(wrapper.vm.categoryModel).toBe('all')
      expect(wrapper.vm.sortModel).toBe('name_asc')
    })
  })

  describe('slots', () => {
    it('renders custom title slot', () => {
      wrapper = mount(PlacesWorkspace, {
        props: {
          trip: { id: 1 },
          places: [],
          categories: [],
          filteredPlaces: [],
          placeQuery: '',
          categoryFilter: 'all',
          sortKey: 'name_asc'
        },
        slots: {
          title: '<h1>Custom Title</h1>'
        },
        global: {
          stubs: {
            TripMap: true
          }
        }
      })
      
      expect(wrapper.text()).toContain('Custom Title')
    })

    it('renders custom add button slot', () => {
      wrapper = mount(PlacesWorkspace, {
        props: {
          trip: { id: 1 },
          places: [],
          categories: [],
          filteredPlaces: [],
          placeQuery: '',
          categoryFilter: 'all',
          sortKey: 'name_asc'
        },
        slots: {
          addBtn: '<span>Custom Add</span>'
        },
        global: {
          stubs: {
            TripMap: true
          }
        }
      })
      
      expect(wrapper.text()).toContain('Custom Add')
    })

    it('renders custom empty state slots', () => {
      wrapper = mount(PlacesWorkspace, {
        props: {
          trip: { id: 1 },
          places: [],
          categories: [],
          filteredPlaces: [],
          placeQuery: '',
          categoryFilter: 'all',
          sortKey: 'name_asc'
        },
        slots: {
          emptyTitle: '<h3>Custom Empty Title</h3>',
          emptyHint: '<p>Custom Empty Hint</p>'
        },
        global: {
          stubs: {
            TripMap: true
          }
        }
      })
      
      expect(wrapper.text()).toContain('Custom Empty Title')
      expect(wrapper.text()).toContain('Custom Empty Hint')
    })
  })

  describe('responsive design', () => {
    it('has responsive grid layout', () => {
      wrapper = createWrapper()
      
      const grid = wrapper.find('.grid.grid-cols-1')
      expect(grid.classes()).toContain('lg:grid-cols-12')
      expect(grid.classes()).toContain('gap-6')
      expect(grid.classes()).toContain('lg:gap-8')
    })

    it('has responsive column spans', () => {
      wrapper = createWrapper()
      
      const placesSection = wrapper.find('.lg\\:col-span-7')
      const mapSection = wrapper.find('.lg\\:col-span-5')
      
      expect(placesSection.exists()).toBe(true)
      expect(mapSection.exists()).toBe(true)
    })

    it('has responsive filter grid', () => {
      wrapper = createWrapper()
      
      const filterGrid = wrapper.find('.grid.grid-cols-1.md\\:grid-cols-12')
      expect(filterGrid.classes()).toContain('md:grid-cols-12')
    })

    it('has responsive button layout', () => {
      wrapper = createWrapper()
      
      const buttonContainer = wrapper.find('.flex.flex-col.gap-3.sm\\:flex-row')
      expect(buttonContainer.classes()).toContain('flex-col')
      expect(buttonContainer.classes()).toContain('sm:flex-row')
    })
  })

  describe('styling', () => {
    it('has proper section styling', () => {
      wrapper = createWrapper()
      
      const sections = wrapper.findAll('.bg-white.p-6')
      sections.forEach(section => {
        expect(section.classes()).toContain('rounded-2xl')
        expect(section.classes()).toContain('border')
        expect(section.classes()).toContain('shadow-sm')
      })
    })

    it('has proper input styling', () => {
      wrapper = createWrapper()
      
      const searchInput = wrapper.find('input[type="text"]')
      expect(searchInput.classes()).toContain('w-full')
      expect(searchInput.classes()).toContain('h-11')
      expect(searchInput.classes()).toContain('rounded-xl')
      expect(searchInput.classes()).toContain('border')
      expect(searchInput.classes()).toContain('border-gray-200')
    })

    it('has proper select styling', () => {
      wrapper = createWrapper()
      
      const selects = wrapper.findAll('select')
      selects.forEach(select => {
        expect(select.classes()).toContain('w-full')
        expect(select.classes()).toContain('h-11')
        expect(select.classes()).toContain('rounded-xl')
        expect(select.classes()).toContain('border')
        expect(select.classes()).toContain('border-gray-200')
      })
    })

    it('has proper place item styling', () => {
      wrapper = createWrapper()
      
      const placeButton = wrapper.findAll('button[type="button"]').filter(btn => 
        btn.text().includes('Place 1')
      )[0]
      
      expect(placeButton.classes()).toContain('border')
      expect(placeButton.classes()).toContain('rounded-2xl')
      expect(placeButton.classes()).toContain('p-4')
      expect(placeButton.classes()).toContain('flex')
    })
  })

  describe('accessibility', () => {
    it('has proper button types', () => {
      wrapper = createWrapper()
      
      const buttons = wrapper.findAll('button[type="button"]')
      buttons.forEach(button => {
        expect(button.attributes('type')).toBe('button')
      })
    })

    it('has proper input types', () => {
      wrapper = createWrapper()
      
      const searchInput = wrapper.find('input[type="text"]')
      expect(searchInput.attributes('type')).toBe('text')
    })

    it('has proper titles for icons', () => {
      wrapper = createWrapper()
      
      const categoryIcon = wrapper.find('[title]')
      expect(categoryIcon.exists()).toBe(true)
    })
  })
})
