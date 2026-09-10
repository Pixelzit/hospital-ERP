export async function api(path, options = {}) {
  const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(options.headers || {}),
  }

  if (options.body && !headers['Content-Type'] && !(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json'
  }

  const res = await fetch(`${window.location.origin}/api${path}`, {
    credentials: 'include',
    ...options,
    headers,
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
