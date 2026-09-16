<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
      <div>
        <h2 class="text-xl font-semibold">Pharmacy POS</h2>
        <p class="text-[11px] text-slate-400 mt-0.5">Dispense from seeded batches. Catalog stays under Pharmacy.</p>
      </div>
      <button type="button" class="h-9 px-4 rounded-xl text-[13px] font-semibold border border-slate-200 text-slate-700 hover:bg-slate-50" @click="reload">Refresh</button>
    </div>

    <p v-if="error" class="mb-3 text-[12px] text-red-600">{{ error }}</p>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
      <div class="xl:col-span-7">
        <div class="flex flex-wrap items-center gap-2 mb-2">
          <h3 class="text-[13px] font-semibold text-slate-700">Sellable stock</h3>
          <input v-model="search" type="search" placeholder="Search medicine / batch" class="ml-auto h-8 rounded-lg px-2 text-[12px] bg-[#eef3fb] outline-none min-w-[180px]">
        </div>
        <p v-if="loading" class="mb-2 text-[12px] text-slate-400">Loading stock...</p>
        <div class="overflow-x-auto rounded-2xl border border-slate-100 max-h-[480px] overflow-y-auto">
          <table class="min-w-full text-left text-[12px]">
            <thead class="bg-[#f8fafc] text-slate-500 sticky top-0">
              <tr>
                <th class="px-3 py-2 font-semibold">Medicine</th>
                <th class="px-3 py-2 font-semibold">Batch</th>
                <th class="px-3 py-2 font-semibold text-right">Qty</th>
                <th class="px-3 py-2 font-semibold text-right">Price</th>
                <th class="px-3 py-2 font-semibold">Expiry</th>
                <th class="px-3 py-2 font-semibold"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!loading && !filteredStock.length">
                <td colspan="6" class="px-3 py-6 text-center text-slate-400">No sellable stock. Run PharmacyPosInventorySeeder.</td>
              </tr>
              <tr v-for="row in filteredStock" :key="row.batch_id" class="border-t border-slate-50" :class="selectedBatchId === row.batch_id ? 'bg-sky-50' : ''">
                <td class="px-3 py-2">
                  <div class="font-medium">{{ row.medicine?.generic_name || 'Medicine' }}</div>
                  <div class="text-[11px] text-slate-400">{{ row.medicine?.brand_name || '' }} {{ row.medicine?.strength || '' }}</div>
                </td>
                <td class="px-3 py-2 font-medium">{{ row.batch_number }}</td>
                <td class="px-3 py-2 text-right">{{ row.quantity }}</td>
                <td class="px-3 py-2 text-right">{{ money(row.selling_price) }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ row.expiry_date || '—' }}</td>
                <td class="px-3 py-2">
                  <button type="button" class="text-[#2f86f3] font-semibold hover:underline" @click="selectRow(row)">Select</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="xl:col-span-5 space-y-4">
        <div class="rounded-2xl border border-slate-100 p-4">
          <h3 class="text-[13px] font-semibold text-slate-700 mb-3">Dispense</h3>
          <p v-if="!selected" class="text-[12px] text-slate-400 mb-2">Select a stock line to dispense.</p>
          <template v-else>
            <p class="text-[12px] text-slate-600 mb-2">
              <span class="font-semibold">{{ selected.medicine?.generic_name }}</span>
              · {{ selected.batch_number }} · on hand {{ selected.quantity }}
            </p>
            <label class="block text-[12px] text-slate-500 mb-3">
              Quantity
              <input v-model.number="dispenseQty" type="number" min="0.001" step="0.001" class="mt-1 w-full h-10 rounded-xl px-3 bg-[#eef3fb] outline-none">
            </label>
            <p v-if="dispenseError" class="mb-2 text-[12px] text-red-600">{{ dispenseError }}</p>
            <p v-if="dispenseOk" class="mb-2 text-[12px] text-emerald-700">{{ dispenseOk }}</p>
            <button type="button" class="h-9 px-4 rounded-xl text-[13px] font-semibold bg-[#2f86f3] text-white disabled:opacity-50" :disabled="busy" @click="submitDispense">{{ busy ? 'Saving...' : 'Dispense' }}</button>
          </template>
        </div>

        <div class="rounded-2xl border border-slate-100 p-4">
          <h3 class="text-[13px] font-semibold text-slate-700 mb-2">Recent dispenses</h3>
          <div class="space-y-2 max-h-[280px] overflow-y-auto">
            <p v-if="!movements.length" class="text-[12px] text-slate-400 text-center py-4">No dispense movements yet.</p>
            <div v-for="m in movements" :key="m.id" class="rounded-xl border border-slate-100 px-3 py-2 text-[12px]">
              <div class="font-medium">{{ m.medicine?.generic_name || 'Medicine' }} · {{ m.batch_number || '—' }}</div>
              <div class="text-[11px] text-slate-400">{{ formatDateTime(m.created_at) }} · qty {{ m.quantity }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../api'

const loading = ref(false)
const busy = ref(false)
const error = ref('')
const stock = ref([])
const movements = ref([])
const search = ref('')
const selectedBatchId = ref('')
const selected = ref(null)
const dispenseQty = ref(1)
const dispenseError = ref('')
const dispenseOk = ref('')

const filteredStock = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return stock.value
  return stock.value.filter((row) => {
    const hay = [
      row.medicine?.generic_name,
      row.medicine?.brand_name,
      row.batch_number,
      row.medicine?.strength,
    ].filter(Boolean).join(' ').toLowerCase()
    return hay.includes(q)
  })
})

