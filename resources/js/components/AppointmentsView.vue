<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-3xl font-bold">Appointments</h1>
        <p class="text-gray-600">{{ appointments.length }} appointments found</p>
      </div>
      <button @click="$emit('add-appointment')" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700">
        + Book Appointment
      </button>
    </div>

    <div class="bg-white rounded-2xl border overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr class="text-left text-sm text-gray-600">
            <th class="px-6 py-4 font-medium">Appointment #</th>
            <th class="px-6 py-4 font-medium">Patient</th>
            <th class="px-6 py-4 font-medium">Date</th>
            <th class="px-6 py-4 font-medium">Time</th>
            <th class="px-6 py-4 font-medium">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="appt in appointments" :key="appt.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 font-mono text-sm">{{ appt.appointment_number }}</td>
            <td class="px-6 py-4">{{ appt.patient?.first_name }} {{ appt.patient?.last_name || 'N/A' }}</td>
            <td class="px-6 py-4">{{ appt.appointment_date }}</td>
            <td class="px-6 py-4">{{ appt.start_time }}</td>
            <td class="px-6 py-4">
              <span class="px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                {{ appt.status }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
defineProps({
  appointments: Array
})

defineEmits(['add-appointment'])
</script>