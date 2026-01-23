import { vi } from 'vitest'
import { config } from '@vue/test-utils'

// Mock localStorage
const localStorageMock = {
  getItem: vi.fn(),
  setItem: vi.fn(),
  removeItem: vi.fn(),
  clear: vi.fn(),
}
global.localStorage = localStorageMock

// Mock vue-router
vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
    go: vi.fn(),
    back: vi.fn(),
    forward: vi.fn()
  }),
  useRoute: () => ({
    path: '/',
    name: 'home',
    params: {},
    query: {},
    meta: {}
  })
}))

// Mock vue-i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: vi.fn((key) => key),
    locale: { value: 'pl' },
    availableLocales: ['pl', 'en']
  }),
  createI18n: () => ({
    global: {
      t: vi.fn((key) => key)
    }
  })
}))

// Global test configuration
config.global.stubs = {
  'router-link': {
    template: '<a :to="to" class="router-link-stub"><slot /></a>',
    props: ['to']
  },
  'router-view': {
    template: '<div><slot /></div>'
  }
}

// Mock global $t function for templates
config.global.mocks = {
  $t: vi.fn((key) => key)
}
