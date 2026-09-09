<template>
  <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white w-full max-w-lg rounded-2xl p-6">
      <h2 class="text-xl font-semibold mb-4">Book New Appointment</h2>
      
      <div class="space-y-4">
        <input v-model="form.appointment_date" type="date" class="w-full border p-3 rounded-xl">
        <input v-model="form.start_time" type="time" class="w-full border p-3 rounded-xl">
        <textarea v-model="form.reason" placeholder="Reason (optional)" class="w-full border p-3 rounded-xl"></textarea>
        
        <div class="flex gap-4 pt-2">
          <button @click="save" class="flex-1 py-3 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700">
            Book Appointment
          </button>
          <button @click="$emit('close')" class="flex-1 py-3 border rounded-xl">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const emit = defineEmits(['close', 'saved'])

const form = ref({
  appointment_date: '',
  start_time: '',
  reason: ''
})

const save = async () => {
  const baseUrl = window.location.origin
  const res = await fetch(`${baseUrl}/api/appointments`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(form.value)
  })

  if (res.ok) {
    emit('saved')
    form.value = { appointment_date: '', start_time: '', reason: '' }
  } else {
    const data = await res.json()
    alert(data.message || 'Error booking appointment')
  }
}
</script>