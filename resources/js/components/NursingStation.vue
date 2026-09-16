<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
      <div>
        <h2 class="text-xl font-semibold">Nursing Station</h2>
        <p class="text-[11px] text-slate-400 mt-0.5">Active IPD admissions, vitals, and nursing notes.</p>
      </div>
      <button type="button" class="h-9 px-4 rounded-xl text-[13px] font-semibold border border-slate-200 text-slate-700 hover:bg-slate-50" @click="loadAdmissions">Refresh</button>
    </div>

    <p v-if="error" class="mb-3 text-[12px] text-red-600">{{ error }}</p>
    <p v-if="loading" class="mb-3 text-[12px] text-slate-400">Loading admissions...</p>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
      <div class="xl:col-span-4">
        <h3 class="text-[13px] font-semibold text-slate-700 mb-2">Admitted patients</h3>
        <div class="overflow-x-auto rounded-2xl border border-slate-100 max-h-[520px] overflow-y-auto">
          <table class="min-w-full text-left text-[12px]">
            <thead class="bg-[#f8fafc] text-slate-500 sticky top-0">
              <tr>
                <th class="px-3 py-2 font-semibold">Patient</th>
                <th class="px-3 py-2 font-semibold">Bed</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!loading && !admissions.length">
                <td colspan="2" class="px-3 py-6 text-center text-slate-400">No active admissions.</td>
              </tr>
              <tr
                v-for="row in admissions"
                :key="row.id"
                class="border-t border-slate-50 cursor-pointer"
                :class="selectedId === row.id ? 'bg-sky-50' : ''"
                @click="selectAdmission(row)"
              >
                <td class="px-3 py-2">
                  <div class="font-medium">{{ row.patient?.name || 'Patient' }}</div>
                  <div class="text-[11px] text-slate-400">{{ row.admission_number }} · {{ row.patient?.mrn || '' }}</div>
                </td>
                <td class="px-3 py-2 whitespace-nowrap">
                  <div>{{ row.ward_name || '—' }}</div>
                  <div class="text-[11px] text-slate-400">{{ [row.room_number, row.bed_number].filter(Boolean).join(' / ') || '—' }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="xl:col-span-8 space-y-5">
        <div v-if="!selected" class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-[13px] text-slate-400">
          Select an admitted patient to chart vitals and notes.
        </div>

        <template v-else>
          <div class="rounded-2xl border border-slate-100 p-4">
            <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
              <div>
                <h3 class="text-[13px] font-semibold text-slate-700">Vitals</h3>
                <p class="text-[11px] text-slate-400">{{ selected.patient?.name }} · {{ selected.admission_number }}</p>
              </div>
            </div>

            <form class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-3 text-[12px]" @submit.prevent="submitVitals">
              <label class="text-slate-500">Temp
                <input v-model.number="vitalForm.temperature" type="number" step="0.1" class="mt-0.5 w-full h-9 rounded-lg px-2 bg-[#eef3fb] outline-none">
              </label>
              <label class="text-slate-500">Pulse
                <input v-model.number="vitalForm.pulse" type="number" class="mt-0.5 w-full h-9 rounded-lg px-2 bg-[#eef3fb] outline-none">
              </label>
              <label class="text-slate-500">RR
                <input v-model.number="vitalForm.respiratory_rate" type="number" class="mt-0.5 w-full h-9 rounded-lg px-2 bg-[#eef3fb] outline-none">
              </label>
              <label class="text-slate-500">SpO2
                <input v-model.number="vitalForm.spo2" type="number" step="0.1" class="mt-0.5 w-full h-9 rounded-lg px-2 bg-[#eef3fb] outline-none">
              </label>
              <label class="text-slate-500">Systolic
                <input v-model.number="vitalForm.systolic_bp" type="number" class="mt-0.5 w-full h-9 rounded-lg px-2 bg-[#eef3fb] outline-none">
              </label>
              <label class="text-slate-500">Diastolic
                <input v-model.number="vitalForm.diastolic_bp" type="number" class="mt-0.5 w-full h-9 rounded-lg px-2 bg-[#eef3fb] outline-none">
              </label>
              <label class="text-slate-500">Pain
                <input v-model.number="vitalForm.pain_score" type="number" min="0" max="10" class="mt-0.5 w-full h-9 rounded-lg px-2 bg-[#eef3fb] outline-none">
              </label>
              <div class="flex items-end">
                <button type="submit" class="h-9 w-full rounded-xl text-[13px] font-semibold bg-[#2f86f3] text-white disabled:opacity-50" :disabled="vitalBusy">{{ vitalBusy ? 'Saving...' : 'Record vitals' }}</button>
              </div>
            </form>
            <p v-if="vitalError" class="mb-2 text-[12px] text-red-600">{{ vitalError }}</p>

            <div class="overflow-x-auto rounded-xl border border-slate-100">
              <table class="min-w-full text-left text-[12px]">
                <thead class="bg-[#f8fafc] text-slate-500">
                  <tr>
                    <th class="px-3 py-2 font-semibold">When</th>
                    <th class="px-3 py-2 font-semibold">T</th>
                    <th class="px-3 py-2 font-semibold">P</th>
                    <th class="px-3 py-2 font-semibold">RR</th>
                    <th class="px-3 py-2 font-semibold">BP</th>
                    <th class="px-3 py-2 font-semibold">SpO2</th>
                    <th class="px-3 py-2 font-semibold">Pain</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!detailLoading && !vitals.length">
                    <td colspan="7" class="px-3 py-4 text-center text-slate-400">No vitals recorded.</td>
                  </tr>
                  <tr v-for="v in vitals" :key="v.id" class="border-t border-slate-50">
                    <td class="px-3 py-2 whitespace-nowrap">{{ formatDateTime(v.recorded_at) }}</td>
                    <td class="px-3 py-2">{{ displayNum(v.temperature) }}</td>
                    <td class="px-3 py-2">{{ displayNum(v.pulse) }}</td>
                    <td class="px-3 py-2">{{ displayNum(v.respiratory_rate) }}</td>
                    <td class="px-3 py-2">{{ bp(v) }}</td>
                    <td class="px-3 py-2">{{ displayNum(v.spo2) }}</td>
                    <td class="px-3 py-2">{{ displayNum(v.pain_score) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="rounded-2xl border border-slate-100 p-4">
            <h3 class="text-[13px] font-semibold text-slate-700 mb-3">Nursing notes</h3>
            <form class="mb-3" @submit.prevent="submitNote">
              <textarea v-model="noteText" rows="3" placeholder="Nursing note (synthetic / clinical observation)" class="w-full rounded-xl px-3 py-2 text-[13px] bg-[#eef3fb] outline-none resize-y"></textarea>
              <div class="mt-2 flex items-center justify-between gap-2">
                <p v-if="noteError" class="text-[12px] text-red-600">{{ noteError }}</p>
                <button type="submit" class="ml-auto h-9 px-4 rounded-xl text-[13px] font-semibold bg-[#2f86f3] text-white disabled:opacity-50" :disabled="noteBusy">{{ noteBusy ? 'Saving...' : 'Add note' }}</button>
              </div>
            </form>

            <div class="space-y-2 max-h-[280px] overflow-y-auto">
              <p v-if="!detailLoading && !notes.length" class="text-[12px] text-slate-400 text-center py-4">No notes yet.</p>
              <div v-for="n in notes" :key="n.id" class="rounded-xl border border-slate-100 px-3 py-2 text-[12px]">
                <div class="text-[11px] text-slate-400 mb-1">{{ formatDateTime(n.created_at) }}</div>
                <div class="text-slate-700 whitespace-pre-wrap">{{ n.note }}</div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api } from '../api'

const loading = ref(false)
const detailLoading = ref(false)
const error = ref('')
const admissions = ref([])
const selectedId = ref('')
const selected = ref(null)
const vitals = ref([])
const notes = ref([])

const vitalBusy = ref(false)
const vitalError = ref('')
const vitalForm = reactive({
  temperature: null,
  pulse: null,
  respiratory_rate: null,
  systolic_bp: null,
  diastolic_bp: null,
  spo2: null,
  pain_score: null,
})

const noteBusy = ref(false)
const noteError = ref('')
const noteText = ref('')

function formatDateTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleString('en-IN')
}
function displayNum(value) {
  return value === null || value === undefined || value === '' ? '—' : value
}
function bp(v) {
  if (v.systolic_bp == null && v.diastolic_bp == null) return '—'
  return `${displayNum(v.systolic_bp)}/${displayNum(v.diastolic_bp)}`
}
function resetVitalForm() {
  vitalForm.temperature = null
  vitalForm.pulse = null
  vitalForm.respiratory_rate = null
  vitalForm.systolic_bp = null
  vitalForm.diastolic_bp = null
  vitalForm.spo2 = null
  vitalForm.pain_score = null
}

async function loadAdmissions() {
  loading.value = true
  error.value = ''
  try {
    const data = await api('/nursing/admissions')
    admissions.value = Array.isArray(data.data) ? data.data : []
    if (selectedId.value) {
      const still = admissions.value.find((a) => a.id === selectedId.value)
      selected.value = still || null
      if (!still) {
        selectedId.value = ''
        vitals.value = []
        notes.value = []
      }
    }
  } catch (e) {
    error.value = e.message || 'Unable to load admissions.'
  } finally {
    loading.value = false
  }
}

async function selectAdmission(row) {
  selectedId.value = row.id
  selected.value = row
  vitalError.value = ''
  noteError.value = ''
  noteText.value = ''
  resetVitalForm()
  await loadDetail()
}

async function loadDetail() {
  if (!selectedId.value) return
  detailLoading.value = true
  try {
    const [v, n] = await Promise.all([
      api(`/nursing/admissions/${selectedId.value}/vitals`),
      api(`/nursing/admissions/${selectedId.value}/notes`),
    ])
    vitals.value = Array.isArray(v.data) ? v.data : []
    notes.value = Array.isArray(n.data) ? n.data : []
  } catch (e) {
    error.value = e.message || 'Unable to load chart data.'
  } finally {
    detailLoading.value = false
  }
}

function vitalPayload() {
  const keys = ['temperature', 'pulse', 'respiratory_rate', 'systolic_bp', 'diastolic_bp', 'spo2', 'pain_score']
  const body = {}
  keys.forEach((k) => {
    const val = vitalForm[k]
    if (val !== null && val !== undefined && val !== '') body[k] = val
  })
  return body
}

async function submitVitals() {
  vitalError.value = ''
  const body = vitalPayload()
  if (!Object.keys(body).length) {
    vitalError.value = 'Enter at least one vital sign.'
    return
  }
  vitalBusy.value = true
  try {
    await api(`/nursing/admissions/${selectedId.value}/vitals`, {
      method: 'POST',
      body: JSON.stringify(body),
    })
    resetVitalForm()
    await loadDetail()
  } catch (e) {
    vitalError.value = e.message || 'Unable to record vitals.'
  } finally {
    vitalBusy.value = false
  }
}

async function submitNote() {
  noteError.value = ''
  const note = (noteText.value || '').trim()
  if (!note) {
    noteError.value = 'Note cannot be empty.'
    return
  }
  noteBusy.value = true
  try {
    await api(`/nursing/admissions/${selectedId.value}/notes`, {
      method: 'POST',
      body: JSON.stringify({ note }),
    })
    noteText.value = ''
    await loadDetail()
  } catch (e) {
    noteError.value = e.message || 'Unable to add note.'
  } finally {
    noteBusy.value = false
  }
}

onMounted(loadAdmissions)
</script>
