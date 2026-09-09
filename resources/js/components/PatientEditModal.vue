<template>
  <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 px-4">
    <form class="bg-white w-full max-w-lg rounded-[22px] p-6" @submit.prevent="save">
      <h2 class="text-xl font-semibold mb-4">Edit Patient</h2>

      <p v-if="error" class="mb-3 text-sm text-red-500">{{ error }}</p>

      <div class="grid grid-cols-2 gap-3">
        <input v-model="form.first_name" type="text" placeholder="First name *" class="col-span-1 border border-slate-200 p-3 rounded-xl text-sm outline-none">
        <input v-model="form.last_name" type="text" placeholder="Last name" class="col-span-1 border border-slate-200 p-3 rounded-xl text-sm outline-none">
        <select v-model="form.gender" class="border border-slate-200 p-3 rounded-xl text-sm outline-none bg-white">
          <option value="">Gender</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>
        <select v-model="form.status" class="border border-slate-200 p-3 rounded-xl text-sm outline-none bg-white">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
        <input v-model="form.date_of_birth" type="date" class="border border-slate-200 p-3 rounded-xl text-sm outline-none">
        <input v-model="form.blood_group" type="text" placeholder="Blood group" class="border border-slate-200 p-3 rounded-xl text-sm outline-none">
        <input v-model="form.phone" type="text" placeholder="Phone" class="border border-slate-200 p-3 rounded-xl text-sm outline-none">
        <input v-model="form.email" type="email" placeholder="Email" class="border border-slate-200 p-3 rounded-xl text-sm outline-none">
      </div>

      <div class="flex gap-3 pt-5">
        <button type="submit" :disabled="saving" class="flex-1 py-3 bg-[#2f86f3] text-white rounded-xl text-sm font-medium hover:bg-[#2476dc] disabled:opacity-50">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
        <button type="button" class="flex-1 py-3 border border-slate-200 rounded-xl text-sm" @click="$emit('close')">Cancel</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { api } from '../api'

const props = defineProps({
  patient: { type: Object, required: true },
})

const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const error = ref('')

const form = reactive({
  first_name: props.patient.first_name || '',
  last_name: props.patient.last_name || '',
  gender: props.patient.gender || '',
  status: props.patient.status || 'active',
  date_of_birth: (props.patient.date_of_birth || '').toString().slice(0, 10),
  blood_group: props.patient.blood_group || '',
  phone: props.patient.phone || '',
  email: props.patient.email || '',
})

async function save() {
  saving.value = true
  error.value = ''
  try {
    await api(`/patients/${props.patient.id}`, {
      method: 'PUT',
      body: JSON.stringify({
        ...form,
        date_of_birth: form.date_of_birth || null,
        email: form.email || null,
        phone: form.phone || null,
        blood_group: form.blood_group || null,
        gender: form.gender || null,
      }),
    })
    emit('saved')
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>
