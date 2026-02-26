// Test setup file for Vitest
import { expect } from 'vitest'

// Make global utilities available
global.expect = expect

// Mock Laravel helpers
global.route = (name, params = {}) => {
  // Simple mock for Laravel's route() function
  const baseRoutes = {
    'business.dashboard': '/business/dashboard',
    'business.promotions.index': '/business/promotions',
    'employee.redeem': '/employee/redeem',
    'portal.dashboard': '/portal/dashboard',
  }
  
  let path = baseRoutes[name] || `/${name}`
  
  // Replace parameters
  Object.entries(params).forEach(([key, value]) => {
    path = path.replace(`:${key}`, value)
  })
  
  return path
}

// Mock Laravel's @inertia/vue3
global.page = {
  props: {
    auth: { user: null },
    errors: {}
  }
}

// Mock formatCurrency utility
global.formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount || 0)
}