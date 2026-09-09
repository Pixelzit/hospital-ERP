function text(value) {
  return String(value || '').trim().toLowerCase()
}

function dateOnly(value) {
  if (!value) return null
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return null
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function matchesAgeGroup(age, group) {
  if (!group || group === 'all') return true
  if (age === null || age === undefined || age === '') return false
  const n = Number(age)
  if (Number.isNaN(n)) return false
  if (group === '0-17') return n <= 17
  if (group === '18-40') return n >= 18 && n <= 40
  if (group === '41-60') return n >= 41 && n <= 60
  if (group === '61+') return n >= 61
  return true
}

export function filterPatients(patients, filters = {}) {
  const list = Array.isArray(patients) ? patients : []
  const search = text(filters.search)
  const status = text(filters.status)
  const gender = text(filters.gender)
  const from = filters.registeredFrom || ''
  const to = filters.registeredTo || ''

  return list.filter((patient) => {
    if (search) {
      const hay = [patient.name, patient.mrn, patient.phone, patient.email]
        .map(text)
        .join(' ')
      if (!hay.includes(search)) return false
    }

    if (status && status !== 'all' && text(patient.status) !== status) return false

    if (gender && gender !== 'all' && text(patient.gender) !== gender) return false

    if (!matchesAgeGroup(patient.age, filters.ageGroup)) return false

    const registered = dateOnly(patient.created_at)
    if (from && (!registered || registered < from)) return false
    if (to && (!registered || registered > to)) return false

    return true
  })
}

export function paginatePatients(patients, page = 1, perPage = 10) {
  const list = Array.isArray(patients) ? patients : []
  const size = Math.max(1, Number(perPage) || 10)
  const total = list.length
  const pages = Math.max(1, Math.ceil(total / size))
  const current = Math.min(Math.max(1, Number(page) || 1), pages)
  const start = (current - 1) * size

  return {
    rows: list.slice(start, start + size),
    total,
    page: current,
    perPage: size,
    pages,
  }
}
