<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    class="w-5 h-5"
  >
    <template v-if="name === 'overview'">
      <rect x="3" y="3" width="7" height="7" rx="1.5" />
      <rect x="14" y="3" width="7" height="7" rx="1.5" />
      <rect x="3" y="14" width="7" height="7" rx="1.5" />
      <rect x="14" y="14" width="7" height="7" rx="1.5" />
    </template>
    <template v-else-if="name === 'income'">
      <path d="M4 19V5" />
      <path d="M4 19h16" />
      <path d="M8 14l3.5-3.5 2.5 2.5L20 7" />
    </template>
    <template v-else-if="name === 'doctors'">
      <path d="M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" />
      <path d="M15.5 7h4M17.5 5v4" />
      <path d="M3 20c.6-3.2 3.2-5 6-5s5.4 1.8 6 5" />
    </template>
    <template v-else-if="name === 'appointments'">
      <rect x="5" y="4" width="14" height="16" rx="2" />
      <path d="M9 2v4M15 2v4M5 10h14" />
      <path d="M9 14h6M9 17h4" />
    </template>
    <template v-else-if="name === 'patient'">
      <circle cx="9" cy="8" r="3" />
      <circle cx="16" cy="9" r="2.2" />
      <path d="M3.5 20c.7-3.2 3.2-5 5.5-5s4.8 1.8 5.5 5" />
      <path d="M13.5 19.5c.4-2 2-3.4 3.5-3.4 1.4 0 2.8 1 3.3 2.8" />
    </template>
    <template v-else-if="name === 'pharmacy'">
      <rect x="4" y="4" width="16" height="16" rx="3" />
      <path d="M12 8v8M8 12h8" />
    </template>
    <template v-else-if="name === 'settings'">
      <circle cx="12" cy="12" r="3" />
      <path d="M12 3v2M12 19v2M4.9 6.5l1.4 1.4M17.7 16.1l1.4 1.4M3 12h2M19 12h2M4.9 17.5l1.4-1.4M17.7 7.9l1.4-1.4" />
    </template>
    <template v-else-if="name === 'help'">
      <circle cx="12" cy="12" r="9" />
      <path d="M9.5 9a2.5 2.5 0 1 1 3.6 2.2c-.8.4-1.1.9-1.1 1.8" />
      <path d="M12 17h.01" />
    </template>
    <template v-else-if="name === 'logout'">
      <path d="M10 17l-5-5 5-5" />
      <path d="M5 12h11" />
      <path d="M16 5h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-2" />
    </template>
    <template v-else-if="name === 'dark'">
      <circle cx="12" cy="12" r="7" />
      <path d="M12 8v8M8 12h8" />
      <path d="M12 5V3M12 21v-2M5 12H3M21 12h-2" />
    </template>
    <template v-else-if="name === 'chat'">
      <path d="M5 16.5V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H9l-4 3.5z" />
    </template>
    <template v-else-if="name === 'bell'">
      <path d="M6 9a6 6 0 1 1 12 0c0 4 1.5 6 1.5 6H4.5S6 13 6 9z" />
      <path d="M10 19a2 2 0 0 0 4 0" />
    </template>
    <template v-else-if="name === 'search'">
      <circle cx="11" cy="11" r="6" />
      <path d="M16 16l4 4" />
    </template>
  </svg>
</template>

<script setup>
defineProps({
  name: { type: String, required: true },
})
</script>
