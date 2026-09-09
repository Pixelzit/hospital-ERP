<template>
  <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white w-full max-w-lg rounded-2xl p-6">
      <h2 class="text-xl font-semibold mb-4">Add New Patient</h2>
      
      <div class="space-y-4">
        <input v-model="form.first_name" type="text" placeholder="First Name *" class="w-full border p-3 rounded-xl">
        <input v-model="form.last_name" type="text" placeholder="Last Name *" class="w-full border p-3 rounded-xl">
        <input v-model="form.mrn" type="text" placeholder="MRN *" class="w-full border p-3 rounded-xl">
        
        <div class="flex gap-4 pt-2">
          <button @click="savePatient" class="flex-1 py-3 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700">
            Save Patient
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
  first_name: '',
  last_name: '',
  mrn: ''
})

const savePatient = async () => {
  try {
    const baseUrl = window.location.origin
    const res = await fetch(`${baseUrl}/api/patients`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(form.value)
    })

    if (res.ok) {
      emit('saved')
      form.value = { first_name: '', last_name: '', mrn: '' }
    } else {
      const data = await res.json()
      alert(data.message || 'Error saving patient')
    }
  } catch (error) {
    alert('Network error: ' + error.message)
  }
}
</script>