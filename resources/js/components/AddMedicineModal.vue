<template>
  <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
    <div class="bg-white w-full max-w-2xl rounded-2xl p-6 max-h-[90vh] overflow-y-auto">
      <h2 class="text-xl font-semibold mb-4">Add Medicine</h2>

      <p v-if="error" class="mb-3 text-sm text-red-500">{{ error }}</p>

      <div class="grid grid-cols-2 gap-3">
        <input v-model="form.generic_name" type="text" placeholder="Generic Name *" class="border p-3 rounded-xl col-span-2">
        <input v-model="form.brand_name" type="text" placeholder="Brand Name" class="border p-3 rounded-xl">
        <input v-model="form.strength" type="text" placeholder="Strength (e.g. 500 mg)" class="border p-3 rounded-xl">
        <input v-model="form.dosage_form" type="text" placeholder="Dosage Form (tablet, syrup)" class="border p-3 rounded-xl">
        <input v-model="form.route" type="text" placeholder="Route (oral, IV)" class="border p-3 rounded-xl">
        <input v-model="form.manufacturer" type="text" placeholder="Manufacturer" class="border p-3 rounded-xl col-span-2">
        <select v-model="form.status" class="border p-3 rounded-xl col-span-2 bg-white">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div class="flex gap-4 pt-5">
        <button :disabled="saving" @click="save" class="flex-1 py-3 bg-[#2f86f3] text-white rounded-xl font-medium disabled:opacity-60">
          {{ saving ? 'Saving...' : 'Save Medicine' }}
        </button>
        <button @click="$emit('close')" class="flex-1 py-3 border rounded-xl">Cancel</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { api } from '../api'

const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const error = ref('')
const form = ref({
  generic_name: '',
  brand_name: '',
  strength: '',
  dosage_form: '',
  route: '',
  manufacturer: '',
  status: 'active',
})

async function save() {
  error.value = ''
  if (!form.value.generic_name.trim()) {
    error.value = 'Generic name is required.'
    return
  }

  const payload = {
    generic_name: form.value.generic_name.trim(),
    brand_name: form.value.brand_name.trim() || null,
    strength: form.value.strength.trim() || null,
    dosage_form: form.value.dosage_form.trim() || null,
    route: form.value.route.trim() || null,
    manufacturer: form.value.manufacturer.trim() || null,
    status: form.value.status || 'active',
  }

  saving.value = true
  try {
    await api('/pharmacy/medicines', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
    emit('saved')
  } catch (e) {
    const details = e.payload?.errors
    error.value = details ? Object.values(details).flat().join(' ') : e.message
  } finally {
    saving.value = false
  }
}
</script>