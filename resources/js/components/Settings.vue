<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <h2 class="text-xl font-semibold mb-6">Settings</h2>
    <div class="space-y-4 max-w-xl">
      <div>
        <label class="block text-sm text-slate-500 mb-1">Hospital Name</label>
        <input class="w-full border border-slate-200 rounded-xl px-4 py-2.5" value="HCS Hospital">
      </div>
      <div>
        <label class="block text-sm text-slate-500 mb-1">Timezone</label>
        <input class="w-full border border-slate-200 rounded-xl px-4 py-2.5" value="Asia/Kolkata">
      </div>
      <div>
        <label class="block text-sm text-slate-500 mb-1">Currency</label>
        <input class="w-full border border-slate-200 rounded-xl px-4 py-2.5" value="INR">
      </div>
      <button class="mt-2 px-5 py-2.5 bg-[#2f86f3] text-white rounded-xl text-sm">Save Settings</button>
    </div>
  </div>
</template>