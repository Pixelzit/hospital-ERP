<template>
  <div class="flex gap-5 h-full">
    <div class="flex-1 min-w-0 flex flex-col gap-5">
      <div class="grid grid-cols-4 gap-4">
        <div v-for="card in kpis" :key="card.label" class="bg-white rounded-[22px] px-5 py-5 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
          <div class="w-12 h-12 rounded-xl bg-[#2f86f3] text-white flex items-center justify-center mb-4">
            <svg v-if="card.icon === 'bed'" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 18v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5M3 18h18M6 11V8a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3M3 18v2M21 18v2" />
            </svg>
            <svg v-else-if="card.icon === 'icu'" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <rect x="4" y="4" width="16" height="16" rx="3" />
              <path stroke-linecap="round" d="M12 8v8M8 12h8" />
            </svg>
            <svg v-else-if="card.icon === 'appt'" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <rect x="6" y="3" width="12" height="18" rx="2" />
              <path stroke-linecap="round" d="M9 8h6M9 12h6M9 16h4" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M8 8h.01M9.5 14c1.2 2 4.8 2 6 0M7 11c2 3 8 3 10 0" />
              <path stroke-linecap="round" d="M12 3c2 2 3 4 3 6s-1 4-3 6" />
            </svg>
          </div>
          <div class="text-[13px] text-slate-500">{{ card.label }}</div>
          <div class="text-[28px] font-bold text-slate-900 mt-1 leading-none">{{ card.value }}</div>
        </div>
      </div>

      <div class="bg-white rounded-[22px] px-6 py-5 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-[17px] font-semibold text-slate-900">Financial Performance</h3>
          <button class="text-sm text-slate-500 border border-slate-200 rounded-lg px-3 py-1.5">Month</button>
        </div>
        <svg viewBox="0 0 640 250" class="w-full h-[230px]">
          <line v-for="y in [30, 65, 100, 135, 170, 205]" :key="y" x1="58" :y1="y" x2="620" :y2="y" stroke="#eef2f7" stroke-width="1" />
          <text v-for="(label, i) in yLabels" :key="label" x="8" :y="34 + i * 35" fill="#94a3b8" font-size="11">{{ label }}</text>
          <text v-for="(m, i) in months" :key="m" :x="80 + i * 85" y="238" fill="#94a3b8" font-size="12">{{ m }}</text>
          <path :d="incomePath" fill="none" stroke="#2f86f3" stroke-width="2.4" stroke-linecap="round" />
        </svg>
        <div class="flex items-center justify-center gap-8 text-sm text-slate-500">
          <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-[#2f86f3]"></span> Income</span>
        </div>
      </div>

      <div class="bg-white rounded-[22px] px-6 py-5 shadow-[0_8px_30px_rgba(47,134,243,0.06)] flex-1">
        <h3 class="text-[17px] font-semibold text-slate-900 mb-4">Patient Registration</h3>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-slate-400">
              <th class="font-medium pb-3">Name</th>
              <th class="font-medium pb-3">Status</th>
              <th class="font-medium pb-3">Date & Time</th>
              <th class="font-medium pb-3">Age</th>
              <th class="font-medium pb-3">Appoint for</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!patients.length">
              <td colspan="5" class="py-8 text-center text-slate-400">No patients in the database yet.</td>
            </tr>
            <tr v-for="row in patients" :key="row.name + row.datetime" class="border-t border-slate-100">
              <td class="py-3 font-medium text-slate-800">{{ row.name }}</td>
              <td class="py-3">
                <span class="text-xs font-medium px-2.5 py-1 rounded-full" :class="statusClass(row.status)">{{ row.status }}</span>
              </td>
              <td class="py-3 text-slate-500">{{ formatDate(row.datetime) }}</td>
              <td class="py-3 text-slate-500">{{ row.age }}</td>
              <td class="py-3 text-slate-500">{{ row.dept }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="w-[300px] shrink-0 flex flex-col gap-5">
      <div class="bg-white rounded-[22px] px-5 py-5 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-[17px] font-semibold text-slate-900">Top Doctors</h3>
          <button class="text-sm text-slate-400 hover:text-[#2f86f3]" @click="$emit('navigate', 'doctors')">view all</button>
        </div>
        <div v-if="!doctors.length" class="text-sm text-slate-400 py-6 text-center">No doctors in the database yet.</div>
        <div class="space-y-4">
          <div v-for="doc in doctors" :key="doc.name" class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-[#2f86f3] text-white flex items-center justify-center text-sm font-semibold">
              {{ initials(doc.name) }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-semibold text-slate-800 truncate">{{ doc.name }}</div>
              <div class="text-xs text-slate-400">{{ doc.role }}</div>
            </div>
            <span class="text-xs font-medium" :class="doc.available ? 'text-emerald-500' : 'text-red-500'">
              {{ doc.available ? 'Available' : 'Unavailable' }}
            </span>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-[22px] px-5 py-5 shadow-[0_8px_30px_rgba(47,134,243,0.06)] flex-1">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-[17px] font-semibold text-slate-900">Medicine Activity</h3>
        </div>
        <div class="flex items-end gap-3 h-[200px] px-1">
          <div v-for="bar in medicine" :key="bar.day" class="flex-1 flex flex-col items-center gap-2">
            <div class="w-full h-[170px] flex items-end">
              <div class="w-full rounded-md bg-[#2f86f3]" :style="{ height: Math.max(bar.pct, 4) + '%' }"></div>
            </div>
            <div class="text-[11px] text-slate-400">{{ bar.day }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../api'

defineEmits(['navigate'])

const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul']
const stats = ref({
  beds_occupied: 0,
  icu_patients: 0,
  appointments: 0,
  today_op: 0,
})
const patients = ref([])
const doctors = ref([])
const income = ref([0, 0, 0, 0, 0, 0, 0])
const medicine = ref([
  { day: 'Mon', pct: 0 },
  { day: 'Tue', pct: 0 },
  { day: 'Wed', pct: 0 },
  { day: 'Thu', pct: 0 },
  { day: 'Fri', pct: 0 },
  { day: 'Sat', pct: 0 },
  { day: 'Sun', pct: 0 },
])

const kpis = computed(() => [
  { label: 'Beds Occupied', value: stats.value.beds_occupied, icon: 'bed' },
  { label: 'ICU Patients', value: stats.value.icu_patients, icon: 'icu' },
  { label: 'Appointments', value: stats.value.appointments, icon: 'appt' },
  { label: 'Today OP', value: stats.value.today_op, icon: 'op' },
])

const chartMax = computed(() => Math.max(60000, ...income.value, 1))
const yLabels = computed(() => {
  const max = chartMax.value
  return [6, 5, 4, 3, 2, 1].map((n) => Math.round((max * n) / 6).toLocaleString('en-IN'))
})

const incomePath = computed(() => linePath(income.value, chartMax.value))

function linePath(values, maxY) {
  const pts = values.map((v, i) => {
    const x = 80 + i * 85
    const y = 205 - (Number(v) / maxY) * 175
    return `${i === 0 ? 'M' : 'L'}${x},${y.toFixed(1)}`
  })
  return pts.join(' ')
}

function statusClass(status) {
  const s = (status || '').toLowerCase()
  if (s === 'active' || s === 'completed') return 'bg-emerald-50 text-emerald-600'
  if (s === 'pending') return 'bg-amber-50 text-amber-600'
  return 'bg-sky-50 text-sky-600'
}

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function initials(name) {
  return (name || 'DR')
    .replace(/^Dr\.?\s*/i, '')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0])
    .join('')
    .toUpperCase()
}

onMounted(async () => {
  try {
    const data = await api('/dashboard')
    stats.value = data.kpis || stats.value
    patients.value = data.patients || []
    doctors.value = data.doctors || []
    if (Array.isArray(data.income) && data.income.length) {
      income.value = data.income
    }
    if (Array.isArray(data.medicine) && data.medicine.length === 7) {
      const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
      medicine.value = data.medicine.map((item, i) => ({
        day: days[i],
        pct: item.pct || 0,
      }))
    }
  } catch (e) {
    console.error(e)
  }
})
</script>
