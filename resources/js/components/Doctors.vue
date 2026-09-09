<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-xl font-semibold">Doctors</h2>
        <p class="text-sm text-slate-400">{{ total }} records from hospital_db</p>
      </div>
      <button @click="openAdd" class="px-4 py-2 bg-[#2f86f3] text-white rounded-xl text-sm">+ Add Doctor</button>
    </div>

    <p v-if="error" class="mb-4 text-sm text-red-500">{{ error }}</p>
    <div v-if="loading" class="py-8 text-center text-slate-400">Loading…</div>
    <div v-else-if="!doctors.length" class="py-8 text-center text-slate-400">No doctors in the database yet.</div>
    <div v-else class="overflow-x-auto">
      <table class="w-full text-sm min-w-[980px]">
        <thead>
          <tr class="text-left text-slate-400">
            <th class="font-medium pb-3">Name</th>
            <th class="font-medium pb-3">Specialization</th>
            <th class="font-medium pb-3">Qualification</th>
            <th class="font-medium pb-3">Registration</th>
            <th class="font-medium pb-3">Experience</th>
            <th class="font-medium pb-3">Fee</th>
            <th class="font-medium pb-3">Phone / Email</th>
            <th class="font-medium pb-3">Status</th>
            <th class="font-medium pb-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="doc in doctors" :key="doc.id" class="border-t border-slate-100 align-top">
            <td class="py-3">
              <div class="font-semibold">{{ doc.name }}</div>
              <div class="text-xs text-slate-400">{{ doc.username || '—' }}</div>
            </td>
            <td class="py-3">{{ doc.specialization || '—' }}</td>
            <td class="py-3">{{ doc.qualification || '—' }}</td>
            <td class="py-3">
              <div>{{ doc.registration_number || '—' }}</div>
              <div class="text-xs text-slate-400">{{ doc.registration_authority || '—' }}</div>
            </td>
            <td class="py-3">{{ doc.experience_years ?? '—' }}</td>
            <td class="py-3">{{ formatFee(doc.consultation_fee) }}</td>
            <td class="py-3">
              <div>{{ doc.phone || '—' }}</div>
              <div class="text-xs text-slate-400">{{ doc.email || '—' }}</div>
            </td>
            <td class="py-3">
              <span class="text-xs font-medium" :class="doc.available ? 'text-emerald-500' : 'text-red-500'">
                {{ doc.status }}
              </span>
            </td>
            <td class="py-3">
              <div class="flex justify-end gap-2">
                <button @click="openEdit(doc)" class="px-3 py-1.5 text-sm rounded-lg border text-[#2f86f3] border-blue-200 hover:bg-blue-50">
                  Edit
                </button>
                <button
                  :disabled="removingId === doc.id"
                  @click="removeDoctor(doc)"
                  class="px-3 py-1.5 text-sm rounded-lg border border-red-200 text-red-600 hover:bg-red-50 disabled:opacity-50"
                >
                  {{ removingId === doc.id ? 'Removing…' : 'Remove' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AddDoctorModal v-if="showModal" :doctor="editing" @close="closeModal" @saved="onSaved" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import AddDoctorModal from './AddDoctorModal.vue'

const doctors = ref([])
const total = ref(0)
const loading = ref(true)
const error = ref('')
const showModal = ref(false)
const editing = ref(null)
const removingId = ref('')

function formatFee(value) {
  if (value === null || value === undefined || value === '') return '—'
  return `₹${Number(value).toLocaleString('en-IN')}`
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const data = await api('/doctors')
    doctors.value = data.data || []
    total.value = data.total || 0
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function openAdd() {
  editing.value = null
  showModal.value = true
}

function openEdit(doc) {
  editing.value = doc
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editing.value = null
}

function onSaved() {
  closeModal()
  load()
}

async function removeDoctor(doc) {
  if (!doc.id) return
  if (!confirm(`Remove ${doc.name}? This deletes the doctor record from the database.`)) return

  removingId.value = doc.id
  error.value = ''
  try {
    await api(`/doctors/${doc.id}`, { method: 'DELETE' })
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    removingId.value = ''
  }
}

onMounted(load)
</script>
