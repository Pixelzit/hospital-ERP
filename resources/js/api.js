export async function api(path, options = {}) {
  const res = await fetch(`${window.location.origin}/api${path}`, {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(options.headers || {}),
    },
    ...options,
  })

  const data = await res.json().catch(() => ({}))

  if (!res.ok) {
    const message = data.message || data.error || `Request failed (${res.status})`
    const error = new Error(message)
    error.payload = data
    error.status = res.status
    throw error
  }

  return data
}
