<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <div class="flex items-start justify-between gap-4 mb-6">
      <div>
        <h2 class="text-xl font-semibold">{{ isEdit ? 'Edit Patient' : 'Add New Patient' }}</h2>
        <p class="text-sm text-slate-400">{{ isEdit ? 'Update demographic and contact information' : 'Register a patient in HCS Hospital ERP' }}</p>
      </div>
    </div>

    <p v-if="formError" class="mb-4 text-sm text-red-500">{{ formError }}</p>

    <div v-if="duplicates.length" class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      <div class="font-medium mb-1">Possible duplicate patient found</div>
      <p v-for="row in duplicates" :key="row.id || row.mrn">
        {{ row.name }} · {{ row.mrn }} · {{ row.phone || 'no phone' }}
      </p>
      <p class="mt-2 text-amber-700">Review the match, or save anyway if this is a different person.</p>
    </div>

    <form class="space-y-5" @submit.prevent="save">
      <section class="rounded-2xl border border-slate-100 p-5">
        <h3 class="text-[17px] font-semibold text-slate-900 mb-4">1. Personal Details</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label class="md:col-span-2 block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Full Name <span class="text-red-500">*</span></span>
            <input v-model="form.full_name" type="text" class="field" :class="errorClass('full_name')">
            <span v-if="errors.full_name" class="err">{{ errors.full_name }}</span>
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Date of Birth <span class="text-red-500">*</span></span>
            <input v-model="form.date_of_birth" type="date" class="field" :class="errorClass('date_of_birth')">
            <span v-if="errors.date_of_birth" class="err">{{ errors.date_of_birth }}</span>
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Gender <span class="text-red-500">*</span></span>
            <select v-model="form.gender" class="field bg-white" :class="errorClass('gender')">
              <option value="">Select</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
            <span v-if="errors.gender" class="err">{{ errors.gender }}</span>
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Blood Group</span>
            <select v-model="form.blood_group" class="field bg-white">
              <option value="">Select</option>
              <option v-for="group in bloodGroups" :key="group" :value="group">{{ group }}</option>
            </select>
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Marital Status</span>
            <select v-model="form.marital_status" class="field bg-white">
              <option value="">Select</option>
              <option value="Single">Single</option>
              <option value="Married">Married</option>
              <option value="Widowed">Widowed</option>
              <option value="Divorced">Divorced</option>
            </select>
          </label>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-100 p-5">
        <h3 class="text-[17px] font-semibold text-slate-900 mb-4">2. Contact Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Mobile <span class="text-red-500">*</span></span>
            <input v-model="form.phone" type="tel" class="field" :class="errorClass('phone')">
            <span v-if="errors.phone" class="err">{{ errors.phone }}</span>
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Email</span>
            <input v-model="form.email" type="email" class="field" :class="errorClass('email')">
            <span v-if="errors.email" class="err">{{ errors.email }}</span>
          </label>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-100 p-5">
        <h3 class="text-[17px] font-semibold text-slate-900 mb-4">3. Address</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label class="md:col-span-2 block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Address Line 1 <span class="text-red-500">*</span></span>
            <input v-model="form.address_line_1" type="text" class="field" :class="errorClass('address_line_1')">
            <span v-if="errors.address_line_1" class="err">{{ errors.address_line_1 }}</span>
          </label>
          <label class="md:col-span-2 block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Address Line 2</span>
            <input v-model="form.address_line_2" type="text" class="field">
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">City</span>
            <input v-model="form.city" type="text" class="field">
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">State</span>
            <input v-model="form.state" type="text" class="field">
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Pincode</span>
            <input v-model="form.postal_code" type="text" class="field">
          </label>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-100 p-5">
        <h3 class="text-[17px] font-semibold text-slate-900 mb-4">4. Emergency Contact</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Emergency Contact Name <span class="text-red-500">*</span></span>
            <input v-model="form.emergency_name" type="text" class="field" :class="errorClass('emergency_name')">
            <span v-if="errors.emergency_name" class="err">{{ errors.emergency_name }}</span>
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Relationship <span class="text-red-500">*</span></span>
            <select v-model="form.emergency_relationship" class="field bg-white" :class="errorClass('emergency_relationship')">
              <option value="">Select</option>
              <option value="Spouse">Spouse</option>
              <option value="Parent">Parent</option>
              <option value="Child">Child</option>
              <option value="Sibling">Sibling</option>
              <option value="Other">Other</option>
            </select>
            <span v-if="errors.emergency_relationship" class="err">{{ errors.emergency_relationship }}</span>
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Phone <span class="text-red-500">*</span></span>
            <input v-model="form.emergency_phone" type="tel" class="field" :class="errorClass('emergency_phone')">
            <span v-if="errors.emergency_phone" class="err">{{ errors.emergency_phone }}</span>
          </label>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-100 p-5">
        <h3 class="text-[17px] font-semibold text-slate-900 mb-4">5. Identification</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">ID Type</span>
            <select v-model="form.id_type" class="field bg-white" :class="errorClass('id_number')">
              <option value="">Select</option>
              <option value="Aadhaar">Aadhaar</option>
              <option value="PAN">PAN</option>
              <option value="Passport">Passport</option>
              <option value="Driving Licence">Driving Licence</option>
              <option value="Voter ID">Voter ID</option>
            </select>
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">ID Number</span>
            <input v-model="form.id_number" type="text" class="field" :class="errorClass('id_number')">
            <span v-if="errors.id_number" class="err">{{ errors.id_number }}</span>
          </label>
          <label class="md:col-span-2 block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">ID Proof Upload</span>
            <input type="file" accept="image/*,.pdf" class="block w-full text-sm text-slate-500" @change="onFile">
            <span v-if="fileName" class="text-xs text-slate-400 mt-1 block">{{ fileName }}</span>
            <span v-if="errors.id_proof" class="err">{{ errors.id_proof }}</span>
          </label>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-100 p-5">
        <h3 class="text-[17px] font-semibold text-slate-900 mb-4">6. Medical Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Allergies</span>
            <textarea v-model="form.allergies" rows="3" class="field h-auto py-3" placeholder="Comma or line separated"></textarea>
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Existing Conditions</span>
            <textarea v-model="form.conditions" rows="3" class="field h-auto py-3" placeholder="Comma or line separated"></textarea>
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Current Medications</span>
            <textarea v-model="form.medications" rows="3" class="field h-auto py-3"></textarea>
          </label>
          <label class="block">
            <span class="block text-[13px] font-medium text-slate-500 mb-1.5">Notes</span>
            <textarea v-model="form.notes" rows="3" class="field h-auto py-3"></textarea>
          </label>
        </div>
      </section>

      <div class="flex flex-wrap gap-3 pt-2">
        <button type="submit" :disabled="saving" class="px-5 h-11 rounded-xl bg-[#2f86f3] text-white text-sm font-medium hover:bg-[#2476dc] disabled:opacity-50">
          {{ saveLabel }}
        </button>
        <button type="button" class="px-5 h-11 rounded-xl border border-slate-200 text-sm text-slate-600 hover:bg-slate-50" @click="$emit('cancel')">
          Cancel
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../api'
import { findDuplicatePatients, splitFullName, validatePatientForm } from '../patients/addPatientForm.js'
import { profileToForm } from '../patients/profileWorkspace.js'

