<template>
  <AddPatientForm v-if="adding" :patients="rows" @cancel="adding = false" @saved="onCreated" />
  <PatientProfile v-else-if="selected" :patient="selected" @back="selected = null" />

  <div v-else class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-xl font-semibold">Patient</h2>
        <p class="text-sm text-slate-400">{{ total }} patient records</p>
      </div>
      <button type="button" class="px-4 py-2 bg-[#2f86f3] text-white rounded-xl text-sm hover:bg-[#2476dc]" @click="adding = true">
        + Add Patient
      </button>
    </div>

    <p v-if="error" class="mb-4 text-sm text-red-500">{{ error }}</p>

    <div class="flex flex-wrap gap-3 mb-5">
      <input
        v-model="filters.search"
        type="text"
        placeholder="Search name, MRN, phone, or email"
        class="flex-1 min-w-[220px] h-11 rounded-xl px-4 text-sm outline-none bg-[#eef3fb] text-slate-800"
      >
      <select v-model="filters.status" class="h-11 rounded-xl px-3 text-sm outline-none bg-[#eef3fb] text-slate-700">
        <option value="all">All status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <select v-model="filters.gender" class="h-11 rounded-xl px-3 text-sm outline-none bg-[#eef3fb] text-slate-700">
        <option value="all">All gender</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
        <option value="Other">Other</option>
      </select>
      <select v-model="filters.ageGroup" class="h-11 rounded-xl px-3 text-sm outline-none bg-[#eef3fb] text-slate-700">
        <option value="all">All ages</option>
        <option value="0-17">0–17</option>
        <option value="18-40">18–40</option>
        <option value="41-60">41–60</option>
        <option value="61+">61+</option>
      </select>
      <input v-model="filters.registeredFrom" type="date" class="h-11 rounded-xl px-3 text-sm outline-none bg-[#eef3fb] text-slate-700" title="Registered from">
      <input v-model="filters.registeredTo" type="date" class="h-11 rounded-xl px-3 text-sm outline-none bg-[#eef3fb] text-slate-700" title="Registered to">
      <button type="button" class="h-11 px-4 rounded-xl border border-slate-200 text-sm text-slate-600 hover:bg-slate-50" @click="resetFilters">
        Reset
      </button>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm min-w-[980px]">
        <thead>
          <tr class="text-left text-slate-400">
            <th class="font-medium pb-3">MRN</th>
            <th class="font-medium pb-3">Name</th>
            <th class="font-medium pb-3">Age</th>
            <th class="font-medium pb-3">Gender</th>
            <th class="font-medium pb-3">Phone</th>
            <th class="font-medium pb-3">Registered</th>
            <th class="font-medium pb-3">Status</th>
            <th class="font-medium pb-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="8" class="py-8 text-center text-slate-400">Loading…</td>
          </tr>
          <tr v-else-if="!pageRows.length">
            <td colspan="8" class="py-8 text-center text-slate-400">No patients found.</td>
          </tr>
          <tr
            v-for="row in pageRows"
            :key="row.id || row.mrn"
            class="border-t border-slate-100 cursor-pointer hover:bg-[#eef3fb]/70"
            @click="openProfile(row)"
          >
            <td class="py-3 font-mono text-slate-600">{{ row.mrn }}</td>
            <td class="py-3 font-medium">{{ row.name }}</td>
            <td class="py-3 text-slate-500">{{ row.age ?? '—' }}</td>
            <td class="py-3">{{ row.gender || '—' }}</td>
            <td class="py-3 text-slate-500">{{ row.phone || '—' }}</td>
            <td class="py-3 text-slate-500">{{ formatDate(row.created_at) }}</td>
            <td class="py-3">
              <span class="text-xs font-medium px-2.5 py-1 rounded-full" :class="statusClass(row.status)">{{ row.status }}</span>
            </td>
            <td class="py-3" @click.stop>
              <div class="relative flex justify-end gap-2">
                <button type="button" class="px-3 py-1.5 text-sm rounded-lg border text-[#2f86f3] border-blue-200 hover:bg-blue-50" @click="openProfile(row)">
                  View
                </button>
                <button type="button" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" @click="editing = row">
                  Edit
                </button>
                <button type="button" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" @click="menuId = menuId === row.id ? null : row.id">
                  More
                </button>
                <div v-if="menuId === row.id" class="absolute right-0 top-9 z-10 w-40 bg-white rounded-xl border border-slate-100 shadow-[0_8px_30px_rgba(47,134,243,0.12)] py-1">
                  <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-[#eef3fb]" @click="copyMrn(row)">
                    {{ copiedId === row.id ? 'Copied' : 'Copy MRN' }}
                  </button>
                  <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-[#eef3fb]" @click="openProfile(row); menuId = null">Open profile</button>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mt-5 text-sm text-slate-500">
      <div>
        Showing {{ pageStart }}–{{ pageEnd }} of {{ filtered.length }}
      </div>
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2">
          <span>Rows</span>
          <select v-model.number="perPage" class="h-9 rounded-xl px-2 outline-none bg-[#eef3fb] text-slate-700">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </label>
        <button type="button" class="h-9 px-3 rounded-xl border border-slate-200 disabled:opacity-40" :disabled="page <= 1" @click="page -= 1">Prev</button>
        <span>{{ page }} / {{ pages }}</span>
        <button type="button" class="h-9 px-3 rounded-xl border border-slate-200 disabled:opacity-40" :disabled="page >= pages" @click="page += 1">Next</button>
      </div>
    </div>

    <PatientEditModal v-if="editing" :patient="editing" @close="editing = null" @saved="onEdited" />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { api } from '../api'
import { filterPatients, paginatePatients } from '../patients/listQuery.js'
import { copyText } from '../patients/copyText.js'
import AddPatientForm from './AddPatientForm.vue'
import PatientEditModal from './PatientEditModal.vue'
import PatientProfile from './PatientProfile.vue'

const rows = ref([])
const total = ref(0)
const loading = ref(true)
const error = ref('')
const adding = ref(false)
const selected = ref(null)
const editing = ref(null)
const menuId = ref(null)
const copiedId = ref(null)
const page = ref(1)
const perPage = ref(10)

const filters = reactive({
  search: '',
  status: 'all',
  gender: 'all',
  ageGroup: 'all',
  registeredFrom: '',
  registeredTo: '',
})

const filtered = computed(() => filterPatients(rows.value, filters))

const paged = computed(() => paginatePatients(filtered.value, page.value, perPage.value))
const pageRows = computed(() => paged.value.rows)
const pages = computed(() => paged.value.pages)
const pageStart = computed(() => (filtered.value.length ? (paged.value.page - 1) * paged.value.perPage + 1 : 0))
const pageEnd = computed(() => Math.min(filtered.value.length, paged.value.page * paged.value.perPage))

watch([filters, perPage], () => {
  page.value = 1
}, { deep: true })

watch(() => paged.value.page, (value) => {
  page.value = value
})

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

function statusClass(status) {
  const s = (status || '').toLowerCase()
  if (s === 'active') return 'bg-emerald-50 text-emerald-600'
  if (s === 'inactive') return 'bg-slate-100 text-slate-500'
  return 'bg-amber-50 text-amber-600'
}

function resetFilters() {
  filters.search = ''
  filters.status = 'all'
  filters.gender = 'all'
  filters.ageGroup = 'all'
  filters.registeredFrom = ''
  filters.registeredTo = ''
  page.value = 1
}

function openProfile(row) {
  menuId.value = null
  selected.value = row
}

async function copyMrn(row) {
  const ok = await copyText(row.mrn || '')
  if (!ok) {
    error.value = 'Could not copy MRN'
    return
  }
  copiedId.value = row.id
  error.value = ''
  window.setTimeout(() => {
    if (copiedId.value === row.id) copiedId.value = null
    if (menuId.value === row.id) menuId.value = null
  }, 1200)
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const data = await api('/patients')
    rows.value = data.data || []
    total.value = data.total || 0
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function onCreated(patient) {
  adding.value = false
  selected.value = patient
  load()
}

function onEdited() {
  editing.value = null
  load()
}

onMounted(load)
</script>
