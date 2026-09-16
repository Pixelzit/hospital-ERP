<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
      <div>
        <h2 class="text-xl font-semibold">Billing &amp; Collection</h2>
        <p class="text-[11px] text-slate-400 mt-0.5">Collect payments against hospital invoices.</p>
      </div>
      <button
        type="button"
        class="h-9 px-4 rounded-xl text-[13px] font-semibold bg-[#2f86f3] text-white hover:bg-[#256fd1]"
        @click="load"
      >
        Refresh
      </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="rounded-2xl border border-slate-100 p-5">
        <div class="text-sm text-slate-500">Today collection</div>
        <div class="text-2xl font-bold mt-1">{{ money(summary.today_collection) }}</div>
      </div>
      <div class="rounded-2xl border border-slate-100 p-5">
        <div class="text-sm text-slate-500">This month</div>
        <div class="text-2xl font-bold mt-1">{{ money(summary.month_collection) }}</div>
      </div>
      <div class="rounded-2xl border border-slate-100 p-5">
        <div class="text-sm text-slate-500">Outstanding</div>
        <div class="text-2xl font-bold mt-1">{{ money(summary.outstanding_balance) }}</div>
      </div>
    </div>

    <div class="flex flex-wrap gap-3 mb-4 text-[12px]">
      <label class="text-slate-500">
        Date
        <input v-model="filters.date" type="date" class="mt-0.5 block h-8 min-w-[132px] rounded-lg px-2 bg-[#eef3fb] outline-none focus:ring-2 focus:ring-[#2f86f3]/30">
      </label>
      <label class="text-slate-500">
        Status
        <select v-model="filters.status" class="mt-0.5 block h-8 rounded-lg px-2 bg-[#eef3fb] outline-none">
          <option value="all">All</option>
          <option value="issued">Issued</option>
          <option value="paid">Paid</option>
        </select>
      </label>
      <label class="text-slate-500 flex-1 min-w-[180px]">
        Search
        <input
          v-model="filters.search"
          type="search"
          placeholder="Invoice # / MRN / name"
          class="mt-0.5 block w-full h-8 rounded-lg px-2 bg-[#eef3fb] outline-none focus:ring-2 focus:ring-[#2f86f3]/30"
        >
      </label>
    </div>

    <p v-if="error" class="mb-3 text-[12px] text-red-600">{{ error }}</p>
    <p v-if="loading" class="mb-3 text-[12px] text-slate-400">Loading invoices…</p>

    <div class="overflow-x-auto rounded-2xl border border-slate-100">
      <table class="min-w-full text-left text-[12px]">
        <thead class="bg-[#f8fafc] text-slate-500">
          <tr>
            <th class="px-3 py-2 font-semibold">Invoice</th>
            <th class="px-3 py-2 font-semibold">Patient</th>
            <th class="px-3 py-2 font-semibold">Date</th>
            <th class="px-3 py-2 font-semibold text-right">Total</th>
            <th class="px-3 py-2 font-semibold text-right">Paid</th>
            <th class="px-3 py-2 font-semibold text-right">Balance</th>
            <th class="px-3 py-2 font-semibold">Status</th>
            <th class="px-3 py-2 font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && !rows.length">
            <td colspan="8" class="px-3 py-6 text-center text-slate-400">No invoices found.</td>
          </tr>
          <tr v-for="row in rows" :key="row.id" class="border-t border-slate-50">
            <td class="px-3 py-2 font-medium text-slate-700">{{ row.invoice_number }}</td>
            <td class="px-3 py-2">
              <div>{{ row.patient?.name || '—' }}</div>
              <div class="text-[11px] text-slate-400">{{ row.patient?.mrn || '' }}</div>
            </td>
            <td class="px-3 py-2 whitespace-nowrap text-slate-600">{{ formatDate(row.date) }}</td>
            <td class="px-3 py-2 text-right">{{ money(row.total) }}</td>
            <td class="px-3 py-2 text-right">{{ money(row.amount_paid) }}</td>
            <td class="px-3 py-2 text-right font-semibold">{{ money(row.balance) }}</td>
            <td class="px-3 py-2">
              <span
                class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold"
                :class="row.status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
              >{{ row.status }}</span>
            </td>
            <td class="px-3 py-2 whitespace-nowrap">
              <button
                v-if="Number(row.balance) > 0 && row.status !== 'paid'"
                type="button"
                class="text-[#2f86f3] font-semibold mr-2 hover:underline"
                @click="openPay(row)"
              >Collect</button>
              <a
                v-if="row.print_url"
                :href="row.print_url"
                target="_blank"
                rel="noopener"
                class="text-slate-500 hover:underline"
              >Print</a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.total > meta.per_page" class="flex items-center justify-between mt-4 text-[12px] text-slate-500">
      <span>{{ meta.total }} invoices</span>
      <div class="flex gap-2">
        <button type="button" class="px-3 h-8 rounded-lg border border-slate-200 disabled:opacity-40" :disabled="filters.page <= 1" @click="filters.page -= 1">Prev</button>
        <span>Page {{ filters.page }}</span>
        <button type="button" class="px-3 h-8 rounded-lg border border-slate-200 disabled:opacity-40" :disabled="filters.page * meta.per_page >= meta.total" @click="filters.page += 1">Next</button>
      </div>
    </div>

    <div v-if="payOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" @click.self="payOpen = false">
      <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
        <h3 class="text-lg font-semibold mb-1">Collect payment</h3>
        <p class="text-[12px] text-slate-500 mb-4">
          {{ payRow?.invoice_number }} · balance {{ money(payRow?.balance) }}
        </p>
        <label class="block text-[12px] text-slate-500 mb-3">
          Amount
          <input v-model.number="payForm.amount" type="number" min="0.01" step="0.01" class="mt-1 w-full h-10 rounded-xl px-3 bg-[#eef3fb] outline-none focus:ring-2 focus:ring-[#2f86f3]/30">
        </label>
        <label class="block text-[12px] text-slate-500 mb-3">
          Method
          <select v-model="payForm.payment_method" class="mt-1 w-full h-10 rounded-xl px-3 bg-[#eef3fb] outline-none">
            <option value="cash">Cash</option>
            <option value="upi">UPI</option>
            <option value="card">Card</option>
            <option value="neft">NEFT</option>
          </select>
        </label>
        <label class="block text-[12px] text-slate-500 mb-4">
          Reference (optional)
          <input v-model="payForm.transaction_reference" type="text" class="mt-1 w-full h-10 rounded-xl px-3 bg-[#eef3fb] outline-none">
        </label>
        <p v-if="payError" class="mb-3 text-[12px] text-red-600">{{ payError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="h-9 px-4 rounded-xl text-[13px] border border-slate-200" @click="payOpen = false">Cancel</button>
          <button type="button" class="h-9 px-4 rounded-xl text-[13px] font-semibold bg-[#2f86f3] text-white disabled:opacity-50" :disabled="paying" @click="submitPay">
            {{ paying ? 'Saving…' : 'Record payment' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { api } from '../api'

const loading = ref(false)
const error = ref('')
const rows = ref([])
const summary = reactive({
  today_collection: 0,
  month_collection: 0,
  outstanding_balance: 0,
})
const meta = reactive({ page: 1, per_page: 25, total: 0 })

const filters = reactive({
  date: '',
  status: 'all',
  search: '',
  page: 1,
  per_page: 25,
})

const payOpen = ref(false)
const payRow = ref(null)
const paying = ref(false)
const payError = ref('')
const payForm = reactive({
  amount: 0,
  payment_method: 'cash',
  transaction_reference: '',
})

function money(value) {
  const n = Number(value || 0)
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(n)
}

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value).slice(0, 10)
  return d.toLocaleDateString('en-IN')
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.date) params.set('date', filters.date)
    if (filters.status && filters.status !== 'all') params.set('status', filters.status)
    if (filters.search.trim()) params.set('search', filters.search.trim())
    params.set('page', String(filters.page))
    params.set('per_page', String(filters.per_page))
    const data = await api(`/billing/invoices?${params.toString()}`)
    rows.value = Array.isArray(data.data) ? data.data : []
    Object.assign(summary, data.summary || {})
    Object.assign(meta, data.meta || {})
  } catch (e) {
    rows.value = []
    error.value = e.message || 'Unable to load invoices.'
  } finally {
    loading.value = false
  }
}

function openPay(row) {
  payRow.value = row
  payForm.amount = Number(row.balance) || 0
  payForm.payment_method = 'cash'
  payForm.transaction_reference = ''
  payError.value = ''
  payOpen.value = true
}

async function submitPay() {
  if (!payRow.value) return
  paying.value = true
  payError.value = ''
  try {
    await api(`/billing/invoices/${payRow.value.id}/payments`, {
      method: 'POST',
      body: JSON.stringify({
        amount: payForm.amount,
        payment_method: payForm.payment_method,
        transaction_reference: payForm.transaction_reference || null,
      }),
    })
    payOpen.value = false
    await load()
  } catch (e) {
    payError.value = e.message || 'Unable to record payment.'
  } finally {
    paying.value = false
  }
}

let searchTimer
watch(() => [filters.date, filters.status, filters.page], load)
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    filters.page = 1
    load()
  }, 300)
})

onMounted(load)
</script>
