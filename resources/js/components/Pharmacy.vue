<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-xl font-semibold">Pharmacy</h2>
        <p class="text-sm text-slate-400">{{ total }} medicines from hospital_db</p>
      </div>
      <button @click="openAdd" class="px-4 py-2 bg-[#2f86f3] text-white rounded-xl text-sm">+ Add Medicine</button>
    </div>

    <p v-if="error" class="mb-4 text-sm text-red-500">{{ error }}</p>
    <div v-if="loading" class="py-8 text-center text-slate-400">Loading...</div>
    <div v-else-if="!medicines.length" class="py-8 text-center text-slate-400">
      No medicines in the database yet. Use + Add Medicine or run MedicineSeeder.
    </div>
    <div v-else class="overflow-x-auto">
      <table class="w-full text-sm min-w-[720px]">
        <thead>
          <tr class="text-left text-slate-400">
            <th class="font-medium pb-3">Medicine</th>
            <th class="font-medium pb-3">Strength</th>
            <th class="font-medium pb-3">Stock</th>
            <th class="font-medium pb-3">Expiry</th>
            <th class="font-medium pb-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in medicines" :key="row.id" class="border-t border-slate-100">
            <td class="py-3">
              <div class="font-medium">{{ row.name }}</div>
              <div class="text-xs text-slate-400">{{ row.dosage_form || row.manufacturer || '' }}</div>
            </td>
            <td class="py-3 text-slate-500">{{ row.strength || '-' }}</td>
            <td class="py-3">{{ row.stock }}</td>
            <td class="py-3 text-slate-500">{{ row.expiry || '-' }}</td>
            <td class="py-3">
              <span class="text-xs font-medium px-2.5 py-1 rounded-full" :class="row.stock_status_class">
                {{ row.stock_status }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AddMedicineModal v-if="showModal" @close="closeModal" @saved="onSaved" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import AddMedicineModal from './AddMedicineModal.vue'

const medicines = ref([])
const total = ref(0)
const loading = ref(true)
const error = ref('')
const showModal = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const data = await api('/pharmacy/medicines')
    medicines.value = data.data || []
    total.value = data.total || 0
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function openAdd() {
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

function onSaved() {
  closeModal()
  load()
}

onMounted(load)
</script>