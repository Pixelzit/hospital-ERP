<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-xl font-semibold">Appointments</h2>
        <p class="text-sm text-slate-400">{{ total }} records from hospital_db</p>
      </div>
    </div>

    <p v-if="error" class="mb-4 text-sm text-red-500">{{ error }}</p>

    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-slate-400">
          <th class="font-medium pb-3">Appointment #</th>
          <th class="font-medium pb-3">Patient</th>
          <th class="font-medium pb-3">Doctor</th>
          <th class="font-medium pb-3">Date & Time</th>
          <th class="font-medium pb-3">Type</th>
          <th class="font-medium pb-3">Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td colspan="6" class="py-8 text-center text-slate-400">Loading…</td>
        </tr>
        <tr v-else-if="!rows.length">
          <td colspan="6" class="py-8 text-center text-slate-400">No appointments in the database yet.</td>
        </tr>
        <tr v-for="row in rows" :key="row.id || row.appointment_number" class="border-t border-slate-100">
          <td class="py-3 font-mono text-slate-600">{{ row.appointment_number }}</td>
          <td class="py-3 font-medium">{{ row.patient }}</td>
          <td class="py-3">{{ row.doctor }}</td>
          <td class="py-3 text-slate-500">{{ row.appointment_date }} {{ row.start_time || '' }}</td>
          <td class="py-3">{{ row.appointment_type }}</td>
          <td class="py-3">
            <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-sky-50 text-sky-600">{{ row.status }}</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'

const rows = ref([])
const total = ref(0)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const data = await api('/appointments')
    rows.value = data.data || []
    total.value = data.total || 0
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})
</script>
