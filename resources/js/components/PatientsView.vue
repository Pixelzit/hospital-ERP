<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-3xl font-bold">Patients</h1>
        <p class="text-gray-600">{{ patients.length }} patients found</p>
      </div>
      <button @click="$emit('add-patient')" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700">
        + Add New Patient
      </button>
    </div>

    <div class="bg-white rounded-2xl border overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr class="text-left text-sm text-gray-600">
            <th class="px-6 py-4 font-medium">MRN</th>
            <th class="px-6 py-4 font-medium">Name</th>
            <th class="px-6 py-4 font-medium">Gender</th>
            <th class="px-6 py-4 font-medium">Status</th>
            <th class="px-6 py-4 font-medium">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="patient in filteredPatients" :key="patient.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 font-mono text-sm">{{ patient.mrn }}</td>
            <td class="px-6 py-4 font-medium">{{ patient.first_name }} {{ patient.last_name }}</td>
            <td class="px-6 py-4">{{ patient.gender || '—' }}</td>
            <td class="px-6 py-4">
              <span :class="patient.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'" 
                    class="px-3 py-1 rounded-full text-xs font-medium">
                {{ patient.status }}
              </span>
            </td>
            <td class="px-6 py-4">
              <button class="text-blue-600 hover:underline text-sm">View</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  patients: Array,
  searchQuery: String
})

defineEmits(['add-patient', 'update-search'])

const filteredPatients = computed(() => {
  if (!props.searchQuery) return props.patients
  return props.patients.filter(p => 
    p.mrn?.toLowerCase().includes(props.searchQuery.toLowerCase()) ||
    `${p.first_name} ${p.last_name}`.toLowerCase().includes(props.searchQuery.toLowerCase())
  )
})
</script>