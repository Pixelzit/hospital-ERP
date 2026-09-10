<template>
  <div>
    <button type="button" class="text-sm text-[#2f86f3] mb-4 hover:underline" @click="$emit('back')">
      ← Back to Medical History
    </button>
    <p v-if="loading" class="text-sm text-slate-400">Loading visit…</p>
    <p v-else-if="error" class="text-sm text-red-500">{{ error }}</p>
    <div v-else-if="visit">
      <h3 class="text-lg font-semibold mb-1">Visit {{ visit.number || '' }}</h3>
      <p class="text-sm text-slate-500 mb-5">{{ formatDateTime(visit.started_at) }} · {{ visit.visit_type || '—' }} · {{ visit.status || '—' }}</p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <section class="rounded-2xl border border-slate-100 p-5 text-sm">
          <h4 class="font-semibold mb-3">Visit information</h4>
          <div class="flex justify-between py-1"><span class="text-slate-400">Doctor</span><span class="font-medium">{{ visit.doctor }}</span></div>
          <div class="flex justify-between py-1"><span class="text-slate-400">Department</span><span class="font-medium">{{ visit.department }}</span></div>
          <div class="flex justify-between py-1"><span class="text-slate-400">Started</span><span class="font-medium">{{ formatDateTime(visit.started_at) }}</span></div>
          <div class="flex justify-between py-1"><span class="text-slate-400">Ended</span><span class="font-medium">{{ formatDateTime(visit.ended_at) }}</span></div>
        </section>
        <section class="rounded-2xl border border-slate-100 p-5 text-sm">
          <h4 class="font-semibold mb-3">Follow-up</h4>
          <p v-if="visit.follow_up">{{ formatDate(visit.follow_up.date) }} {{ visit.follow_up.time || '' }} · {{ visit.follow_up.status }}</p>
          <p v-else class="text-slate-400">No upcoming follow-up appointment.</p>
        </section>
      </div>
      <section class="rounded-2xl border border-slate-100 p-5 text-sm mb-4">
        <h4 class="font-semibold mb-3">Diagnosis</h4>
        <p v-if="!visit.diagnoses?.length" class="text-slate-400">No diagnosis recorded for this visit.</p>
        <div v-for="row in visit.diagnoses" :key="row.id" class="border-t border-slate-100 py-2 first:border-0">
          <div class="font-medium">{{ row.name || '—' }}</div>
          <div class="text-slate-500">{{ row.type }} · {{ row.status }} {{ row.notes ? '· ' + row.notes : '' }}</div>
        </div>
      </section>
      <section v-if="visit.vitals?.length" class="rounded-2xl border border-slate-100 p-5 text-sm mb-4">
        <h4 class="font-semibold mb-3">Vitals</h4>
        <div v-for="row in visit.vitals" :key="row.id" class="text-slate-600">
          {{ formatDateTime(row.recorded_at) }}
          <span v-if="row.temperature"> · Temp {{ row.temperature }}</span>
          <span v-if="row.pulse"> · Pulse {{ row.pulse }}</span>
          <span v-if="row.systolic_bp"> · BP {{ row.systolic_bp }}/{{ row.diastolic_bp }}</span>
        </div>
      </section>
      <section class="rounded-2xl border border-slate-100 p-5 text-sm mb-4">
        <h4 class="font-semibold mb-3">Investigations</h4>
        <p v-if="!visit.lab_reports?.length && !visit.radiology?.length" class="text-slate-400">No investigations for this visit.</p>
        <div v-for="row in visit.lab_reports" :key="row.id">Lab {{ row.number }} · {{ row.status }}</div>
        <div v-for="row in visit.radiology" :key="row.id">Radiology {{ row.number }} {{ row.test ? '· ' + row.test : '' }} · {{ row.status }}</div>
      </section>
      <section class="rounded-2xl border border-slate-100 p-5 text-sm mb-4">
        <h4 class="font-semibold mb-3">Prescriptions</h4>
        <p v-if="!visit.prescriptions?.length" class="text-slate-400">No prescriptions for this visit.</p>
        <div v-for="row in visit.prescriptions" :key="row.id">{{ row.number }} · {{ row.status }}</div>
      </section>
      <section class="rounded-2xl border border-slate-100 p-5 text-sm">
        <h4 class="font-semibold mb-3">Clinical notes</h4>
        <p v-if="!visit.clinical_notes?.length" class="text-slate-400">No clinical notes for this visit.</p>
        <div v-for="row in visit.clinical_notes" :key="row.id" class="mb-2">
          <div class="text-slate-400 text-xs">{{ row.type }} · {{ formatDateTime(row.created_at) }}</div>
          <div>{{ row.content }}</div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { api } from '../api'

const props = defineProps({
  patientId: { type: String, required: true },
  visitId: { type: String, required: true },
})
defineEmits(['back'])

const loading = ref(false)
const error = ref('')
const visit = ref(null)

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const data = await api(`/patients/${props.patientId}/encounters/${props.visitId}`)
    visit.value = data.data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

watch(() => props.visitId, load)
onMounted(load)
</script>
