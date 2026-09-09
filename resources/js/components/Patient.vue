<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-xl font-semibold">Patient</h2>
        <p class="text-sm text-slate-400">{{ total }} records from hospital_db</p>
      </div>
      <button @click="showModal = true" class="px-4 py-2 bg-[#2f86f3] text-white rounded-xl text-sm">+ Add Patient</button>
    </div>

    <p v-if="error" class="mb-4 text-sm text-red-500">{{ error }}</p>

    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-slate-400">
          <th class="font-medium pb-3">MRN</th>
          <th class="font-medium pb-3">Name</th>
          <th class="font-medium pb-3">Age</th>
          <th class="font-medium pb-3">Gender</th>
          <th class="font-medium pb-3">Registered</th>
          <th class="font-medium pb-3">Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td colspan="6" class="py-8 text-center text-slate-400">Loading…</td>
        </tr>
        <tr v-else-if="!rows.length">
          <td colspan="6" class="py-8 text-center text-slate-400">No patients found.</td>
        </tr>
        <tr v-for="row in rows" :key="row.id || row.mrn" class="border-t border-slate-100">
          <td class="py-3 font-mono text-slate-600">{{ row.mrn }}</td>
          <td class="py-3 font-medium">{{ row.name }}</td>
          <td class="py-3 text-slate-500">{{ row.age ?? '—' }}</td>
          <td class="py-3">{{ row.gender || '—' }}</td>
          <td class="py-3 text-slate-500">{{ formatDate(row.created_at) }}</td>
          <td class="py-3">
            <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600">{{ row.status }}</span>
          </td>
        </tr>
      </tbody>
    </table>

    <AddPatientModal v-if="showModal" @close="showModal = false" @saved="onSaved" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import AddPatientModal from './AddPatientModal.vue'

const rows = ref([])
const total = ref(0)
const loading = ref(true)
const error = ref('')
const showModal = ref(false)

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
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

function onSaved() {
  showModal.value = false
  load()
}

onMounted(load)
</script>
