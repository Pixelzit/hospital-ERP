import { test } from 'node:test'
import assert from 'node:assert/strict'
import { canLogin } from '../../resources/js/auth/credentials.js'

test('admin / admin can log in', () => {
  assert.equal(canLogin('admin', 'admin'), true)
})

test('any other username or password is rejected', () => {
  assert.equal(canLogin('admin', 'wrong'), false)
  assert.equal(canLogin('user', 'admin'), false)
  assert.equal(canLogin('', ''), false)
  assert.equal(canLogin('Admin', 'admin'), false)
})
