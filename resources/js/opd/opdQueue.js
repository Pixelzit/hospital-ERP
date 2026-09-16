function text(value) {
  return String(value ?? '').trim().toLowerCase()
}

export function opdStatusKey(status) {
  const s = text(status)
  if (['nurse_seen', 'nurse seen', 'nursing'].includes(s)) return 'nurse_seen'
  if (['doctor_seen', 'doctor seen'].includes(s)) return 'doctor_seen'
  if (['closed', 'completed', 'complete', 'visit_complete', 'fulfilled'].includes(s)) return 'visit_complete'
  if (['open', 'scheduled', 'new', 'new_patient', 'arrived', 'active'].includes(s)) return 'new_patient'
  return s
}

export function opdStatusLabel(status) {
  const key = opdStatusKey(status)
  if (key === 'new_patient') return 'New Patient'
  if (key === 'nurse_seen') return 'Nurse Seen'
  if (key === 'doctor_seen') return 'Doctor Seen'
  if (key === 'visit_complete') return 'Visit Complete'
  return status || '—'
}

export function walkInLabel(appointmentNumber) {
  const value = String(appointmentNumber ?? '').trim()
  return value || 'Walk-in'
}

export function filterOpdVisits(visits, filters = {}) {
  const list = Array.isArray(visits) ? visits : []
  const search = text(filters.search)
  const doctorId = text(filters.doctorId)
  const departmentId = text(filters.departmentId)
  const patientType = text(filters.patientType)
  const status = text(filters.status)

  return list.filter((row) => {
    if (search) {
      const hay = [row.patient_name, row.uhid, row.visit_number, row.phone, row.appointment_number]
        .map(text)
        .join(' ')
      if (!hay.includes(search)) return false
    }
    if (doctorId && doctorId !== 'all' && text(row.doctor_id) !== doctorId) return false
    if (departmentId && departmentId !== 'all' && text(row.department_id) !== departmentId) return false
    if (patientType && patientType !== 'all' && text(row.patient_type) !== patientType) return false
    if (status && status !== 'all' && opdStatusKey(row.status) !== status) return false
    return true
  })
}

export function opdStatusCounts(visits) {
  const counts = {
    new_patient: 0,
    nurse_seen: 0,
    doctor_seen: 0,
    visit_complete: 0,
  }
  for (const row of Array.isArray(visits) ? visits : []) {
    const key = opdStatusKey(row.status)
    if (Object.prototype.hasOwnProperty.call(counts, key)) counts[key] += 1
  }
  return counts
}

export function sortOpdVisits(visits, key = 'started_at', direction = 'desc') {
  const list = [...(Array.isArray(visits) ? visits : [])]
  const dir = text(direction) === 'asc' ? 1 : -1
  const field = key || 'started_at'
  list.sort((a, b) => {
    const left = a?.[field]
    const right = b?.[field]
    if (left == null && right == null) return 0
    if (left == null) return 1
    if (right == null) return -1
    if (typeof left === 'number' && typeof right === 'number') return (left - right) * dir
    return String(left).localeCompare(String(right), undefined, { numeric: true, sensitivity: 'base' }) * dir
  })
  return list
}