const props = defineProps({
  patients: { type: Array, default: () => [] },
  patient: { type: Object, default: null },
})

const emit = defineEmits(['cancel', 'saved'])
const isEdit = computed(() => Boolean(props.patient?.id))

const bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
const saving = ref(false)
const formError = ref('')
const errors = ref({})
const duplicates = ref([])
const forceSave = ref(false)
const fileName = ref('')

const form = reactive({
  full_name: '',
  date_of_birth: '',
  gender: '',
  blood_group: '',
  marital_status: '',
  phone: '',
  email: '',
  address_line_1: '',
  address_line_2: '',
  city: '',
  state: '',
  postal_code: '',
  emergency_name: '',
  emergency_relationship: '',
  emergency_phone: '',
  id_type: '',
  id_number: '',
  id_proof: null,
  allergies: '',
  conditions: '',
  medications: '',
  notes: '',
})

const saveLabel = computed(() => {
  if (saving.value) return 'Saving…'
  if (forceSave.value && duplicates.value.length) return 'Save anyway'
  return isEdit.value ? 'Save Patient' : 'Save Patient'
})

function errorClass(field) {
  return errors.value[field] ? 'ring-1 ring-red-400' : ''
}

function onFile(event) {
  const file = event.target.files?.[0]
  errors.value = { ...errors.value, id_proof: undefined }
  if (!file) {
    form.id_proof = null
    fileName.value = ''
    return
  }
  if (file.size > 5 * 1024 * 1024) {
    errors.value = { ...errors.value, id_proof: 'File must be under 5 MB' }
    form.id_proof = null
    fileName.value = ''
    return
  }
  fileName.value = file.name
  const reader = new FileReader()
  reader.onload = () => {
    const raw = String(reader.result || '')
    form.id_proof = {
      name: file.name,
      type: file.type || 'application/octet-stream',
      content: raw.includes(',') ? raw.split(',')[1] : raw,
    }
  }
  reader.readAsDataURL(file)
}

