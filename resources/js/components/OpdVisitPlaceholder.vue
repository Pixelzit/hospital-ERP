<template>
  <div class="bg-white rounded-2xl border border-slate-100 px-4 py-3 shadow-[0_4px_16px_rgba(47,134,243,0.05)]">
    <div class="flex items-center gap-3 mb-2">
      <button type="button" class="text-[12px] text-[#2f86f3] hover:underline shrink-0 focus:outline-none focus:ring-2 focus:ring-[#2f86f3]/30 rounded" @click="$emit('back')">
        ← Back
      </button>
      <h2 class="text-[16px] font-semibold text-slate-800">OPD Visit</h2>
    </div>

    <p v-if="invalid" class="text-[12px] text-red-500">Invalid visit</p>
    <div v-else>
      <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] border border-slate-100 rounded-lg bg-[#f8fbff] px-3 py-2 mb-3">
        <span class="font-semibold text-slate-800">{{ visit.patient_name || '—' }}</span>
        <span class="text-slate-500">{{ ageGender }}</span>
        <span class="font-mono text-slate-600">{{ visit.uhid || '—' }}</span>
        <span class="font-mono text-slate-600">{{ visit.visit_number || '—' }}</span>
        <span class="text-slate-500">{{ formatDateTime(visit.started_at) }}</span>
        <span class="text-slate-600">{{ visit.doctor || visit.assigned_user || '—' }}</span>
        <span class="text-slate-600">{{ visit.department || '—' }}</span>
      </div>
      <p class="text-[12px] text-slate-400">This clinical workspace is not available yet.</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  visit: { type: Object, default: () => ({}) },
})

defineEmits(['back'])

const invalid = computed(() => !props.visit?.patient_id || !props.visit?.id)

const ageGender = computed(() => {
  const age = props.visit?.age == null || props.visit?.age === '' ? '—' : props.visit.age
  const gender = props.visit?.gender || '—'
  return `${age} / ${gender}`
})

function formatDateTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
