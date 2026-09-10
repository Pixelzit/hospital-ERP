import { test } from 'node:test'
import assert from 'node:assert/strict'
import { generateSlots, validateAppointmentForm, validateCancelReason } from '../../resources/js/patients/appointmentForm.js'

test('generateSlots skips booked times and past slots on the same day', () => {
  const slots = generateSlots('2099-01-15', ['09:00:00', '09:20:00'], new Date('2099-01-15T08:00:00'))
  assert.equal(slots[0], '09:40')
  assert.ok(!slots.includes('09:00'))
  assert.ok(!slots.includes('09:20'))
})

test('validateAppointmentForm requires department doctor date time and type', () => {
  const errors = validateAppointmentForm({})
  assert.equal(errors.department_id, 'Department is required')
  assert.equal(errors.doctor_id, 'Doctor is required')
  assert.equal(errors.appointment_date, 'Date is required')
  assert.equal(errors.start_time, 'Time slot is required')
  assert.equal(errors.appointment_type, 'Appointment type is required')
})

test('validateCancelReason requires a reason', () => {
  assert.equal(validateCancelReason(''), 'Cancellation reason is required')
  assert.equal(validateCancelReason('  Patient request  '), '')
})
