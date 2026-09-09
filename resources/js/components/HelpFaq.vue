<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <h2 class="text-xl font-semibold mb-6">Help & FAQ</h2>
    <div class="space-y-4">
      <div v-for="item in faqs" :key="item.q" class="border border-slate-100 rounded-2xl p-4">
        <div class="font-medium text-slate-800">{{ item.q }}</div>
        <div class="text-sm text-slate-500 mt-1">{{ item.a }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
const faqs = [
  { q: 'How do I register a new OPD patient?', a: 'Open Patient → Add Patient, fill MRN and demographics. The same record is used across IPD, lab and billing.' },
  { q: 'Where do I book an appointment?', a: 'Use Appointments → Book Appointment. Choose doctor, date and slot. Status updates appear on Overview.' },
  { q: 'How is pharmacy stock linked to billing?', a: 'Dispensing deducts inventory and posts a charge to the patient invoice automatically.' },
  { q: 'Is NABH audit trail available?', a: 'Yes. Access logs and clinical documentation stay ready for NABH review from day one.' },
]
</script>