function payload(force) {
  const names = splitFullName(form.full_name)
  return {
    first_name: names.first_name,
    last_name: names.last_name || null,
    gender: form.gender,
    date_of_birth: form.date_of_birth,
    blood_group: form.blood_group || null,
    marital_status: form.marital_status || null,
    phone: form.phone,
    email: form.email || null,
    address_line_1: form.address_line_1,
    address_line_2: form.address_line_2 || null,
    city: form.city || null,
    state: form.state || null,
    postal_code: form.postal_code || null,
    emergency_name: form.emergency_name,
    emergency_relationship: form.emergency_relationship || null,
    emergency_phone: form.emergency_phone,
    id_type: form.id_type || null,
    id_number: form.id_number || null,
    id_proof: form.id_proof,
    allergies: form.allergies || null,
    conditions: form.conditions || null,
    medications: form.medications || null,
    notes: form.notes || null,
    force,
  }
}

async function save() {
  formError.value = ''
  errors.value = validatePatientForm(form)
  if (Object.keys(errors.value).length) return

  const localHits = findDuplicatePatients(props.patients, form).filter((row) => row.id !== props.patient?.id)
  if (localHits.length && !forceSave.value && !isEdit.value) {
    duplicates.value = localHits
    forceSave.value = true
    return
  }

  saving.value = true
  try {
    const path = isEdit.value ? `/patients/${props.patient.id}` : '/patients'
    const data = await api(path, {
      method: isEdit.value ? 'PUT' : 'POST',
      body: JSON.stringify(payload(forceSave.value)),
    })
    emit('saved', data.data)
  } catch (e) {
    if (e.status === 409) {
      duplicates.value = e.payload?.duplicates || []
      forceSave.value = true
      formError.value = e.message
    } else {
      formError.value = e.message
    }
  } finally {
    saving.value = false
  }
}

async function hydrate() {
  if (!props.patient?.id) return
  let source = props.patient
  if (!props.patient.address && !props.patient.emergency) {
    try {
      const data = await api(`/patients/${props.patient.id}`)
      source = data.data
    } catch (e) {
      formError.value = e.message
    }
  }
  Object.assign(form, profileToForm(source))
}

onMounted(hydrate)
</script>

<style scoped>
.field {
  width: 100%;
  height: 2.75rem;
  border-radius: 0.75rem;
  padding: 0 1rem;
  font-size: 0.875rem;
  outline: none;
  background: #eef3fb;
  color: #1e293b;
}
.err {
  display: block;
  margin-top: 0.25rem;
  font-size: 0.75rem;
  color: #ef4444;
}
</style>
