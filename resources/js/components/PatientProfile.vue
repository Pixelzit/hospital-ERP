<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <button
      type="button"
      class="text-sm text-[#2f86f3] mb-5 hover:underline"
      @click="$emit('back')"
    >
      ← Back to Patient List
    </button>

    <div class="flex items-start justify-between gap-4 mb-6">
      <div>
        <h2 class="text-xl font-semibold">{{ patient.name }}</h2>
        <p class="text-sm text-slate-400 mt-1">Patient Profile · {{ patient.mrn }}</p>
      </div>
      <span class="text-xs font-medium px-2.5 py-1 rounded-full" :class="statusClass(patient.status)">
        {{ patient.status || '—' }}
      </span>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
      <div v-for="item in fields" :key="item.label" class="rounded-2xl border border-slate-100 bg-[#eef3fb]/60 px-4 py-3">
        <div class="text-[12px] text-slate-400 mb-1">{{ item.label }}</div>
        <div class="text-sm font-medium text-slate-800">{{ item.value }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  patient: { type: Object, required: true },
})

defineEmits(['back'])

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

function statusClass(status) {
  const s = (status || '').toLowerCase()
  if (s === 'active') return 'bg-emerald-50 text-emerald-600'
  if (s === 'inactive') return 'bg-slate-100 text-slate-500'
  return 'bg-amber-50 text-amber-600'
}

const fields = computed(() => [
  { label: 'MRN', value: props.patient.mrn || '—' },
  { label: 'Age', value: props.patient.age ?? '—' },
  { label: 'Gender', value: props.patient.gender || '—' },
  { label: 'Phone', value: props.patient.phone || '—' },
  { label: 'Email', value: props.patient.email || '—' },
  { label: 'Date of birth', value: formatDate(props.patient.date_of_birth) },
  { label: 'Blood group', value: props.patient.blood_group || '—' },
  { label: 'Registered', value: formatDate(props.patient.created_at) },
  { label: 'Occupation', value: props.patient.occupation || '—' },
])
</script>
