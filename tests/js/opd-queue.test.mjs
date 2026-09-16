import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  filterOpdVisits,
  opdStatusCounts,
  opdStatusKey,
  opdStatusLabel,
  sortOpdVisits,
  walkInLabel,
} from '../../resources/js/opd/opdQueue.js'
import { paginatePatients } from '../../resources/js/patients/listQuery.js'

const rows = [
  {
    id: 'v1',
    patient_id: 'p1',
    visit_number: 'ENC-OPD-1',
    appointment_number: 'APT-1',
    patient_name: 'Sneha Reddy',
    uhid: 'HCS-26-0001',
    phone: '9876101234',
    doctor: 'Meera Iyer',
    doctor_id: 'd1',
    department: 'Radiology',
    department_id: 'dep1',
    patient_type: 'Out Patient',
    status: 'open',
    started_at: '2026-09-10 09:00:00',
  },
  {
    id: 'v2',
    patient_id: 'p2',
    visit_number: 'ENC-OPD-2',
    appointment_number: null,
    patient_name: 'Anita Verma',
    uhid: 'HCS-26-0006',
    phone: '9000000000',
    doctor: 'Ketan Sharma',
    doctor_id: 'd2',
    department: 'General Medicine',
    department_id: 'dep2',
    patient_type: 'Out Patient',
    status: 'closed',
    started_at: '2026-09-10 10:00:00',
  },
]

test('opdStatusKey maps backend encounter status without inventing nurse/doctor stages', () => {
  assert.equal(opdStatusKey('open'), 'new_patient')
  assert.equal(opdStatusKey('closed'), 'visit_complete')
  assert.equal(opdStatusKey('nurse_seen'), 'nurse_seen')
  assert.equal(opdStatusLabel('closed'), 'Visit Complete')
  assert.equal(walkInLabel(null), 'Walk-in')
  assert.equal(walkInLabel('APT-1'), 'APT-1')
})

test('filterOpdVisits searches name MRN visit number and phone and filters doctor department type status', () => {
  assert.equal(filterOpdVisits(rows, { search: 'HCS-26-0001' }).length, 1)
  assert.equal(filterOpdVisits(rows, { search: 'ENC-OPD-2' })[0].id, 'v2')
  assert.equal(filterOpdVisits(rows, { search: '9876101234' })[0].id, 'v1')
  assert.equal(filterOpdVisits(rows, { doctorId: 'd2' })[0].id, 'v2')
  assert.equal(filterOpdVisits(rows, { departmentId: 'dep1' })[0].id, 'v1')
  assert.equal(filterOpdVisits(rows, { patientType: 'Out Patient' }).length, 2)
  assert.equal(filterOpdVisits(rows, { status: 'new_patient' })[0].id, 'v1')
  assert.equal(filterOpdVisits(rows, { status: 'nurse_seen' }).length, 0)
})

test('opdStatusCounts use real rows and sortOpdVisits plus pagination work', () => {
  const counts = opdStatusCounts(rows)
  assert.equal(counts.new_patient, 1)
  assert.equal(counts.nurse_seen, 0)
  assert.equal(counts.doctor_seen, 0)
  assert.equal(counts.visit_complete, 1)
  const sorted = sortOpdVisits(rows, 'patient_name', 'asc')
  assert.equal(sorted[0].patient_name, 'Anita Verma')
  const page = paginatePatients(sorted, 1, 1)
  assert.equal(page.rows.length, 1)
  assert.equal(page.pages, 2)
})
