function text(value) {
  return String(value ?? '').trim()
}

export function patientInitials(name) {
  const parts = text(name).split(/\s+/).filter(Boolean)
  if (!parts.length) return '?'
  if (parts.length === 1) return parts[0].slice(0, 1).toUpperCase()
  return (parts[0].slice(0, 1) + parts[parts.length - 1].slice(0, 1)).toUpperCase()
}

export function formatAddress(address) {
  if (!address || typeof address !== 'object') return '—'
  const line = [address.address_line_1, address.address_line_2].map(text).filter(Boolean).join(', ')
  const cityState = [address.city, address.state].map(text).filter(Boolean).join(', ')
  const pin = text(address.postal_code)
  const parts = [line, cityState].filter(Boolean)
  if (!parts.length && !pin) return '—'
  if (pin) parts.push(cityState || line ? `${parts.length ? '' : ''}${pin}`.replace(/^/, pin) : pin)
  const head = [line, cityState].filter(Boolean).join(', ')
  if (!head) return pin || '—'
  return pin ? `${head} - ${pin}` : head
}

function appointmentBucket(row, today = new Date().toISOString().slice(0, 10)) {
  const status = text(row?.status).toLowerCase()
  if (['cancelled', 'canceled', 'no_show', 'no-show'].includes(status)) return 'cancelled'
  if (['completed', 'closed', 'fulfilled', 'visited'].includes(status)) return 'completed'
  const date = text(row?.appointment_date).slice(0, 10)
  if (date && date < today) return 'completed'
  return 'upcoming'
}

export function filterAppointments(rows, tab = 'upcoming') {
  const list = Array.isArray(rows) ? rows : []
  const wanted = text(tab).toLowerCase() || 'upcoming'
  return list.filter((row) => appointmentBucket(row) === wanted)
}

function documentCategory(row) {
  const type = text(row?.document_type).toLowerCase()
  if (type.includes('prescription')) return 'prescriptions'
  if (type.includes('lab')) return 'lab'
  if (type.includes('radio') || type.includes('xray') || type.includes('scan')) return 'radiology'
  if (type.includes('discharge')) return 'discharge'
  return 'uploaded'
}

export function filterDocuments(rows, category = 'all') {
  const list = Array.isArray(rows) ? rows : []
  const wanted = text(category).toLowerCase() || 'all'
  if (wanted === 'all') return list
  return list.filter((row) => documentCategory(row) === wanted)
}

export function profileToForm(patient = {}) {
  const address = patient.address || {}
  const emergency = patient.emergency || {}
  const identifier = patient.identifier || {}
  return {
    full_name: text(patient.name || `${patient.first_name || ''} ${patient.last_name || ''}`),
    date_of_birth: text(patient.date_of_birth).slice(0, 10),
    gender: text(patient.gender),
    blood_group: text(patient.blood_group),
    marital_status: text(patient.marital_status),
    phone: text(patient.phone),
    email: text(patient.email),
    address_line_1: text(address.address_line_1),
    address_line_2: text(address.address_line_2),
    city: text(address.city),
    state: text(address.state),
    postal_code: text(address.postal_code),
    emergency_name: text(emergency.name),
    emergency_relationship: text(emergency.relationship),
    emergency_phone: text(emergency.phone),
    id_type: text(identifier.type || identifier.identifier_type),
    id_number: text(identifier.number || identifier.identifier_value),
    id_proof: null,
    allergies: Array.isArray(patient.allergies) ? patient.allergies.join(', ') : text(patient.allergies),
    conditions: Array.isArray(patient.conditions) ? patient.conditions.join(', ') : text(patient.conditions),
    medications: Array.isArray(patient.medications) ? patient.medications.join(', ') : text(patient.medications),
    notes: text(patient.notes),
  }
}
