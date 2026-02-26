import { describe, it, expect } from 'vitest'

function prefillOriginalAmount(calculatorInfo, promotion, isFinalPunch) {
  let originalAmount = ''
  if (calculatorInfo?.prefills?.purchase_amount) {
    originalAmount = parseFloat(calculatorInfo.prefills.purchase_amount).toFixed(2)
  } else if (promotion?.original_price) {
    originalAmount = parseFloat(promotion.original_price).toFixed(2)
  }
  if (isFinalPunch && promotion.reward_value) {
    originalAmount = parseFloat(promotion.reward_value).toFixed(2)
  }
  return originalAmount
}

function shouldShowDisplay(promotion, originalAmount, promotionOriginalPrice) {
  return promotion.discount_type === 'punch_card' ||
         (originalAmount && parseFloat(originalAmount) > 0) ||
         (promotionOriginalPrice && parseFloat(promotionOriginalPrice) > 0)
}

describe('Punch card logic', () => {
  it('prefills original price when provided', () => {
    expect(prefillOriginalAmount({}, { original_price: 25.0 }, false)).toBe('25.00')
  })

  it('prefill uses reward_value for final punch', () => {
    expect(prefillOriginalAmount({}, { original_price: 25.0, reward_value: 15.0 }, true)).toBe('15.00')
  })

  it('display shows for punch_card even if empty', () => {
    expect(shouldShowDisplay({ discount_type: 'punch_card' }, '', null)).toBe(true)
  })

  it('display shows when original_price present', () => {
    expect(shouldShowDisplay({ discount_type: 'percentage' }, '', '20.00')).toBe(true)
  })
})

