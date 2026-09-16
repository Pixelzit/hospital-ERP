<template>
  <OpdVisitPlaceholder v-if="opened" :visit="opened" @back="opened = null" />

  <div v-else class="bg-white rounded-2xl border border-slate-100 px-4 py-3 shadow-[0_4px_16px_rgba(47,134,243,0.05)]">
    <div class="flex items-center justify-between gap-3 mb-2">
      <div class="min-w-0">
        <h2 class="text-[16px] font-semibold text-slate-800 leading-tight">OPD Dashboard</h2>
        <p class="text-[11px] text-slate-400">Manage today's outpatient visits and patient flow.</p>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <button
          type="button"
          class="h-8 px-3 rounded-lg border border-[#2f86f3] text-[#2f86f3] text-[12px] font-medium hover:bg-[#eef3fb] focus:outline-none focus:ring-2 focus:ring-[#2f86f3]/30"
          @click="openCheckIn"
        >
          Check-in / Walk-in
        </button>
        <button
          type="button"
          class="h-8 px-3 rounded-lg bg-[#2f86f3] text-white text-[12px] font-medium hover:bg-[#2476dc] disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-[#2f86f3]/40"
          :disabled="loading"
          @click="load"
        >
          {{ loading ? 'Refreshing...' : 'Refresh' }}
        </button>
      </div>
    </div>

    <p v-if="error" class="mb-2 text-[12px] text-red-500">{{ error }}</p>

    <div class="flex flex-wrap items-end gap-2 mb-2">
      <label class="text-[10px] text-slate-400 font-medium">
        Date
        <input v-model="filters.date" type="date" class="mt-0.5 block h-8 min-w-[132px] rounded-lg px-2 text-[12px] outline-none bg-[#eef3fb] text-slate-700 focus:ring-2 focus:ring-[#2f86f3]/30">
      </label>
      <label class="text-[10px] text-slate-400 font-medium">
        Doctor
        <select v-model="filters.doctorId" class="mt-0.5 block h-8 min-w-[140px] rounded-lg px-2 text-[12px] outline-none bg-[#eef3fb] text-slate-700 focus:ring-2 focus:ring-[#2f86f3]/30">
          <option value="all">All doctors</option>
          <option v-for="item in doctors" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
      </label>
      <label class="text-[10px] text-slate-400 font-medium">
        Clinic / Department
        <select v-model="filters.departmentId" class="mt-0.5 block h-8 min-w-[150px] rounded-lg px-2 text-[12px] outline-none bg-[#eef3fb] text-slate-700 focus:ring-2 focus:ring-[#2f86f3]/30">
          <option value="all">All clinics</option>
          <option v-for="item in departments" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
      </label>
      <label class="text-[10px] text-slate-400 font-medium">
        Patient Type
        <select v-model="filters.patientType" class="mt-0.5 block h-8 min-w-[120px] rounded-lg px-2 text-[12px] outline-none bg-[#eef3fb] text-slate-700 focus:ring-2 focus:ring-[#2f86f3]/30">
          <option value="all">All types</option>
          <option value="Out Patient">Out Patient</option>
        </select>
      </label>
      <label class="text-[10px] text-slate-400 font-medium flex-1 min-w-[180px]">
        Patient Search
        <input
          v-model="filters.search"
          type="text"
          placeholder="Name, MRN, visit no., mobile"
          class="mt-0.5 block w-full h-8 rounded-lg px-2 text-[12px] outline-none bg-[#eef3fb] text-slate-800 focus:ring-2 focus:ring-[#2f86f3]/30"
        >
      </label>
      <button
        type="button"
        class="h-8 px-3 rounded-lg border border-slate-200 text-[12px] text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#2f86f3]/30"
        @click="resetFilters"
      >
        Reset
      </button>
    </div>

    <div class="flex flex-wrap gap-1 mb-2">
      <button
        v-for="card in statusCards"
        :key="card.id"
        type="button"
        class="h-7 px-2.5 rounded-md text-[11px] font-medium inline-flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-[#2f86f3]/30"
        :class="filters.status === card.id ? 'bg-[#2f86f3] text-white' : 'bg-[#eef3fb] text-slate-600 hover:bg-[#dcebff]'"
        @click="toggleStatus(card.id)"
      >
        <span>{{ card.label }}</span>
        <span class="tabular-nums" :class="filters.status === card.id ? 'text-white' : 'text-slate-500'">{{ counts[card.id] }}</span>
      </button>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-[12px] min-w-[920px]">
        <thead>
          <tr class="text-left text-[11px] text-slate-400 border-b border-slate-100">
            <th class="font-medium py-1.5 pr-2 hidden xl:table-cell">S/N</th>
            <th class="font-medium py-1.5 pr-2 hidden xl:table-cell">
              <button type="button" class="hover:text-[#2f86f3]" @click="setSort('appointment_number')">Appointment No.</button>
            </th>
            <th class="font-medium py-1.5 pr-2">
              <button type="button" class="hover:text-[#2f86f3]" @click="setSort('visit_number')">Visit No.</button>
            </th>
            <th class="font-medium py-1.5 pr-2 hidden lg:table-cell">
              <button type="button" class="hover:text-[#2f86f3]" @click="setSort('started_at')">Date & Time</button>
            </th>
            <th class="font-medium py-1.5 pr-2 hidden lg:table-cell">
              <button type="button" class="hover:text-[#2f86f3]" @click="setSort('department')">Clinic / Doctor</button>
            </th>
            <th class="font-medium py-1.5 pr-2">
              <button type="button" class="hover:text-[#2f86f3]" @click="setSort('uhid')">UHID</button>
            </th>
            <th class="font-medium py-1.5 pr-2">
              <button type="button" class="hover:text-[#2f86f3]" @click="setSort('patient_name')">Patient Name</button>
            </th>
            <th class="font-medium py-1.5 pr-2 hidden md:table-cell">Age / Gender</th>
            <th class="font-medium py-1.5 pr-2 hidden xl:table-cell">
              <button type="button" class="hover:text-[#2f86f3]" @click="setSort('assigned_user')">Assigned User</button>
            </th>
            <th class="font-medium py-1.5 pr-2">
              <button type="button" class="hover:text-[#2f86f3]" @click="setSort('status')">Status</button>
            </th>
            <th class="font-medium py-1.5 text-right">Action</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <tr v-for="n in 8" :key="'sk-'+n" class="border-b border-slate-50">
              <td colspan="11" class="py-1.5">
                <div class="h-3 rounded bg-slate-100 animate-pulse"></div>
              </td>
            </tr>
          </template>
          <tr v-else-if="!visits.length">
            <td colspan="11" class="py-6 text-center text-slate-400 text-[12px]">No OPD visits found.</td>
          </tr>
          <tr v-else-if="!pageRows.length">
            <td colspan="11" class="py-6 text-center text-slate-400 text-[12px]">No matching visits.</td>
          </tr>
          <tr
            v-for="(row, index) in pageRows"
            :key="row.id"
            class="border-b border-slate-50 hover:bg-[#f8fbff]"
          >
            <td class="py-1.5 pr-2 text-slate-400 hidden xl:table-cell">{{ pageStart + index }}</td>
            <td class="py-1.5 pr-2 hidden xl:table-cell">{{ walkInLabel(row.appointment_number) }}</td>
            <td class="py-1.5 pr-2 font-mono text-slate-600 whitespace-nowrap">{{ row.visit_number || '-' }}</td>
            <td class="py-1.5 pr-2 text-slate-600 whitespace-nowrap hidden lg:table-cell">{{ formatDateTime(row.started_at) }}</td>
            <td class="py-1.5 pr-2 hidden lg:table-cell">{{ clinicDoctor(row) }}</td>
            <td class="py-1.5 pr-2 font-mono text-slate-600 whitespace-nowrap">{{ row.uhid || '-' }}</td>
            <td class="py-1.5 pr-2 font-medium text-slate-800 whitespace-nowrap">{{ row.patient_name || '-' }}</td>
            <td class="py-1.5 pr-2 text-slate-500 whitespace-nowrap hidden md:table-cell">{{ ageGender(row) }}</td>
            <td class="py-1.5 pr-2 hidden xl:table-cell">{{ row.assigned_user || '-' }}</td>
            <td class="py-1.5 pr-2">
              <span class="inline-block text-[10px] font-medium px-1.5 py-0.5 rounded" :class="statusClass(row.status)">{{ opdStatusLabel(row.status) }}</span>
            </td>
            <td class="py-1.5 text-right whitespace-nowrap">
              <div class="inline-flex items-center gap-1 justify-end">
                <button
                  v-if="row.next_status"
                  type="button"
                  class="h-7 px-2 rounded-md border border-slate-200 text-slate-700 text-[11px] font-medium hover:bg-slate-50 disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-[#2f86f3]/30"
                  :disabled="advancingId === row.id"
                  @click="advanceStatus(row)"
                >
                  {{ advancingId === row.id ? 'Updating...' : advanceLabel(row.next_status) }}
                </button>
                <button
                  type="button"
                  class="h-7 px-2 rounded-md bg-[#2f86f3] text-white text-[11px] font-medium hover:bg-[#2476dc] focus:outline-none focus:ring-2 focus:ring-[#2f86f3]/40"
                  @click="openVisit(row)"
                >
                  Open Visit
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="filtered.length" class="flex flex-wrap items-center justify-between gap-2 mt-2 text-[11px] text-slate-500">
      <div>Showing {{ pageStart }}-{{ pageEnd }} of {{ filtered.length }}</div>
      <div class="flex items-center gap-2">
        <label class="flex items-center gap-1">
          <span>Rows</span>
          <select v-model.number="perPage" class="h-7 rounded-md px-1 outline-none bg-[#eef3fb] text-slate-700">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </label>
        <button type="button" class="h-7 px-2 rounded-md border border-slate-200 disabled:opacity-40" :disabled="page <= 1" @click="page -= 1">Prev</button>
        <span>{{ page }} / {{ pages }}</span>
        <button type="button" class="h-7 px-2 rounded-md border border-slate-200 disabled:opacity-40" :disabled="page >= pages" @click="page += 1">Next</button>
      </div>
    </div>

    <div v-if="showCheckIn" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white w-full max-w-lg rounded-2xl p-5">
        <h3 class="text-lg font-semibold mb-3">Check-in / Walk-in</h3>
        <p v-if="checkInError" class="mb-2 text-sm text-red-500">{{ checkInError }}</p>
        <div class="grid gap-3">
          <label class="text-xs text-slate-500">
            Patient *
            <select v-model="checkIn.patientId" class="mt-1 block w-full h-10 rounded-xl border px-3 text-sm bg-white">
              <option value="">Select patient</option>
              <option v-for="p in patients" :key="p.id" :value="p.id">
                {{ p.name || [p.first_name, p.last_name].filter(Boolean).join(' ') }} ({{ p.mrn || p.uhid || p.id }})
              </option>
            </select>
          </label>
          <label class="text-xs text-slate-500">
            Doctor (optional)
            <select v-model="checkIn.doctorId" class="mt-1 block w-full h-10 rounded-xl border px-3 text-sm bg-white">
              <option value="">None</option>
              <option v-for="item in doctors" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </label>
          <label class="text-xs text-slate-500">
            Department (optional)
            <select v-model="checkIn.departmentId" class="mt-1 block w-full h-10 rounded-xl border px-3 text-sm bg-white">
              <option value="">None</option>
              <option v-for="item in departments" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </label>
        </div>
        <div class="flex gap-3 mt-5">
          <button
            type="button"
            class="flex-1 py-2.5 rounded-xl bg-[#2f86f3] text-white text-sm font-medium disabled:opacity-60"
            :disabled="checkingIn"
            @click="submitCheckIn"
          >
            {{ checkingIn ? 'Checking in...' : 'Check in' }}
          </button>
          <button type="button" class="flex-1 py-2.5 rounded-xl border text-sm" @click="closeCheckIn">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { api } from '../api'
