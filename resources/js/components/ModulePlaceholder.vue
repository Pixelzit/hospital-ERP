<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <h2 class="text-xl font-semibold mb-3">{{ title }}</h2>
    <div class="rounded-2xl border border-slate-100 bg-[#f8fafc] px-6 py-10 text-center text-slate-500 text-sm">
      {{ message || 'This module is a placeholder and is not built yet.' }}
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  message: { type: String, default: '' },
})
</script>
