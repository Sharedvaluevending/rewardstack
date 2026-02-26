import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CurrencyDisplay from './CurrencyDisplay.vue'

describe('CurrencyDisplay', () => {
  it('displays amount', () => {
    const wrapper = mount(CurrencyDisplay, { props: { amount: 10.5 } })
    expect(wrapper.text()).toBe('$10.50')
  })

  it('shows $0.00 for undefined', () => {
    const wrapper = mount(CurrencyDisplay, { props: { amount: undefined } })
    expect(wrapper.text()).toBe('$0.00')
  })
})

