<template>
  <div class="w-64 bg-white border-r shadow-sm flex flex-col">
    <div class="px-6 py-6 border-b">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center">
          <span class="text-white font-bold text-2xl">H</span>
        </div>
        <div>
          <div class="font-bold text-xl text-gray-900">HCS</div>
          <div class="text-xs text-gray-500 -mt-1">Hospital Care System</div>
        </div>
      </div>
    </div>

    <nav class="px-3 py-4 flex-1">
      <div class="space-y-1">
        <a 
          v-for="item in menuItems" 
          :key="item.view"
          @click="$emit('change-view', item.view)"
          :class="currentView === item.view ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100'"
          class="flex items-center gap-3 px-4 py-3 rounded-xl cursor-pointer transition"
        >
          <span class="text-xl">{{ item.icon }}</span>
          <span>{{ item.label }}</span>
        </a>
      </div>
    </nav>
  </div>
</template>

<script setup>
defineProps({
  currentView: String
})

defineEmits(['change-view'])

const menuItems = [
  { view: 'dashboard', label: 'Dashboard', icon: '📊' },
  { view: 'patients', label: 'Patients', icon: '👥' },
  { view: 'appointments', label: 'Appointments', icon: '📅' },
  { view: 'admissions', label: 'Admissions', icon: '🏥' },
  { view: 'billing', label: 'Billing', icon: '💳' },
]
</script>