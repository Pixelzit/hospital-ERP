<template>
  <div class="bg-white rounded-[22px] p-6 shadow-[0_8px_30px_rgba(47,134,243,0.06)]">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold">Pharmacy</h2>
      <button class="px-4 py-2 bg-[#2f86f3] text-white rounded-xl text-sm">+ Add Medicine</button>
    </div>
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-slate-400">
          <th class="font-medium pb-3">Medicine</th>
          <th class="font-medium pb-3">Strength</th>
          <th class="font-medium pb-3">Stock</th>
          <th class="font-medium pb-3">Expiry</th>
          <th class="font-medium pb-3">Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.name" class="border-t border-slate-100">
          <td class="py-3 font-medium">{{ row.name }}</td>
          <td class="py-3 text-slate-500">{{ row.strength }}</td>
          <td class="py-3">{{ row.stock }}</td>
          <td class="py-3 text-slate-500">{{ row.expiry }}</td>
          <td class="py-3">
            <span class="text-xs font-medium px-2.5 py-1 rounded-full" :class="row.statusClass">{{ row.status }}</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
const rows = [
  { name: 'Paracetamol', strength: '500 mg', stock: '1,240', expiry: 'Mar 2027', status: 'In Stock', statusClass: 'bg-emerald-50 text-emerald-600' },
  { name: 'Amoxicillin', strength: '250 mg', stock: '86', expiry: 'Nov 2026', status: 'Low', statusClass: 'bg-amber-50 text-amber-600' },
  { name: 'Atorvastatin', strength: '10 mg', stock: '410', expiry: 'Jan 2027', status: 'In Stock', statusClass: 'bg-emerald-50 text-emerald-600' },
  { name: 'Insulin Glargine', strength: '100 IU/ml', stock: '12', expiry: 'Oct 2026', status: 'Critical', statusClass: 'bg-red-50 text-red-600' },
]
</script>
