<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
      <div>
        <button type="button" class="text-sm text-[#2f86f3] mb-2 hover:underline" @click="$emit('back')">
          ← Back to Patient List
        </button>
        <h2 class="text-xl font-semibold">Patient Profile</h2>
        <p class="text-sm text-slate-400">Complete patient information and clinical summary</p>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" class="px-4 h-10 rounded-xl bg-[#2f86f3] text-white text-sm font-medium hover:bg-[#2476dc]" @click="$emit('edit', profile)">
          Edit Patient
        </button>
        <div class="relative">
          <button type="button" class="px-3 h-10 rounded-xl border border-slate-200 text-sm text-slate-600 hover:bg-slate-50" @click="menuOpen = !menuOpen">
            More
          </button>
          <div v-if="menuOpen" class="absolute right-0 top-11 z-20 w-52 bg-white rounded-xl border border-slate-100 shadow-[0_8px_30px_rgba(47,134,243,0.12)] py-1">
            <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-[#eef3fb]" @click="startBook">Book Appointment</button>
            <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-[#eef3fb]" @click="goTab('history')">View Medical History</button>
            <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-[#eef3fb]" @click="goTab('documents')">Upload Document</button>
            <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-[#eef3fb]" @click="goTab('billing')">View Billing</button>
            <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-[#eef3fb]" @click="printSummary">Print Patient Summary</button>
            <button type="button" class="w-full text-left px-3 py-2 text-sm text-red-500 hover:bg-red-50" @click="askDeactivate = true; menuOpen = false">Deactivate Patient</button>
          </div>
        </div>
      </div>
    </div>

    <p v-if="loading" class="text-sm text-slate-400 mb-4">Loading patient information…</p>
    <p v-else-if="error" class="text-sm text-red-500 mb-4">{{ error }}</p>

    <div v-if="askDeactivate" class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      <div class="font-medium mb-1">Deactivate {{ profile.name }} ({{ profile.mrn }})?</div>
      <p class="mb-3">This does not delete clinical or billing history.</p>
      <div class="flex gap-2">
        <button type="button" class="px-3 h-9 rounded-xl bg-amber-600 text-white text-sm" :disabled="savingStatus" @click="deactivate">{{ savingStatus ? 'Saving…' : 'Confirm deactivate' }}</button>
        <button type="button" class="px-3 h-9 rounded-xl border border-amber-200 text-sm" @click="askDeactivate = false">Cancel</button>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] gap-4 mb-5">
      <div class="rounded-2xl border border-slate-100 bg-[#eef3fb]/50 p-5">
        <div class="flex items-start gap-4">
          <div class="h-16 w-16 rounded-2xl bg-[#2f86f3] text-white flex items-center justify-center text-xl font-semibold shrink-0">
            {{ initials }}
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-lg font-semibold text-slate-900 truncate">{{ profile.name || '—' }}</h3>
              <span class="text-xs font-medium px-2.5 py-1 rounded-full" :class="statusClass(profile.status)">{{ profile.status || '—' }}</span>
            </div>
            <p class="text-sm text-slate-500 mt-1">
              {{ profile.mrn || '—' }}
              <span v-if="profile.age != null"> · {{ profile.age }} Years</span>
              <span v-if="profile.gender"> · {{ profile.gender }}</span>
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4 text-sm">
              <div>
                <div class="text-[12px] text-slate-400 mb-0.5">Phone</div>
                <div class="font-medium text-slate-800">{{ profile.phone || '—' }}</div>
              </div>
              <div>
                <div class="text-[12px] text-slate-400 mb-0.5">Email</div>
                <div class="font-medium text-slate-800 break-all">{{ profile.email || '—' }}</div>
              </div>
              <div class="sm:col-span-1">
                <div class="text-[12px] text-slate-400 mb-0.5">Address</div>
                <div class="font-medium text-slate-800">{{ profile.address_text || '—' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <button type="button" class="rounded-2xl border border-slate-100 bg-white px-4 py-3 text-left hover:bg-[#eef3fb]/70" @click="goTab('history')">
          <div class="text-xl font-semibold text-slate-900">{{ summary.visits }}</div>
          <div class="text-xs text-slate-400 mt-1">Total Visits</div>
        </button>
        <button type="button" class="rounded-2xl border border-slate-100 bg-white px-4 py-3 text-left hover:bg-[#eef3fb]/70" @click="goTab('overview')">
          <div class="text-xl font-semibold text-slate-900">{{ summary.conditions }}</div>
          <div class="text-xs text-slate-400 mt-1">Active Conditions</div>
        </button>
        <button type="button" class="rounded-2xl border border-slate-100 bg-white px-4 py-3 text-left hover:bg-[#eef3fb]/70" @click="goTab('overview')">
          <div class="text-xl font-semibold text-slate-900">{{ summary.allergies }}</div>
          <div class="text-xs text-slate-400 mt-1">Allergies</div>
        </button>
        <button type="button" class="rounded-2xl border border-slate-100 bg-white px-4 py-3 text-left hover:bg-[#eef3fb]/70" @click="goTab('appointments')">
          <div class="text-xl font-semibold text-slate-900">{{ summary.upcoming_appointments }}</div>
          <div class="text-xs text-slate-400 mt-1">Upcoming Appointments</div>
        </button>
      </div>
    </div>

    <div class="flex overflow-x-auto gap-1 border-b border-slate-100 mb-5">
      <button
        v-for="item in tabs"
        :key="item.id"
        type="button"
        class="px-4 h-11 text-sm whitespace-nowrap border-b-2 -mb-px"
        :class="tab === item.id ? 'border-[#2f86f3] text-[#2f86f3] font-medium' : 'border-transparent text-slate-500'"
        @click="goTab(item.id)"
      >
        {{ item.label }}
      </button>
    </div>

    <VisitDetail v-if="visitId" :patient-id="profile.id" :visit-id="visitId" @back="closeVisit" />
    <AppointmentBookForm v-else-if="booking" :patient-id="profile.id" :appointment="reschedule" @cancel="closeBook" @saved="onAppointmentSaved" />

    <div v-else-if="tab === 'overview'" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <section class="rounded-2xl border border-slate-100 p-5">
        <h4 class="text-[15px] font-semibold mb-4">Personal Information</h4>
        <dl class="space-y-3 text-sm">
          <div v-for="item in personalFields" :key="item.label" class="flex justify-between gap-3">
            <dt class="text-slate-400">{{ item.label }}</dt>
            <dd class="font-medium text-slate-800 text-right">{{ item.value }}</dd>
          </div>
        </dl>
      </section>

      <section class="rounded-2xl border border-slate-100 p-5">
        <h4 class="text-[15px] font-semibold mb-4">Emergency Contact</h4>
        <dl class="space-y-3 text-sm">
          <div class="flex justify-between gap-3"><dt class="text-slate-400">Contact Name</dt><dd class="font-medium">{{ profile.emergency?.name || '—' }}</dd></div>
          <div class="flex justify-between gap-3"><dt class="text-slate-400">Relationship</dt><dd class="font-medium">{{ profile.emergency?.relationship || '—' }}</dd></div>
          <div class="flex justify-between gap-3"><dt class="text-slate-400">Phone</dt><dd class="font-medium">{{ profile.emergency?.phone || '—' }}</dd></div>
        </dl>
        <h4 class="text-[15px] font-semibold mt-6 mb-4">Identification</h4>
        <dl class="space-y-3 text-sm">
          <div class="flex justify-between gap-3"><dt class="text-slate-400">ID Type</dt><dd class="font-medium">{{ profile.identifier?.type || '—' }}</dd></div>
          <div class="flex justify-between gap-3"><dt class="text-slate-400">ID Number</dt><dd class="font-medium">{{ profile.identifier?.number || '—' }}</dd></div>
        </dl>
      </section>

      <section class="rounded-2xl border border-slate-100 p-5">
        <h4 class="text-[15px] font-semibold mb-4">Medical Information</h4>
        <div class="text-[12px] text-slate-400 mb-2">Allergies</div>
        <div v-if="profile.allergies?.length" class="flex flex-wrap gap-2 mb-4">
          <span v-for="item in profile.allergies" :key="item" class="text-xs px-2.5 py-1 rounded-full bg-rose-50 text-rose-600">{{ item }}</span>
        </div>
        <p v-else class="text-sm text-slate-400 mb-4">No allergies recorded.</p>
        <div class="text-[12px] text-slate-400 mb-2">Existing Conditions</div>
        <div v-if="profile.conditions?.length" class="flex flex-wrap gap-2 mb-4">
          <span v-for="item in profile.conditions" :key="item" class="text-xs px-2.5 py-1 rounded-full bg-amber-50 text-amber-700">{{ item }}</span>
        </div>
        <p v-else class="text-sm text-slate-400 mb-4">No active conditions.</p>
        <div class="text-[12px] text-slate-400 mb-2">Current Medications</div>
        <div v-if="profile.medications?.length" class="flex flex-wrap gap-2 mb-4">
          <span v-for="item in profile.medications" :key="item" class="text-xs px-2.5 py-1 rounded-full bg-blue-50 text-[#2f86f3]">{{ item }}</span>
        </div>
        <p v-else class="text-sm text-slate-400 mb-4">No current medications.</p>
        <div class="text-[12px] text-slate-400 mb-1">Notes</div>
        <p class="text-sm font-medium text-slate-800">{{ profile.notes || '—' }}</p>
      </section>
    </div>

    <div v-else-if="tab === 'history'">
      <div class="flex overflow-x-auto gap-2 mb-4">
        <button v-for="item in historyTabs" :key="item.id" type="button" class="h-9 px-3 rounded-xl text-sm" :class="historyTab === item.id ? 'bg-[#2f86f3] text-white' : 'bg-[#eef3fb] text-slate-600'" @click="historyTab = item.id">{{ item.label }}</button>
      </div>
      <div v-if="historyTab === 'visits'" class="overflow-x-auto">
        <p v-if="!profile.visits?.length" class="text-sm text-slate-400 py-8 text-center">No visits recorded for this patient yet.</p>
        <table v-else class="w-full text-sm min-w-[720px]">
          <thead><tr class="text-left text-slate-400"><th class="pb-3 font-medium">Date</th><th class="pb-3 font-medium">Visit type</th><th class="pb-3 font-medium">Doctor</th><th class="pb-3 font-medium">Department</th><th class="pb-3 font-medium">Diagnosis / Notes</th><th class="pb-3 font-medium text-right">Action</th></tr></thead>
          <tbody>
            <tr v-for="row in profile.visits" :key="row.id" class="border-t border-slate-100">
              <td class="py-3">{{ formatDate(row.date) }}</td>
              <td>{{ row.visit_type || '—' }}</td>
              <td>{{ row.doctor }}</td>
              <td>{{ row.department }}</td>
              <td>{{ row.diagnosis || '—' }}</td>
              <td class="text-right"><button type="button" class="text-[#2f86f3] text-sm" @click="visitId = row.id">View</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="overflow-x-auto">
        <p v-if="!historyRows.length" class="text-sm text-slate-400 py-8 text-center">{{ emptyHistory }}</p>
        <table v-else class="w-full text-sm min-w-[640px]">
          <thead><tr class="text-left text-slate-400"><th class="pb-3 font-medium">Date</th><th class="pb-3 font-medium">Record</th><th class="pb-3 font-medium">Detail</th><th class="pb-3 font-medium">Status</th></tr></thead>
          <tbody>
            <tr v-for="row in historyRows" :key="row.id" class="border-t border-slate-100">
              <td class="py-3">{{ formatDate(row.date) }}</td>
              <td>{{ row.title }}</td>
              <td>{{ row.detail }}</td>
              <td>{{ row.status || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-else-if="tab === 'appointments'">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex gap-2">
          <button v-for="item in appointmentTabs" :key="item" type="button" class="h-9 px-3 rounded-xl text-sm" :class="appointmentTab === item ? 'bg-[#2f86f3] text-white' : 'bg-[#eef3fb] text-slate-600'" @click="appointmentTab = item">{{ item[0].toUpperCase() + item.slice(1) }}</button>
        </div>
        <button type="button" class="px-4 h-10 rounded-xl bg-[#2f86f3] text-white text-sm" @click="startBook">+ Book Appointment</button>
      </div>
      <p v-if="notice" class="mb-3 text-sm text-slate-500">{{ notice }}</p>
      <p v-if="!filteredAppointments.length" class="text-sm text-slate-400 py-8 text-center">No {{ appointmentTab }} appointments for this patient.</p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm min-w-[860px]">
          <thead><tr class="text-left text-slate-400"><th class="pb-3 font-medium">Date</th><th class="pb-3 font-medium">Time</th><th class="pb-3 font-medium">Department</th><th class="pb-3 font-medium">Doctor</th><th class="pb-3 font-medium">Type</th><th class="pb-3 font-medium">Status</th><th class="pb-3 font-medium text-right">Actions</th></tr></thead>
          <tbody>
            <tr v-for="row in filteredAppointments" :key="row.id" class="border-t border-slate-100">
              <td class="py-3">{{ formatDate(row.appointment_date) }}</td>
              <td>{{ row.start_time || '—' }}</td>
              <td>{{ row.department }}</td>
              <td>{{ row.doctor }}</td>
              <td>{{ row.appointment_type }}</td>
              <td>{{ row.status }}</td>
              <td class="text-right whitespace-nowrap">
                <button type="button" class="text-[#2f86f3] mr-3" @click="viewAppointment(row)">View</button>
                <button v-if="canChange(row)" type="button" class="text-slate-500 mr-3" @click="startReschedule(row)">Reschedule</button>
                <button v-if="canChange(row)" type="button" class="text-red-500" @click="askCancel = row">Cancel</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-else-if="tab === 'documents'">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex overflow-x-auto gap-2">
          <button v-for="item in documentTabs" :key="item.id" type="button" class="h-9 px-3 rounded-xl text-sm" :class="documentTab === item.id ? 'bg-[#2f86f3] text-white' : 'bg-[#eef3fb] text-slate-600'" @click="documentTab = item.id">{{ item.label }}</button>
        </div>
        <label class="px-4 h-10 rounded-xl bg-[#2f86f3] text-white text-sm inline-flex items-center cursor-pointer">
          + Upload Document
          <input type="file" class="hidden" @change="uploadDocument">
        </label>
      </div>
      <p v-if="docError" class="mb-3 text-sm text-red-500">{{ docError }}</p>
      <p v-if="!filteredDocuments.length" class="text-sm text-slate-400 py-8 text-center">No documents linked to this patient.</p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
          <thead><tr class="text-left text-slate-400"><th class="pb-3 font-medium">Date</th><th class="pb-3 font-medium">Type</th><th class="pb-3 font-medium">Title</th><th class="pb-3 font-medium">Doctor / Department</th><th class="pb-3 font-medium text-right">Actions</th></tr></thead>
          <tbody>
            <tr v-for="row in filteredDocuments" :key="row.id" class="border-t border-slate-100">
              <td class="py-3">{{ formatDate(row.created_at) }}</td>
              <td>{{ row.document_type }}</td>
              <td>{{ row.title }}</td>
              <td>{{ row.doctor }} / {{ row.department }}</td>
              <td class="text-right whitespace-nowrap">
                <button type="button" class="text-[#2f86f3] mr-3" @click="openDocument(row, false)">View</button>
                <button type="button" class="text-slate-500" @click="openDocument(row, true)">Download</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-else-if="tab === 'billing'">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
        <div class="rounded-2xl border border-slate-100 px-4 py-3"><div class="text-xl font-semibold">₹{{ money(profile.billing?.total_billed) }}</div><div class="text-xs text-slate-400 mt-1">Total Billed</div></div>
        <div class="rounded-2xl border border-slate-100 px-4 py-3"><div class="text-xl font-semibold">₹{{ money(profile.billing?.total_paid) }}</div><div class="text-xs text-slate-400 mt-1">Total Paid</div></div>
        <div class="rounded-2xl border border-slate-100 px-4 py-3"><div class="text-xl font-semibold">₹{{ money(profile.billing?.outstanding) }}</div><div class="text-xs text-slate-400 mt-1">Outstanding</div></div>
      </div>
      <p v-if="!profile.invoices?.length" class="text-sm text-slate-400 py-8 text-center">No billing records for this patient. Invoices created in Billing will appear here.</p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
          <thead><tr class="text-left text-slate-400"><th class="pb-3 font-medium">Invoice</th><th class="pb-3 font-medium">Date</th><th class="pb-3 font-medium">Service</th><th class="pb-3 font-medium">Amount</th><th class="pb-3 font-medium">Status</th><th class="pb-3 font-medium text-right">Actions</th></tr></thead>
          <tbody>
            <tr v-for="row in profile.invoices" :key="row.id" class="border-t border-slate-100">
              <td class="py-3">{{ row.invoice_number }}</td>
              <td>{{ formatDate(row.date) }}</td>
              <td>{{ row.service }}</td>
              <td>₹{{ money(row.amount) }}</td>
              <td>{{ row.status }}</td>
              <td class="text-right whitespace-nowrap">
                <button type="button" class="text-[#2f86f3] mr-3" @click="openBill(row)">View Bill</button>
                <button type="button" class="text-slate-500 mr-3" @click="printBill(row)">Print Bill</button>
                <button type="button" class="text-slate-500" @click="openReceipt(row)">View Receipt</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="askCancel" class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      <div class="font-medium mb-1">Cancel appointment {{ askCancel.appointment_number }}?</div>
      <p class="mb-2">The appointment stays in history as Cancelled.</p>
      <input v-model="cancelReason" type="text" placeholder="Cancellation reason *" class="w-full h-10 rounded-xl px-3 bg-white outline-none mb-2">
      <p v-if="cancelError" class="text-red-500 mb-2">{{ cancelError }}</p>
      <div class="flex gap-2">
        <button type="button" class="px-3 h-9 rounded-xl bg-amber-600 text-white text-sm" :disabled="savingCancel" @click="confirmCancel">{{ savingCancel ? 'Saving…' : 'Confirm cancel' }}</button>
        <button type="button" class="px-3 h-9 rounded-xl border border-amber-200 text-sm" @click="askCancel = null; cancelReason = ''">Back</button>
      </div>
    </div>

    <div v-if="viewingDoc" class="mt-4 rounded-2xl border border-slate-100 p-5">
      <div class="flex items-center justify-between mb-3">
        <h4 class="font-semibold">{{ viewingDoc.title }}</h4>
        <button type="button" class="text-sm text-[#2f86f3]" @click="viewingDoc = null">Close</button>
      </div>
      <iframe v-if="canPreview(viewingDoc)" :src="documentUrl(viewingDoc, false)" class="w-full h-[480px] rounded-xl bg-[#eef3fb]"></iframe>
      <p v-else class="text-sm text-slate-500">This file type opens as a download.</p>
    </div>

    <div v-if="bill" class="mt-4 rounded-2xl border border-slate-100 p-5 text-sm">
      <div class="flex items-center justify-between mb-3">
        <h4 class="font-semibold">{{ billKind === 'receipt' ? 'Receipt' : 'Invoice' }} {{ bill.invoice_number }}</h4>
        <button type="button" class="text-sm text-[#2f86f3]" @click="bill = null">Close</button>
      </div>
      <p class="text-slate-500 mb-3">{{ bill.patient?.name }} · {{ bill.patient?.mrn }} · {{ formatDate(bill.date) }} · {{ bill.status }}</p>
      <div v-if="billKind !== 'receipt'" class="overflow-x-auto">
        <table class="w-full">
          <thead><tr class="text-left text-slate-400"><th class="pb-2 font-medium">Service</th><th class="pb-2 font-medium">Amount</th><th class="pb-2 font-medium">Discount</th><th class="pb-2 font-medium">Total</th></tr></thead>
          <tbody>
            <tr v-for="item in bill.items" :key="item.id" class="border-t border-slate-100">
              <td class="py-2">{{ item.description }}</td>
              <td>₹{{ money(item.unit_price) }}</td>
              <td>₹{{ money(item.discount) }}</td>
              <td>₹{{ money(item.total) }}</td>
            </tr>
          </tbody>
        </table>
        <div class="mt-3 text-right">
          <div>Subtotal ₹{{ money(bill.subtotal) }}</div>
          <div>Discount ₹{{ money(bill.discount) }}</div>
          <div>Tax ₹{{ money(bill.tax) }}</div>
          <div class="font-semibold">Total ₹{{ money(bill.total) }}</div>
          <div>Paid ₹{{ money(bill.amount_paid) }}</div>
          <div>Balance ₹{{ money(bill.balance) }}</div>
        </div>
      </div>
      <h5 class="font-medium mt-4 mb-2">Payments</h5>
      <p v-if="!bill.payments?.length" class="text-slate-400">No payments recorded.</p>
      <div v-for="pay in bill.payments" :key="pay.id" class="border-t border-slate-100 py-2">
        {{ formatDate(pay.paid_at) }} · {{ pay.payment_method }} · ₹{{ money(pay.amount) }} · {{ pay.status }}
        <span v-if="pay.transaction_reference"> · {{ pay.transaction_reference }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '../api'
import { filterAppointments, filterDocuments, patientInitials } from '../patients/profileWorkspace.js'
import { validateCancelReason } from '../patients/appointmentForm.js'
import VisitDetail from './VisitDetail.vue'
import AppointmentBookForm from './AppointmentBookForm.vue'

const props = defineProps({
  patient: { type: Object, required: true },
})

const emit = defineEmits(['back', 'edit', 'updated'])

const loading = ref(false)
const error = ref('')
const profile = ref({ ...props.patient, summary: {}, allergies: [], conditions: [], medications: [], visits: [], appointments: [], documents: [], invoices: [], billing: {} })
const tab = ref('overview')
const historyTab = ref('visits')
const appointmentTab = ref('upcoming')
const documentTab = ref('all')
const menuOpen = ref(false)
const askDeactivate = ref(false)
const savingStatus = ref(false)
const notice = ref('')
const docError = ref('')
const visitId = ref(null)
const booking = ref(false)
const reschedule = ref(null)
const askCancel = ref(null)
const cancelReason = ref('')
const cancelError = ref('')
const savingCancel = ref(false)
const viewingDoc = ref(null)
const bill = ref(null)
const billKind = ref('bill')

const tabs = [
  { id: 'overview', label: 'Overview' },
  { id: 'history', label: 'Medical History' },
  { id: 'appointments', label: 'Appointments' },
  { id: 'documents', label: 'Documents' },
  { id: 'billing', label: 'Billing' },
]
const historyTabs = [
  { id: 'visits', label: 'All Visits' },
  { id: 'diagnoses', label: 'Diagnoses' },
  { id: 'prescriptions', label: 'Prescriptions' },
  { id: 'lab', label: 'Lab Reports' },
  { id: 'radiology', label: 'Radiology' },
  { id: 'notes', label: 'Clinical Notes' },
]
const appointmentTabs = ['upcoming', 'completed', 'cancelled']
const documentTabs = [
  { id: 'all', label: 'All' },
  { id: 'prescriptions', label: 'Prescriptions' },
  { id: 'lab', label: 'Lab Reports' },
  { id: 'radiology', label: 'Radiology' },
  { id: 'discharge', label: 'Discharge Summaries' },
  { id: 'uploaded', label: 'Uploaded Documents' },
]

const initials = computed(() => patientInitials(profile.value.name))
const summary = computed(() => profile.value.summary || { visits: 0, conditions: 0, allergies: 0, upcoming_appointments: 0 })
const filteredAppointments = computed(() => filterAppointments(profile.value.appointments || [], appointmentTab.value))
const filteredDocuments = computed(() => filterDocuments(profile.value.documents || [], documentTab.value))

const personalFields = computed(() => [
  { label: 'Full Name', value: profile.value.name || '—' },
  { label: 'Date of Birth', value: formatDate(profile.value.date_of_birth) },
  { label: 'Age', value: profile.value.age ?? '—' },
  { label: 'Gender', value: profile.value.gender || '—' },
  { label: 'Blood Group', value: profile.value.blood_group || '—' },
  { label: 'Marital Status', value: profile.value.marital_status || '—' },
  { label: 'Nationality', value: profile.value.nationality || '—' },
])

const historyRows = computed(() => {
  if (historyTab.value === 'diagnoses') return (profile.value.diagnoses || []).map((row) => ({ id: row.id, date: row.date, title: row.name, detail: row.notes || '—', status: row.status }))
  if (historyTab.value === 'prescriptions') return (profile.value.prescriptions || []).map((row) => ({ id: row.id, date: row.date, title: row.number, detail: row.doctor, status: row.status }))
  if (historyTab.value === 'lab') return (profile.value.lab_reports || []).map((row) => ({ id: row.id, date: row.date, title: row.number, detail: row.priority, status: row.status }))
  if (historyTab.value === 'radiology') return (profile.value.radiology || []).map((row) => ({ id: row.id, date: row.date, title: row.number, detail: row.priority, status: row.status }))
  if (historyTab.value === 'notes') return (profile.value.clinical_notes || []).map((row) => ({ id: row.id, date: row.date, title: row.type, detail: row.content, status: '' }))
  return []
})

const emptyHistory = computed(() => {
  const labels = {
    diagnoses: 'No diagnoses recorded for this patient.',
    prescriptions: 'No prescriptions recorded for this patient.',
    lab: 'No lab reports for this patient.',
    radiology: 'No radiology reports for this patient.',
    notes: 'No clinical notes for this patient.',
  }
  return labels[historyTab.value] || 'No medical history yet.'
})

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

function money(value) {
  return Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function goTab(id) {
  tab.value = id
  menuOpen.value = false
  visitId.value = null
  booking.value = false
  reschedule.value = null
}

function closeVisit() {
  visitId.value = null
  tab.value = 'history'
  historyTab.value = 'visits'
}

function startBook() {
  menuOpen.value = false
  tab.value = 'appointments'
  visitId.value = null
  reschedule.value = null
  booking.value = true
}

function startReschedule(row) {
  reschedule.value = row
  booking.value = true
}

function closeBook() {
  booking.value = false
  reschedule.value = null
}

async function onAppointmentSaved() {
  booking.value = false
  reschedule.value = null
  tab.value = 'appointments'
  await load()
}

function canChange(row) {
  const status = (row.status || '').toLowerCase()
  return !['cancelled', 'canceled', 'completed'].includes(status)
}

function viewAppointment(row) {
  notice.value = `${row.appointment_number} · ${formatDate(row.appointment_date)} ${row.start_time || ''} · ${row.doctor} · ${row.status}`
}

async function confirmCancel() {
  cancelError.value = validateCancelReason(cancelReason.value)
  if (cancelError.value) return
  savingCancel.value = true
  try {
    await api(`/appointments/${askCancel.value.id}/cancel`, {
      method: 'POST',
      body: JSON.stringify({ reason: cancelReason.value.trim() }),
    })
    askCancel.value = null
    cancelReason.value = ''
    appointmentTab.value = 'cancelled'
    await load()
  } catch (e) {
    cancelError.value = e.message
  } finally {
    savingCancel.value = false
  }
}

function documentUrl(row, download) {
  return `/api/patients/${profile.value.id}/documents/${row.id}/file${download ? '?download=1' : ''}`
}

function canPreview(row) {
  const mime = (row.mime_type || '').toLowerCase()
  const title = (row.title || '').toLowerCase()
  return mime.includes('pdf') || mime.startsWith('image/') || title.endsWith('.pdf') || mime.startsWith('text/')
}

function openDocument(row, download) {
  if (download || !canPreview(row)) {
    window.open(documentUrl(row, true), '_blank')
    return
  }
  viewingDoc.value = row
}

async function openBill(row) {
  billKind.value = 'bill'
  const data = await api(`/patients/${profile.value.id}/invoices/${row.id}`)
  bill.value = data.data
}

function printBill(row) {
  window.open(`/api/patients/${profile.value.id}/invoices/${row.id}/print`, '_blank')
}

async function openReceipt(row) {
  try {
    billKind.value = 'receipt'
    const data = await api(`/patients/${profile.value.id}/invoices/${row.id}`)
    if (!data.data?.payments?.length) {
      notice.value = 'No payment receipt for this invoice.'
      bill.value = null
      return
    }
    bill.value = data.data
  } catch (e) {
    notice.value = e.message
  }
}

function printSummary() {
  menuOpen.value = false
  window.print()
}

async function load() {
  if (!props.patient?.id) return
  loading.value = true
  error.value = ''
  try {
    const data = await api(`/patients/${props.patient.id}`)
    profile.value = data.data
  } catch (e) {
    error.value = e.message || 'Could not load patient information.'
  } finally {
    loading.value = false
  }
}

async function deactivate() {
  savingStatus.value = true
  try {
    const data = await api(`/patients/${profile.value.id}`, {
      method: 'PUT',
      body: JSON.stringify({
        first_name: profile.value.first_name,
        last_name: profile.value.last_name,
        status: 'inactive',
        phone: profile.value.phone,
        email: profile.value.email,
        gender: profile.value.gender,
        date_of_birth: profile.value.date_of_birth,
      }),
    })
    profile.value = data.data
    askDeactivate.value = false
    emit('updated', data.data)
  } catch (e) {
    error.value = e.message
  } finally {
    savingStatus.value = false
  }
}

async function uploadDocument(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  docError.value = ''
  if (!file) return
  if (file.size > 5 * 1024 * 1024) {
    docError.value = 'File must be under 5 MB'
    return
  }
  const reader = new FileReader()
  reader.onload = async () => {
    const raw = String(reader.result || '')
    try {
      const data = await api(`/patients/${profile.value.id}/documents`, {
        method: 'POST',
        body: JSON.stringify({
          document_type: 'uploaded',
          file: {
            name: file.name,
            type: file.type || 'application/octet-stream',
            content: raw.includes(',') ? raw.split(',')[1] : raw,
          },
        }),
      })
      profile.value = data.data
      documentTab.value = 'uploaded'
    } catch (e) {
      docError.value = e.message
    }
  }
  reader.readAsDataURL(file)
}

watch(() => props.patient?.id, load)
onMounted(load)
</script>
