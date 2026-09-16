<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
      <div>
        <h2 class="text-xl font-semibold">IPD &amp; Bed Management</h2>
        <p class="text-[11px] text-slate-400 mt-0.5">Bed board, admit, and IPD billing stages.</p>
      </div>
      <div class="flex gap-2">
        <button type="button" class="h-9 px-4 rounded-xl text-[13px] font-semibold border border-slate-200 text-slate-700 hover:bg-slate-50" @click="loadAll">Refresh</button>
        <button type="button" class="h-9 px-4 rounded-xl text-[13px] font-semibold bg-[#2f86f3] text-white hover:bg-[#256fd1]" @click="openAdmit">Admit</button>
      </div>
    </div>

    <div class="flex flex-wrap gap-3 mb-4 text-[12px]">
      <label class="text-slate-500">
        Ward
        <select v-model="wardFilter" class="mt-0.5 block h-8 rounded-lg px-2 bg-[#eef3fb] outline-none min-w-[180px]">
          <option value="">All wards</option>
          <option v-for="w in wards" :key="w.id" :value="w.id">{{ w.name }}</option>
        </select>
      </label>
      <label class="text-slate-500">
        Bed status
        <select v-model="bedStatusFilter" class="mt-0.5 block h-8 rounded-lg px-2 bg-[#eef3fb] outline-none">
          <option value="all">All</option>
          <option value="available">Available</option>
          <option value="occupied">Occupied</option>
          <option value="maintenance">Maintenance</option>
        </select>
      </label>
    </div>

    <p v-if="error" class="mb-3 text-[12px] text-red-600">{{ error }}</p>
    <p v-if="loading" class="mb-3 text-[12px] text-slate-400">Loading…</p>

    <h3 class="text-[13px] font-semibold text-slate-700 mb-2">Bed board</h3>
    <div class="overflow-x-auto rounded-2xl border border-slate-100 mb-6">
      <table class="min-w-full text-left text-[12px]">
        <thead class="bg-[#f8fafc] text-slate-500">
          <tr>
            <th class="px-3 py-2 font-semibold">Ward</th>
            <th class="px-3 py-2 font-semibold">Room</th>
            <th class="px-3 py-2 font-semibold">Bed</th>
            <th class="px-3 py-2 font-semibold">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && !filteredBeds.length">
            <td colspan="4" class="px-3 py-6 text-center text-slate-400">No beds found. Run IPD bed seeder.</td>
          </tr>
          <tr v-for="bed in filteredBeds" :key="bed.id" class="border-t border-slate-50">
            <td class="px-3 py-2">{{ bed.ward?.name || '—' }}</td>
            <td class="px-3 py-2">{{ bed.room?.room_number || '—' }}</td>
            <td class="px-3 py-2 font-medium">{{ bed.bed_number }}</td>
            <td class="px-3 py-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold" :class="bedStatusClass(bed.status)">{{ bed.status }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <h3 class="text-[13px] font-semibold text-slate-700 mb-2">Active admissions</h3>
    <div class="overflow-x-auto rounded-2xl border border-slate-100 mb-6">
      <table class="min-w-full text-left text-[12px]">
        <thead class="bg-[#f8fafc] text-slate-500">
          <tr>
            <th class="px-3 py-2 font-semibold">Admission #</th>
            <th class="px-3 py-2 font-semibold">Patient</th>
            <th class="px-3 py-2 font-semibold">Type</th>
            <th class="px-3 py-2 font-semibold">Admitted</th>
            <th class="px-3 py-2 font-semibold">Status</th>
            <th class="px-3 py-2 font-semibold">Bill</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && !admissions.length">
            <td colspan="6" class="px-3 py-6 text-center text-slate-400">No active admissions.</td>
          </tr>
          <tr
            v-for="row in admissions"
            :key="row.id"
            class="border-t border-slate-50 cursor-pointer"
            :class="selectedAdmissionId === row.id ? 'bg-sky-50' : ''"
            @click="selectAdmission(row)"
          >
            <td class="px-3 py-2 font-medium">{{ row.admission_number }}</td>
            <td class="px-3 py-2">
              <div>{{ row.patient?.name || '—' }}</div>
              <div class="text-[11px] text-slate-400">{{ row.patient?.mrn || '' }}</div>
            </td>
            <td class="px-3 py-2">{{ row.admission_type }}</td>
            <td class="px-3 py-2 whitespace-nowrap">{{ formatDateTime(row.admitted_at) }}</td>
            <td class="px-3 py-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-700">{{ row.status }}</span>
            </td>
            <td class="px-3 py-2">
              <button type="button" class="text-[#2f86f3] font-semibold hover:underline" @click.stop="selectAdmission(row)">Open</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="selectedAdmission" class="rounded-2xl border border-slate-100 p-4 mb-2">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
          <h3 class="text-[13px] font-semibold text-slate-700">Bill panel</h3>
          <p class="text-[11px] text-slate-400">
            {{ selectedAdmission.admission_number }} · {{ selectedAdmission.patient?.name || 'Patient' }}
          </p>
        </div>
        <button type="button" class="h-8 px-3 rounded-lg text-[12px] font-semibold bg-[#2f86f3] text-white" @click="openCreateInvoice">Create interim invoice</button>
      </div>

      <p v-if="billError" class="mb-2 text-[12px] text-red-600">{{ billError }}</p>
      <p v-if="billLoading" class="mb-2 text-[12px] text-slate-400">Loading invoices…</p>

      <div class="overflow-x-auto rounded-xl border border-slate-100">
        <table class="min-w-full text-left text-[12px]">
          <thead class="bg-[#f8fafc] text-slate-500">
            <tr>
              <th class="px-3 py-2 font-semibold">Invoice</th>
              <th class="px-3 py-2 font-semibold">Status</th>
              <th class="px-3 py-2 font-semibold text-right">Total</th>
              <th class="px-3 py-2 font-semibold text-right">Paid</th>
              <th class="px-3 py-2 font-semibold text-right">Balance</th>
              <th class="px-3 py-2 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!billLoading && !invoices.length">
              <td colspan="6" class="px-3 py-5 text-center text-slate-400">No IPD invoices for this admission.</td>
            </tr>
            <tr v-for="inv in invoices" :key="inv.id" class="border-t border-slate-50">
              <td class="px-3 py-2 font-medium">{{ inv.invoice_number }}</td>
              <td class="px-3 py-2">
                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700">{{ inv.status }}</span>
              </td>
              <td class="px-3 py-2 text-right">{{ money(inv.total) }}</td>
              <td class="px-3 py-2 text-right">{{ money(inv.amount_paid) }}</td>
              <td class="px-3 py-2 text-right font-semibold">{{ money(inv.balance) }}</td>
              <td class="px-3 py-2 whitespace-nowrap">
                <button
                  v-if="inv.next_status"
                  type="button"
                  class="text-[#2f86f3] font-semibold mr-2 hover:underline"
                  :disabled="billBusy"
                  @click="advanceInvoice(inv)"
                >Advance to {{ inv.next_status }}</button>
                <button
                  v-if="inv.status === 'final' && Number(inv.balance) > 0"
                  type="button"
                  class="text-emerald-700 font-semibold hover:underline"
                  :disabled="billBusy"
                  @click="openPay(inv)"
                >Collect</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Admit modal -->
    <div v-if="admitOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" @click.self="admitOpen = false">
      <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
        <h3 class="text-lg font-semibold mb-1">Admit patient</h3>
        <p class="text-[12px] text-slate-500 mb-4">Creates IPD encounter, admission, and bed assignment.</p>
        <label class="block text-[12px] text-slate-500 mb-3">
          Patient
          <select v-model="admitForm.patient_id" class="mt-1 w-full h-10 rounded-xl px-3 bg-[#eef3fb] outline-none">
            <option value="">Select patient</option>
            <option v-for="p in patients" :key="p.id" :value="p.id">{{ patientLabel(p) }}</option>
          </select>
        </label>
        <label class="block text-[12px] text-slate-500 mb-3">
          Available bed
          <select v-model="admitForm.bed_id" class="mt-1 w-full h-10 rounded-xl px-3 bg-[#eef3fb] outline-none">
            <option value="">Select bed</option>
            <option v-for="b in availableBeds" :key="b.id" :value="b.id">{{ b.ward?.name }} / {{ b.room?.room_number }} / {{ b.bed_number }}</option>
          </select>
        </label>
        <label class="block text-[12px] text-slate-500 mb-3">
          Admission type
          <select v-model="admitForm.admission_type" class="mt-1 w-full h-10 rounded-xl px-3 bg-[#eef3fb] outline-none">
            <option value="routine">Routine</option>
            <option value="emergency">Emergency</option>
            <option value="transfer">Transfer</option>
          </select>
        </label>
        <label class="block text-[12px] text-slate-500 mb-4">
          Reason (optional)
          <input v-model="admitForm.reason" type="text" class="mt-1 w-full h-10 rounded-xl px-3 bg-[#eef3fb] outline-none">
        </label>
        <p v-if="admitError" class="mb-3 text-[12px] text-red-600">{{ admitError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="h-9 px-4 rounded-xl text-[13px] border border-slate-200" @click="admitOpen = false">Cancel</button>
          <button type="button" class="h-9 px-4 rounded-xl text-[13px] font-semibold bg-[#2f86f3] text-white disabled:opacity-50" :disabled="admitting" @click="submitAdmit">{{ admitting ? 'Saving…' : 'Admit' }}</button>
        </div>
      </div>
    </div>

    <!-- Create interim invoice modal -->
    <div v-if="invoiceOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" @click.self="invoiceOpen = false">
      <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
        <h3 class="text-lg font-semibold mb-1">Create interim invoice</h3>
        <p class="text-[12px] text-slate-500 mb-4">Add at least one charge line.</p>
        <div v-for="(line, idx) in invoiceLines" :key="idx" class="grid grid-cols-12 gap-2 mb-2 text-[12px]">
          <input v-model="line.description" placeholder="Description" class="col-span-5 h-9 rounded-lg px-2 bg-[#eef3fb] outline-none">
          <input v-model.number="line.quantity" type="number" min="0.01" step="0.01" placeholder="Qty" class="col-span-2 h-9 rounded-lg px-2 bg-[#eef3fb] outline-none">
          <input v-model.number="line.unit_price" type="number" min="0" step="0.01" placeholder="Unit" class="col-span-3 h-9 rounded-lg px-2 bg-[#eef3fb] outline-none">
          <button type="button" class="col-span-2 text-slate-500 hover:text-red-600" @click="removeLine(idx)">Remove</button>
        </div>
        <button type="button" class="text-[12px] text-[#2f86f3] font-semibold mb-3" @click="addLine">+ Add line</button>
        <p v-if="invoiceError" class="mb-3 text-[12px] text-red-600">{{ invoiceError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="h-9 px-4 rounded-xl text-[13px] border border-slate-200" @click="invoiceOpen = false">Cancel</button>
          <button type="button" class="h-9 px-4 rounded-xl text-[13px] font-semibold bg-[#2f86f3] text-white disabled:opacity-50" :disabled="billBusy" @click="submitInvoice">{{ billBusy ? 'Saving…' : 'Create interim' }}</button>
        </div>
      </div>
    </div>

    <!-- Pay modal -->
    <div v-if="payOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" @click.self="payOpen = false">
      <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
        <h3 class="text-lg font-semibold mb-1">Collect payment</h3>
        <p class="text-[12px] text-slate-500 mb-4">{{ payInvoice?.invoice_number }} · balance {{ money(payInvoice?.balance) }}</p>
        <label class="block text-[12px] text-slate-500 mb-3">
          Amount
          <input v-model.number="payForm.amount" type="number" min="0.01" step="0.01" class="mt-1 w-full h-10 rounded-xl px-3 bg-[#eef3fb] outline-none">
        </label>
        <label class="block text-[12px] text-slate-500 mb-4">
          Method
          <select v-model="payForm.payment_method" class="mt-1 w-full h-10 rounded-xl px-3 bg-[#eef3fb] outline-none">
            <option value="cash">Cash</option>
            <option value="upi">UPI</option>
            <option value="card">Card</option>
            <option value="neft">NEFT</option>
          </select>
        </label>
        <p v-if="payError" class="mb-3 text-[12px] text-red-600">{{ payError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="h-9 px-4 rounded-xl text-[13px] border border-slate-200" @click="payOpen = false">Cancel</button>
          <button type="button" class="h-9 px-4 rounded-xl text-[13px] font-semibold bg-[#2f86f3] text-white disabled:opacity-50" :disabled="billBusy" @click="submitPay">{{ billBusy ? 'Saving…' : 'Record payment' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { api } from '../api'

const loading = ref(false)
const error = ref('')
const wards = ref([])
const beds = ref([])
const admissions = ref([])
const patients = ref([])
const wardFilter = ref('')
const bedStatusFilter = ref('all')

const admitOpen = ref(false)
const admitting = ref(false)
const admitError = ref('')
const admitForm = reactive({
  patient_id: '',
  bed_id: '',
  admission_type: 'routine',
  reason: '',
})

const selectedAdmissionId = ref('')
const selectedAdmission = ref(null)
const invoices = ref([])
const billLoading = ref(false)
const billError = ref('')
const billBusy = ref(false)

const invoiceOpen = ref(false)
const invoiceError = ref('')
const invoiceLines = ref([{ description: 'Ward bed day', quantity: 1, unit_price: 500 }])

const payOpen = ref(false)
const payInvoice = ref(null)
const payError = ref('')
const payForm = reactive({ amount: 0, payment_method: 'cash' })

const filteredBeds = computed(() => beds.value.filter((b) => {
  if (wardFilter.value && b.ward?.id !== wardFilter.value) return false
  if (bedStatusFilter.value !== 'all' && b.status !== bedStatusFilter.value) return false
  return true
}))
const availableBeds = computed(() => beds.value.filter((b) => b.status === 'available'))

function bedStatusClass(status) {
  if (status === 'available') return 'bg-emerald-50 text-emerald-700'
  if (status === 'occupied') return 'bg-amber-50 text-amber-700'
  return 'bg-slate-100 text-slate-600'
}
function patientLabel(p) {
  const name = p.name || [p.first_name, p.last_name].filter(Boolean).join(' ') || 'Patient'
  return p.mrn ? `${name} (${p.mrn})` : name
}
function formatDateTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleString('en-IN')
}
function money(value) {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(value || 0))
}

async function loadWards() {
  const data = await api('/ipd/wards')
  wards.value = Array.isArray(data.data) ? data.data : []
}
async function loadBeds() {
  const params = new URLSearchParams()
  if (wardFilter.value) params.set('ward_id', wardFilter.value)
  const q = params.toString()
  const data = await api(`/ipd/beds${q ? `?${q}` : ''}`)
  beds.value = Array.isArray(data.data) ? data.data : []
}
async function loadAdmissions() {
  const data = await api('/ipd/admissions')
  admissions.value = Array.isArray(data.data) ? data.data : []
}
async function loadPatients() {
  try {
    const data = await api('/patients')
    patients.value = Array.isArray(data.data) ? data.data : []
  } catch (e) {
    patients.value = []
  }
}
async function loadAll() {
  loading.value = true
  error.value = ''
  try {
    await Promise.all([loadWards(), loadBeds(), loadAdmissions()])
    if (selectedAdmissionId.value) {
      const still = admissions.value.find((a) => a.id === selectedAdmissionId.value)
      selectedAdmission.value = still || null
      if (still) await loadInvoices()
    }
  } catch (e) {
    error.value = e.message || 'Unable to load IPD data.'
  } finally {
    loading.value = false
  }
}

async function selectAdmission(row) {
  selectedAdmissionId.value = row.id
  selectedAdmission.value = row
  await loadInvoices()
}

async function loadInvoices() {
  if (!selectedAdmissionId.value) return
  billLoading.value = true
  billError.value = ''
  try {
    const data = await api(`/ipd/admissions/${selectedAdmissionId.value}/invoices`)
    invoices.value = Array.isArray(data.data) ? data.data : []
  } catch (e) {
    invoices.value = []
    billError.value = e.message || 'Unable to load invoices.'
  } finally {
    billLoading.value = false
  }
}

function openCreateInvoice() {
  invoiceError.value = ''
  invoiceLines.value = [{ description: 'Ward bed day', quantity: 1, unit_price: 500 }]
  invoiceOpen.value = true
}
function addLine() {
  invoiceLines.value.push({ description: '', quantity: 1, unit_price: 0 })
}
function removeLine(idx) {
  if (invoiceLines.value.length <= 1) return
  invoiceLines.value.splice(idx, 1)
}
async function submitInvoice() {
  invoiceError.value = ''
  const items = invoiceLines.value.filter((l) => l.description && Number(l.quantity) > 0)
  if (!items.length) {
    invoiceError.value = 'Add at least one charge line.'
    return
  }
  billBusy.value = true
  try {
    await api(`/ipd/admissions/${selectedAdmissionId.value}/invoices`, {
      method: 'POST',
      body: JSON.stringify({ items }),
    })
    invoiceOpen.value = false
    await loadInvoices()
  } catch (e) {
    invoiceError.value = e.message || 'Unable to create invoice.'
  } finally {
    billBusy.value = false
  }
}

async function advanceInvoice(inv) {
  billBusy.value = true
  billError.value = ''
  try {
    await api(`/ipd/invoices/${inv.id}/advance`, { method: 'POST', body: JSON.stringify({}) })
    await loadInvoices()
  } catch (e) {
    billError.value = e.message || 'Unable to advance invoice.'
  } finally {
    billBusy.value = false
  }
}

function openPay(inv) {
  payInvoice.value = inv
  payForm.amount = Number(inv.balance) || 0
  payForm.payment_method = 'cash'
  payError.value = ''
  payOpen.value = true
}
async function submitPay() {
  if (!payInvoice.value) return
  billBusy.value = true
  payError.value = ''
  try {
    await api(`/billing/invoices/${payInvoice.value.id}/payments`, {
      method: 'POST',
      body: JSON.stringify({
        amount: payForm.amount,
        payment_method: payForm.payment_method,
      }),
    })
    payOpen.value = false
    await loadInvoices()
  } catch (e) {
    payError.value = e.message || 'Unable to record payment.'
  } finally {
    billBusy.value = false
  }
}

async function openAdmit() {
  admitError.value = ''
  admitForm.patient_id = ''
  admitForm.bed_id = ''
  admitForm.admission_type = 'routine'
  admitForm.reason = ''
  await Promise.all([loadPatients(), loadBeds()])
  admitOpen.value = true
}
async function submitAdmit() {
  admitError.value = ''
  if (!admitForm.patient_id || !admitForm.bed_id) {
    admitError.value = 'Select a patient and an available bed.'
    return
  }
  admitting.value = true
  try {
    await api('/ipd/admissions', {
      method: 'POST',
      body: JSON.stringify({
        patient_id: admitForm.patient_id,
        bed_id: admitForm.bed_id,
        admission_type: admitForm.admission_type,
        reason: admitForm.reason || null,
      }),
    })
    admitOpen.value = false
    await loadAll()
  } catch (e) {
    admitError.value = e.message || 'Admit failed.'
  } finally {
    admitting.value = false
  }
}

watch(wardFilter, () => { loadBeds().catch(() => {}) })
onMounted(loadAll)
</script>
