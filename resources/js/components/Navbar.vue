<template>
  <div class="h-16 bg-white border-b flex items-center justify-between px-6">
    <div class="text-xl font-semibold text-gray-800">Hospital Management System</div>
    
    <div class="flex items-center gap-4">
      <div class="relative w-72">
        <input 
          type="text" 
          placeholder="Search patients..." 
          class="w-full pl-10 pr-4 py-2 bg-gray-100 rounded-xl text-sm focus:outline-none focus:bg-white border"
        >
        <div class="absolute left-4 top-2.5 text-gray-400">🔍</div>
      </div>
      
      <div class="flex items-center gap-3">
        <div class="text-right text-sm">
          <div class="font-medium">Dr. Rahul Sharma</div>
        </div>
        <div class="w-9 h-9 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">RS</div>
      </div>
    </div>
  </div>
</template>