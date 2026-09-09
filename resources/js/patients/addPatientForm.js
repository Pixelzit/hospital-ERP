function text(value) {
  return String(value ?? '').trim()
}

function digits(value) {
  return text(value).replace(/\D/g, '')
}

function dateOnly(value) {
  const raw = text(value)
  if (!raw) return ''
  return raw.slice(0, 10)
}

export function splitFullName(fullName) {
  const parts = text(fullName).split(/\s+/).filter(Boolean)
  return {
    first_name: parts[0] || '',
    last_name: parts.slice(1).join(' '),
  }
}

export function validatePatientForm(form = {}) {
  const errors = {}
  const name = text(form.full_name)
  const dob = dateOnly(form.date_of_birth)
  const gender = text(form.gender)
  const phone = digits(form.phone)
  const email = text(form.email)
  const address = text(form.address_line_1)
  const emergencyName = text(form.emergency_name)
  const emergencyPhone = digits(form.emergency_phone)
  const idType = text(form.id_type)
  const idNumber = text(form.id_number)

  if (!name) errors.full_name = 'Full name is required'
  if (!dob) errors.date_of_birth = 'Date of birth is required'
  else if (Number.isNaN(new Date(dob).getTime()) || dob > new Date().toISOString().slice(0, 10)) {
    errors.date_of_birth = 'Enter a valid date of birth'
  }
  if (!gender) errors.gender = 'Gender is required'
  if (!phone) errors.phone = 'Mobile number is required'
  else if (phone.length < 10) errors.phone = 'Enter a valid mobile number'
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = 'Enter a valid email'
  if (!address) errors.address_line_1 = 'Address line 1 is required'
  if (!emergencyName) errors.emergency_name = 'Emergency contact name is required'
  if (!text(form.emergency_relationship)) errors.emergency_relationship = 'Relationship is required'
  if (!emergencyPhone) errors.emergency_phone = 'Emergency phone is required'
  else if (emergencyPhone.length < 10) errors.emergency_phone = 'Enter a valid emergency phone'
  if ((idType && !idNumber) || (!idType && idNumber)) {
    errors.id_number = 'ID type and ID number are both required'
  }

  return errors
}

export function findDuplicatePatients(patients, form = {}) {
  const list = Array.isArray(patients) ? patients : []
  const { first_name, last_name } = splitFullName(form.full_name)
  const name = `${first_name} ${last_name}`.trim().toLowerCase()
  const dob = dateOnly(form.date_of_birth)
  const phone = digits(form.phone)
  if (!name || !dob || !phone) return []

  return list.filter((patient) => {
    const patientName = text(patient.name || `${patient.first_name || ''} ${patient.last_name || ''}`).toLowerCase()
    const patientDob = dateOnly(patient.date_of_birth)
    const patientPhone = digits(patient.phone)
    return patientName === name && patientDob === dob && patientPhone === phone
  })
}
