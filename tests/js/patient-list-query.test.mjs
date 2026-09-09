import { test } from 'node:test'
import assert from 'node:assert/strict'
import { filterPatients, paginatePatients } from '../../resources/js/patients/listQuery.js'

const patients = [
  { id: '1', mrn: 'HCS-24001', name: 'Priya Sharma', phone: '9876543210', email: 'priya.sharma@example.com', gender: 'Female', age: 29, status: 'active', created_at: '2026-01-15 10:00:00' },
  { id: '2', mrn: 'HCS-24002', name: 'Rohan Mehta', phone: '9810012345', email: 'rohan.mehta@example.com', gender: 'Male', age: 67, status: 'inactive', created_at: '2025-11-02 09:00:00' },
  { id: '3', mrn: 'HCS-24003', name: 'Aarav Singh', phone: '9900112233', email: 'aarav.singh@example.com', gender: 'Male', age: 8, status: 'active', created_at: '2026-03-20 08:00:00' },
]

test('search matches name, MRN, phone, or email', () => {
  assert.equal(filterPatients(patients, { search: 'priya' }).length, 1)
  assert.equal(filterPatients(patients, { search: 'HCS-24002' })[0].name, 'Rohan Mehta')
  assert.equal(filterPatients(patients, { search: '9900112233' })[0].name, 'Aarav Singh')
  assert.equal(filterPatients(patients, { search: 'rohan.mehta' })[0].mrn, 'HCS-24002')
})

test('status, gender, age group, and registration date filters combine', () => {
  const rows = filterPatients(patients, {
    status: 'active',
    gender: 'Male',
    ageGroup: '0-17',
    registeredFrom: '2026-03-01',
    registeredTo: '2026-03-31',
  })
  assert.equal(rows.length, 1)
  assert.equal(rows[0].name, 'Aarav Singh')
})

test('paginatePatients returns the requested page slice', () => {
  const page1 = paginatePatients(patients, 1, 2)
  assert.equal(page1.rows.length, 2)
  assert.equal(page1.total, 3)
  assert.equal(page1.pages, 2)
  assert.equal(paginatePatients(patients, 2, 2).rows[0].name, 'Aarav Singh')
})
