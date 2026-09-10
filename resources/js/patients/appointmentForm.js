function text(value) {
  return String(value ?? '').trim()
}

function pad(n) {
  return String(n).padStart(2, '0')
}

export function generateSlots(date, bookedTimes = [], now = new Date()) {
  const day = text(date)
  if (!day) return []
  const booked = new Set((bookedTimes || []).map((t) => String(t).slice(0, 5)))
  const today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
  const currentMinutes = now.getHours() * 60 + now.getMinutes()
  const slots = []
  for (let minutes = 9 * 60; minutes <= 16 * 60 + 40; minutes += 20) {
    const hh = pad(Math.floor(minutes / 60))
    const mm = pad(minutes % 60)
    const label = `${hh}:${mm}`
    if (booked.has(label)) continue
    if (day === today && minutes <= currentMinutes) continue
    slots.push(label)
  }
  return slots
}

export function validateAppointmentForm(form = {}) {
  const errors = {}
  if (!text(form.department_id)) errors.department_id = 'Department is required'
  if (!text(form.doctor_id)) errors.doctor_id = 'Doctor is required'
  if (!text(form.appointment_date)) errors.appointment_date = 'Date is required'
  if (!text(form.start_time)) errors.start_time = 'Time slot is required'
  if (!text(form.appointment_type)) errors.appointment_type = 'Appointment type is required'
  return errors
}

export function validateCancelReason(reason) {
  return text(reason) ? '' : 'Cancellation reason is required'
}
