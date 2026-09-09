<template>
  <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
    <div class="bg-white w-full max-w-2xl rounded-2xl p-6 max-h-[90vh] overflow-y-auto">
      <h2 class="text-xl font-semibold mb-4">{{ doctor ? 'Edit Doctor' : 'Add Doctor' }}</h2>

      <p v-if="error" class="mb-3 text-sm text-red-500">{{ error }}</p>

      <div class="grid grid-cols-2 gap-3">
        <input v-model="form.first_name" type="text" placeholder="First Name *" class="border p-3 rounded-xl">
        <input v-model="form.last_name" type="text" placeholder="Last Name" class="border p-3 rounded-xl">
        <input v-model="form.registration_number" type="text" placeholder="Registration Number *" class="border p-3 rounded-xl">
        <input v-model="form.registration_authority" type="text" placeholder="Registration Authority" class="border p-3 rounded-xl">
        <input v-model="form.specialization" type="text" placeholder="Specialization" class="border p-3 rounded-xl">
        <input v-model="form.qualification" type="text" placeholder="Qualification" class="border p-3 rounded-xl col-span-2">
        <input v-model="form.experience_years" type="number" step="0.1" placeholder="Experience (years)" class="border p-3 rounded-xl">
        <input v-model="form.consultation_fee" type="number" step="0.01" placeholder="Consultation Fee" class="border p-3 rounded-xl">
        <input v-model="form.phone" type="text" placeholder="Phone" class="border p-3 rounded-xl">
        <input v-model="form.email" type="email" placeholder="Email" class="border p-3 rounded-xl">
        <select v-model="form.status" class="border p-3 rounded-xl col-span-2 bg-white">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div class="flex gap-4 pt-5">
        <button :disabled="saving" @click="save" class="flex-1 py-3 bg-[#2f86f3] text-white rounded-xl font-medium disabled:opacity-60">
          {{ saving ? 'Saving…' : (doctor ? 'Update Doctor' : 'Save Doctor') }}
        </button>
        <button @click="$emit('close')" class="flex-1 py-3 border rounded-xl">Cancel</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { api } from '../api'

const props = defineProps({
  doctor: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const error = ref('')
const form = ref({
  first_name: props.doctor?.first_name || '',
  last_name: props.doctor?.last_name || '',
  registration_number: props.doctor?.registration_number || '',
  registration_authority: props.doctor?.registration_authority || '',
  specialization: props.doctor?.specialization || '',
  qualification: props.doctor?.qualification || '',
  experience_years: props.doctor?.experience_years ?? '',
  consultation_fee: props.doctor?.consultation_fee ?? '',
  phone: props.doctor?.phone || '',
  email: props.doctor?.email || '',
  status: props.doctor?.status || 'active',
})

async function save() {
  error.value = ''
  if (!form.value.first_name || !form.value.registration_number) {
    error.value = 'First name and registration number are required.'
    return
  }

  const payload = {
    ...form.value,
    experience_years: form.value.experience_years === '' ? null : form.value.experience_years,
    consultation_fee: form.value.consultation_fee === '' ? null : form.value.consultation_fee,
    email: form.value.email || null,
  }

  saving.value = true
  try {
    if (props.doctor?.id) {
      await api(`/doctors/${props.doctor.id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
      })
    } else {
      await api('/doctors', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
    }
    emit('saved')
  } catch (e) {
    const details = e.payload?.errors
    error.value = details ? Object.values(details).flat().join(' ') : e.message
  } finally {
    saving.value = false
  }
}
</script>
