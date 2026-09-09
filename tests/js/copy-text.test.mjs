import { test } from 'node:test'
import assert from 'node:assert/strict'
import { copyText } from '../../resources/js/patients/copyText.js'

test('uses clipboard.writeText when it exists', async () => {
  const written = []
  const ok = await copyText('HCS-26-101', {
    clipboard: { writeText: async (value) => { written.push(value) } },
  })
  assert.equal(ok, true)
  assert.deepEqual(written, ['HCS-26-101'])
})

test('falls back to execCommand when clipboard is missing', async () => {
  const el = {
    value: '',
    style: {},
    setAttribute() {},
    select() {},
  }
  const removed = []
  const document = {
    createElement: () => el,
    body: {
      appendChild() {},
      removeChild(node) { removed.push(node) },
    },
    execCommand: (cmd) => cmd === 'copy',
  }

  const ok = await copyText('MRN-DB-001', { document })
  assert.equal(ok, true)
  assert.equal(el.value, 'MRN-DB-001')
  assert.equal(removed[0], el)
})
