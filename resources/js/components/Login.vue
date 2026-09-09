<template>
  <div class="h-screen overflow-hidden font-sans bg-[#eef3fb] text-slate-800 flex items-center justify-center px-5">
    <form
      class="w-full max-w-[400px] bg-white rounded-[22px] px-8 py-9 shadow-[0_8px_30px_rgba(47,134,243,0.08)]"
      @submit.prevent="submit"
    >
      <div class="leading-tight mb-8">
        <div class="text-[22px] font-extrabold tracking-[0.18em] text-[#1a56db]">HCS</div>
        <div class="text-[10px] font-semibold tracking-[0.28em] -mt-0.5 text-[#64748b]">HOSPITAL ERP</div>
      </div>

      <label class="block text-[13px] font-medium text-slate-500 mb-1.5" for="login-username">Username</label>
      <input
        id="login-username"
        v-model="username"
        type="text"
        autocomplete="username"
        class="w-full h-11 rounded-xl px-4 text-sm outline-none bg-[#eef3fb] text-slate-800 mb-4"
        autofocus
      >

      <label class="block text-[13px] font-medium text-slate-500 mb-1.5" for="login-password">Password</label>
      <input
        id="login-password"
        v-model="password"
        type="password"
        autocomplete="current-password"
        class="w-full h-11 rounded-xl px-4 text-sm outline-none bg-[#eef3fb] text-slate-800 mb-5"
      >

      <p v-if="error" class="text-sm text-red-500 mb-4">{{ error }}</p>

      <button
        type="submit"
        class="w-full h-11 rounded-xl bg-[#2f86f3] text-white text-[15px] font-semibold hover:bg-[#2476dc] transition"
      >
        Enter
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { canLogin } from '../auth/credentials.js'

const emit = defineEmits(['success'])

const username = ref('')
const password = ref('')
const error = ref('')

function submit() {
  if (canLogin(username.value, password.value)) {
    error.value = ''
    emit('success')
    return
  }

  error.value = 'Invalid username or password'
}
</script>
