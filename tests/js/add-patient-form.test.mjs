import { test } from 'node:test'
import assert from 'node:assert/strict'
import { findDuplicatePatients, splitFullName, validatePatientForm } from '../../resources/js/patients/addPatientForm.js'

test('splitFullName separates first and last names', () => {
  assert.deepEqual(splitFullName('Anita Verma'), { first_name: 'Anita', last_name: 'Verma' })
  assert.deepEqual(splitFullName('Priya'), { first_name: 'Priya', last_name: '' })
})

test('validatePatientForm requires identity, mobile, address, and emergency contact', () => {
  const errors = validatePatientForm({
    full_name: '',
    date_of_birth: '',
    gender: '',
    phone: '',
    email: 'not-an-email',
    address_line_1: '',
    emergency_name: '',
    emergency_relationship: '',
    emergency_phone: '',
  })
  assert.equal(errors.full_name, 'Full name is required')
  assert.equal(errors.date_of_birth, 'Date of birth is required')
  assert.equal(errors.gender, 'Gender is required')
  assert.equal(errors.phone, 'Mobile number is required')
  assert.equal(errors.email, 'Enter a valid email')
  assert.equal(errors.address_line_1, 'Address line 1 is required')
  assert.equal(errors.emergency_name, 'Emergency contact name is required')
  assert.equal(errors.emergency_phone, 'Emergency phone is required')
})

test('findDuplicatePatients matches name, date of birth, and phone', () => {
  const patients = [
    { name: 'Anita Verma', date_of_birth: '1990-05-12', phone: '9810001122' },
    { name: 'Rohan Mehta', date_of_birth: '1958-11-02', phone: '9810012345' },
  ]
  const hits = findDuplicatePatients(patients, {
    full_name: 'anita verma',
    date_of_birth: '1990-05-12',
    phone: '9810001122',
  })
  assert.equal(hits.length, 1)
  assert.equal(hits[0].name, 'Anita Verma')
  assert.equal(findDuplicatePatients(patients, {
    full_name: 'Anita Verma',
    date_of_birth: '1990-05-12',
    phone: '9999999999',
  }).length, 0)
})