import { paginatePatients } from '../patients/listQuery.js'
import { filterOpdVisits, opdStatusCounts, opdStatusLabel, sortOpdVisits, walkInLabel } from '../opd/opdQueue.js'
import OpdVisitPlaceholder from './OpdVisitPlaceholder.vue'

const statusCards = [
  { id: 'new_patient', label: 'New Patient' },
  { id: 'nurse_seen', label: 'Nurse Seen' },
  { id: 'doctor_seen', label: 'Doctor Seen' },
  { id: 'visit_complete', label: 'Visit Complete' },
]

const loading = ref(false)
const error = ref('')
const visits = ref([])
const doctors = ref([])
const departments = ref([])
const opened = ref(null)
const showCheckIn = ref(false)
const checkingIn = ref(false)
const checkInError = ref('')
const advancingId = ref('')
const patients = ref([])
const checkIn = reactive({ patientId: '', doctorId: '', departmentId: '' })
const page = ref(1)
const perPage = ref(10)
const sortKey = ref('started_at')
const sortDir = ref('asc')

function todayIso() {
  const d = new Date()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${month}-${day}`
}

const filters = reactive({
  date: todayIso(),
  doctorId: 'all',
  departmentId: 'all',
  patientType: 'all',
  search: '',
  status: 'all',
})

const counted = computed(() => filterOpdVisits(visits.value, { ...filters, status: 'all' }))
const counts = computed(() => opdStatusCounts(counted.value))
const filtered = computed(() => sortOpdVisits(filterOpdVisits(visits.value, filters), sortKey.value, sortDir.value))
const paged = computed(() => paginatePatients(filtered.value, page.value, perPage.value))
const pageRows = computed(() => paged.value.rows)
const pages = computed(() => paged.value.pages)
const pageStart = computed(() => (filtered.value.length ? (paged.value.page - 1) * paged.value.perPage + 1 : 0))
const pageEnd = computed(() => Math.min(filtered.value.length, paged.value.page * paged.value.perPage))

function toggleStatus(id) {
  filters.status = filters.status === id ? 'all' : id
}

function resetFilters() {
  filters.date = todayIso()
  filters.doctorId = 'all'
  filters.departmentId = 'all'
  filters.patientType = 'all'
  filters.search = ''
  filters.status = 'all'
  page.value = 1
}

function setSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    return
  }
  sortKey.value = key
  sortDir.value = 'asc'
}

function ageGender(row) {
  const age = row.age == null || row.age === '' ? '-' : row.age
  const gender = row.gender || '-'
  return `${age} / ${gender}`
}

function clinicDoctor(row) {
  const clinic = row.department && row.department !== '-' ? row.department : ''
  const doctor = row.doctor || row.assigned_user || ''
  if (clinic && doctor && doctor !== '-') return `${clinic} / ${doctor}`
  return clinic || doctor || '-'
}

function formatDateTime(value) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function statusClass(status) {
  const key = String(status || '').toLowerCase()
  if (['closed', 'completed', 'complete', 'visit_complete', 'fulfilled'].includes(key)) return 'bg-emerald-50 text-emerald-700'
  if (['open', 'scheduled', 'new', 'new_patient', 'arrived', 'active'].includes(key)) return 'bg-amber-50 text-amber-700'
  if (key.includes('nurse')) return 'bg-sky-50 text-sky-700'
  if (key.includes('doctor')) return 'bg-indigo-50 text-indigo-700'
  return 'bg-slate-100 text-slate-500'
}

function openVisit(row) {
  if (!row?.id || !row?.patient_id) {
    opened.value = { id: row?.id || null, patient_id: row?.patient_id || null }
    return
  }
  opened.value = { ...row }
}

function advanceLabel(next) {
  if (next === 'nurse_seen') return 'Mark nurse seen'
  if (next === 'doctor_seen') return 'Mark doctor seen'
  if (next === 'visit_complete') return 'Complete visit'
  return 'Advance'
}

function openCheckIn() {
  checkInError.value = ''
  checkIn.patientId = ''
  checkIn.doctorId = ''
  checkIn.departmentId = ''
  showCheckIn.value = true
  loadPatients()
}

function closeCheckIn() {
  showCheckIn.value = false
}

async function loadPatients() {
  try {
    const data = await api('/patients')
    patients.value = Array.isArray(data.data) ? data.data : []
  } catch (e) {
    patients.value = []
  }
}

async function submitCheckIn() {
  checkInError.value = ''
  if (!checkIn.patientId) {
    checkInError.value = 'Select a patient.'
    return
  }
  checkingIn.value = true
  try {
    const payload = {
      patient_id: checkIn.patientId,
      doctor_id: checkIn.doctorId || null,
      department_id: checkIn.departmentId || null,
    }
    await api('/opd/visits', { method: 'POST', body: JSON.stringify(payload) })
    filters.date = todayIso()
    closeCheckIn()
    await load()
  } catch (e) {
    checkInError.value = e.message || 'Check-in failed.'
  } finally {
    checkingIn.value = false
  }
}

async function advanceStatus(row) {
  if (!row?.id || !row?.next_status) return
  advancingId.value = row.id
  error.value = ''
  try {
    await api(`/opd/visits/${row.id}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ status: row.next_status }),
    })
    await load()
  } catch (e) {
    error.value = e.message || 'Unable to update status.'
  } finally {
    advancingId.value = ''
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const query = new URLSearchParams({ date: filters.date || '' })
    const data = await api(`/opd/visits?${query.toString()}`)
    visits.value = Array.isArray(data.data) ? data.data : []
  } catch (e) {
    visits.value = []
    error.value = e.message || 'Unable to load OPD visits.'
  } finally {
    loading.value = false
  }
}

async function loadLookups() {
  try {
    const [dept, doc] = await Promise.all([api('/departments'), api('/doctors')])
    departments.value = Array.isArray(dept.data) ? dept.data : []
    doctors.value = (Array.isArray(doc.data) ? doc.data : []).map((item) => ({
      id: item.id,
      name: item.name || [item.first_name, item.last_name].filter(Boolean).join(' ') || 'Doctor',
    }))
  } catch (e) {
    departments.value = []
    doctors.value = []
  }
}

watch(() => filters.date, load)
watch([filters, perPage], () => { page.value = 1 }, { deep: true })
watch(() => paged.value.page, (value) => { page.value = value })

onMounted(async () => {
  await loadLookups()
  await load()
})
</script>