function money(value) {
  if (value === null || value === undefined || value === '') return '—'
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(value))
}
function formatDateTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleString('en-IN')
}

function selectRow(row) {
  selectedBatchId.value = row.batch_id
  selected.value = row
  dispenseQty.value = 1
  dispenseError.value = ''
  dispenseOk.value = ''
}

async function loadStock() {
  loading.value = true
  error.value = ''
  try {
    const data = await api('/pharmacy/pos/stock')
    stock.value = Array.isArray(data.data) ? data.data : []
    if (selectedBatchId.value) {
      const still = stock.value.find((r) => r.batch_id === selectedBatchId.value)
      selected.value = still || null
      if (!still) selectedBatchId.value = ''
    }
  } catch (e) {
    error.value = e.message || 'Unable to load stock.'
  } finally {
    loading.value = false
  }
}

async function loadMovements() {
  try {
    const data = await api('/pharmacy/pos/movements')
    movements.value = Array.isArray(data.data) ? data.data : []
  } catch (e) {
    // keep prior list; surface only if no stock error yet
    if (!error.value) error.value = e.message || 'Unable to load movements.'
  }
}

async function reload() {
  await Promise.all([loadStock(), loadMovements()])
}

async function submitDispense() {
  dispenseError.value = ''
  dispenseOk.value = ''
  if (!selected.value) {
    dispenseError.value = 'Select a stock line first.'
    return
  }
  const qty = Number(dispenseQty.value)
  if (!(qty > 0)) {
    dispenseError.value = 'Enter a quantity greater than zero.'
    return
  }
  if (qty > Number(selected.value.quantity)) {
    dispenseError.value = 'Quantity exceeds on-hand stock.'
    return
  }
  busy.value = true
  try {
    const res = await api('/pharmacy/pos/dispense', {
      method: 'POST',
      body: JSON.stringify({
        lines: [{ batch_id: selected.value.batch_id, quantity: qty }],
      }),
    })
    const remaining = res?.data?.remaining?.[0]?.quantity
    dispenseOk.value = remaining === undefined || remaining === null
      ? 'Dispense recorded.'
      : `Dispense recorded. Remaining batch qty: ${remaining}`
    await reload()
  } catch (e) {
    dispenseError.value = e.message || 'Unable to dispense.'
  } finally {
    busy.value = false
  }
}

onMounted(reload)
</script>
