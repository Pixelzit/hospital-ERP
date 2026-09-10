import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  filterAppointments,
  filterDocuments,
  formatAddress,
  patientInitials,
  profileToForm,
} from '../../resources/js/patients/profileWorkspace.js'

test('patientInitials uses first and last name letters', () => {
  assert.equal(patientInitials('Anita Verma'), 'AV')
  assert.equal(patientInitials('Priya'), 'P')
})

test('formatAddress joins available parts', () => {
  assert.equal(formatAddress({
    address_line_1: '123 MG Road',
    city: 'Indore',
    state: 'Madhya Pradesh',
    postal_code: '452001',
  }), '123 MG Road, Indore, Madhya Pradesh - 452001')
  assert.equal(formatAddress(null), '—')
})

test('filterAppointments splits upcoming completed cancelled', () => {
  const rows = [
    { status: 'scheduled', appointment_date: '2099-01-01' },
    { status: 'completed', appointment_date: '2020-01-01' },
    { status: 'cancelled', appointment_date: '2099-01-01' },
  ]
  assert.equal(filterAppointments(rows, 'upcoming').length, 1)
  assert.equal(filterAppointments(rows, 'completed').length, 1)
  assert.equal(filterAppointments(rows, 'cancelled').length, 1)
})

test('filterDocuments matches category', () => {
  const rows = [
    { document_type: 'id_proof', title: 'Aadhaar' },
    { document_type: 'lab_report', title: 'CBC' },
    { document_type: 'prescription', title: 'Rx' },
  ]
  assert.equal(filterDocuments(rows, 'all').length, 3)
  assert.equal(filterDocuments(rows, 'lab').length, 1)
  assert.equal(filterDocuments(rows, 'prescriptions').length, 1)
  assert.equal(filterDocuments(rows, 'uploaded').length, 1)
})

test('profileToForm maps a patient into the add/edit form shape', () => {
  const form = profileToForm({
    name: 'Anita Verma',
    date_of_birth: '1990-05-12',
    gender: 'Female',
    phone: '9810001122',
    email: 'anita.verma@email.com',
    address: { address_line_1: '123 MG Road', city: 'Indore' },
    emergency: { name: 'Rohan Verma', relationship: 'Spouse', phone: '9810000000' },
  })
  assert.equal(form.full_name, 'Anita Verma')
  assert.equal(form.date_of_birth, '1990-05-12')
  assert.equal(form.address_line_1, '123 MG Road')
  assert.equal(form.emergency_name, 'Rohan Verma')
})
