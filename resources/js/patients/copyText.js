function copyWithTextarea(text, document) {
  if (!document?.body?.appendChild) return false

  const el = document.createElement('textarea')
  el.value = String(text ?? '')
  el.setAttribute('readonly', '')
  el.style.position = 'fixed'
  el.style.left = '-9999px'
  document.body.appendChild(el)
  el.select()
  const ok = document.execCommand('copy')
  document.body.removeChild(el)
  return !!ok
}

export async function copyText(text, env = {}) {
  const clipboard = env.clipboard ?? globalThis.navigator?.clipboard
  const document = env.document ?? globalThis.document

  if (clipboard?.writeText) {
    await clipboard.writeText(String(text ?? ''))
    return true
  }

  return copyWithTextarea(text, document)
}
