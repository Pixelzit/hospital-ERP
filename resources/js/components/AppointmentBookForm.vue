<template>
  <div>
    <button type="button" class="text-sm text-[#2f86f3] mb-4 hover:underline" @click="$emit('cancel')">
      ← Back to Appointments
    </button>
    <h3 class="text-lg font-semibold mb-4">{{ appointment ? 'Reschedule appointment' : 'Book appointment' }}</h3>
    <p v-if="error" class="text-sm text-red-500 mb-3">{{ error }}</p>
    <form v-if="!confirming" class="max-w-xl space-y-4" @submit.prevent="review">
      <div>
        <label class="block text-[13px] text-slate-500 mb-1">Department *</label>
        <select v-model="form.department_id" class="w-full h-11 rounded-xl px-3 bg-[#eef3fb] outline-none" @change="onDepartment">
          <option value="">Select department</option>
          <option v-for="row in departments" :key="row.id" :value="row.id">{{ row.name }}</option>
        </select>
        <p v-if="errors.department_id" class="text-xs text-red-500 mt-1">{{ errors.department_id }}</p>
      </div>
      <div>
        <label class="block text-[13px] text-slate-500 mb-1">Doctor *</label>
        <select v-model="form.doctor_id" class="w-full h-11 rounded-xl px-3 bg-[#eef3fb] outline-none" :disabled="!form.department_id" @change="loadSlots">
          <option value="">Select doctor</option>
          <option v-for="row in doctors" :key="row.id" :value="row.id">{{ row.name }}</option>
        </select>
        <p v-if="errors.doctor_id" class="text-xs text-red-500 mt-1">{{ errors.doctor_id }}</p>
      </div>
      <div>
        <label class="block text-[13px] text-slate-500 mb-1">Date *</label>
        <input v-model="form.appointment_date" type="date" class="w-full h-11 rounded-xl px-3 bg-[#eef3fb] outline-none" :min="minDate" @change="loadSlots">
        <p v-if="errors.appointment_date" class="text-xs text-red-500 mt-1">{{ errors.appointment_date }}</p>
      </div>
      <div>
        <label class="block text-[13px] text-slate-500 mb-1">Time slot *</label>
        <select v-model="form.start_time" class="w-full h-11 rounded-xl px-3 bg-[#eef3fb] outline-none" :disabled="!slots.length">
          <option value="">{{ slots.length ? 'Select slot' : 'No slots available' }}</option>
          <option v-for="slot in slots" :key="slot" :value="slot">{{ slot }}</option>
        </select>
        <p v-if="errors.start_time" class="text-xs text-red-500 mt-1">{{ errors.start_time }}</p>
      </div>
      <div>
        <label class="block text-[13px] text-slate-500 mb-1">Appointment type *</label>
        <select v-model="form.appointment_type" class="w-full h-11 rounded-xl px-3 bg-[#eef3fb] outline-none">
          <option value="OPD">OPD</option>
          <option value="Follow-up">Follow-up</option>
          <option value="Review">Review</option>
        </select>
        <p v-if="errors.appointment_type" class="text-xs text-red-500 mt-1">{{ errors.appointment_type }}</p>
      </div>
      <div>
        <label class="block text-[13px] text-slate-500 mb-1">Reason</label>
        <input v-model="form.reason" type="text" class="w-full h-11 rounded-xl px-3 bg-[#eef3fb] outline-none">
      </div>
      <div class="flex gap-2">
        <button type="submit" class="px-4 h-10 rounded-xl bg-[#2f86f3] text-white text-sm">Review</button>
        <button type="button" class="px-4 h-10 rounded-xl border border-slate-200 text-sm" @click="$emit('cancel')">Cancel</button>
      </div>
    </form>
    <div v-else class="max-w-xl rounded-2xl border border-slate-100 p-5 text-sm">
      <div class="flex justify-between py-1"><span class="text-slate-400">Department</span><span>{{ departmentName }}</span></div>
      <div class="flex justify-between py-1"><span class="text-slate-400">Doctor</span><span>{{ doctorName }}</span></div>
      <div class="flex justify-between py-1"><span class="text-slate-400">Date</span><span>{{ form.appointment_date }}</span></div>
      <div class="flex justify-between py-1"><span class="text-slate-400">Time</span><span>{{ form.start_time }}</span></div>
      <div class="flex justify-between py-1"><span class="text-slate-400">Type</span><span>{{ form.appointment_type }}</span></div>
      <p v-if="saving" class="text-slate-400 mt-3">Saving…</p>
      <div class="flex gap-2 mt-4">
        <button type="button" class="px-4 h-10 rounded-xl bg-[#2f86f3] text-white text-sm" :disabled="saving" @click="confirm">Confirm</button>
        <button type="button" class="px-4 h-10 rounded-xl border border-slate-200 text-sm" :disabled="saving" @click="confirming = false">Back</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../api'
import { validateAppointmentForm } from '../patients/appointmentForm.js'

const props = defineProps({
  patientId: { type: String, required: true },
  appointment: { type: Object, default: null },
})
const emit = defineEmits(['cancel', 'saved'])

const departments = ref([])
const doctors = ref([])
const slots = ref([])
const errors = ref({})
const error = ref('')
const confirming = ref(false)
const saving = ref(false)
const minDate = new Date().toISOString().slice(0, 10)
const form = reactive({
  department_id: '',
  doctor_id: '',
  appointment_date: '',
  start_time: '',
  appointment_type: 'OPD',
  reason: '',
})

const departmentName = computed(() => departments.value.find((row) => row.id === form.department_id)?.name || '—')
const doctorName = computed(() => doctors.value.find((row) => row.id === form.doctor_id)?.name || '—')

async function loadDepartments() {
  const data = await api('/departments')
  departments.value = data.data || []
}

async function onDepartment() {
  form.doctor_id = ''
  form.start_time = ''
  slots.value = []
  doctors.value = []
  if (!form.department_id) return
  const data = await api(`/doctors?department_id=${encodeURIComponent(form.department_id)}`)
  doctors.value = data.data || []
}

async function loadSlots() {
  form.start_time = ''
  slots.value = []
  if (!form.doctor_id || !form.appointment_date) return
  const ignore = props.appointment?.id ? `&ignore_appointment_id=${encodeURIComponent(props.appointment.id)}` : ''
  const data = await api(`/appointments/slots?doctor_id=${encodeURIComponent(form.doctor_id)}&date=${encodeURIComponent(form.appointment_date)}${ignore}`)
  slots.value = data.data || []
}

function review() {
  errors.value = validateAppointmentForm(form)
  error.value = ''
  if (Object.keys(errors.value).length) return
  confirming.value = true
}

async function confirm() {
  saving.value = true
  error.value = ''
  try {
    const payload = { ...form, start_time: form.start_time.length === 5 ? `${form.start_time}:00` : form.start_time }
    if (props.appointment?.id) {
      await api(`/appointments/${props.appointment.id}`, { method: 'PUT', body: JSON.stringify(payload) })
    } else {
      await api(`/patients/${props.patientId}/appointments`, { method: 'POST', body: JSON.stringify(payload) })
    }
    emit('saved')
  } catch (e) {
    confirming.value = false
    error.value = e.message
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    await loadDepartments()
    if (props.appointment) {
      form.appointment_type = props.appointment.appointment_type || 'OPD'
      form.appointment_date = String(props.appointment.appointment_date || '').slice(0, 10)
      form.reason = props.appointment.reason || ''
    }
  } catch (e) {
    error.value = e.message
  }
})
</script>
