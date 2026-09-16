<template>
  <Login v-if="!loggedIn" @success="onLogin" />
  <div v-else class="h-screen overflow-hidden font-sans" :class="darkMode ? 'bg-[#0f172a] text-slate-100' : 'bg-[#eef3fb] text-slate-800'">
    <!-- Top bar -->
    <header class="h-[72px] px-6 flex items-center gap-6 shrink-0">
      <div class="w-[220px] shrink-0 flex items-center">
        <div class="leading-tight">
          <div class="text-[22px] font-extrabold tracking-[0.18em]" :class="darkMode ? 'text-white' : 'text-[#1a56db]'">HCS</div>
          <div class="text-[10px] font-semibold tracking-[0.28em] -mt-0.5" :class="darkMode ? 'text-slate-400' : 'text-[#64748b]'">HOSPITAL ERP</div>
        </div>
      </div>

      <div class="flex-1 flex justify-center">
        <div class="relative w-full max-w-md">
          <input
            v-model="search"
            type="text"
            placeholder="Search"
            class="w-full h-11 rounded-full pl-5 pr-12 text-sm outline-none"
            :class="darkMode ? 'bg-[#1e293b] text-white placeholder:text-slate-400' : 'bg-white text-slate-700 placeholder:text-slate-400 shadow-sm'"
          >
          <span class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400">
            <NavIcon name="search" />
          </span>
        </div>
      </div>

      <div class="flex items-center gap-4 shrink-0">
        <button class="w-10 h-10 rounded-full flex items-center justify-center" :class="darkMode ? 'bg-[#1e293b]' : 'bg-white shadow-sm'">
          <NavIcon name="chat" />
        </button>
        <button class="relative w-10 h-10 rounded-full flex items-center justify-center" :class="darkMode ? 'bg-[#1e293b]' : 'bg-white shadow-sm'">
          <NavIcon name="bell" />
          <span class="absolute top-2 right-2 w-2 h-2 rounded-full bg-red-500"></span>
        </button>
        <div class="flex items-center gap-3 pl-1">
          <img
            src="https://i.pravatar.cc/80?img=12"
            alt="Dr. Sachin J"
            class="w-10 h-10 rounded-full object-cover"
          >
          <div class="leading-tight pr-1">
            <div class="text-sm font-semibold">{{ currentUser?.name || 'HCS User' }}</div>
            <div class="text-xs text-slate-400">{{ currentUser?.username || 'User' }}</div>
          </div>
        </div>
      </div>
    </header>

    <div class="flex gap-5 px-5 pb-5" style="height: calc(100vh - 72px)">
      <!-- Blue floating sidebar -->
      <aside class="w-[220px] shrink-0 rounded-[28px] flex flex-col py-5 px-3 text-white overflow-y-auto" style="background: #2f86f3">
        <nav class="flex-1 space-y-1">
          <button
            v-for="item in menu"
            :key="item.key"
            type="button"
            @click="currentView = item.key"
            class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-[15px] text-left transition"
            :class="currentView === item.key ? 'bg-[#dbebff] text-[#2f86f3] font-semibold' : 'text-white/95 hover:bg-white/10'"
          >
            <NavIcon :name="item.icon" />
            <span>{{ item.label }}</span>
          </button>
        </nav>

        <div class="pt-4 space-y-1">
          <button type="button" @click="logOut" class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-[15px] text-white/95 hover:bg-white/10">
            <NavIcon name="logout" />
            <span>Log Out</span>
          </button>
          <button type="button" @click="darkMode = !darkMode" class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-[15px] text-white/95 hover:bg-white/10">
            <NavIcon name="dark" />
            <span>Dark Mode</span>
          </button>
        </div>
      </aside>

      <!-- Main -->
      <main class="flex-1 min-w-0 overflow-y-auto pr-1">
        <component :is="currentComponent" @navigate="currentView = $event" />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, h, defineComponent } from 'vue'
import { api } from './api'
import NavIcon from './components/NavIcon.vue'
import Login from './components/Login.vue'
import Dashboard from './components/Dashboard.vue'
import Income from './components/Income.vue'
import Doctors from './components/Doctors.vue'
import Appointments from './components/Appointments.vue'
import Patient from './components/Patient.vue'
import Pharmacy from './components/Pharmacy.vue'
import PharmacyPos from './components/PharmacyPos.vue'
import OpdDashboard from './components/OpdDashboard.vue'
import IPDManagement from './components/IPDManagement.vue'
import NursingStation from './components/NursingStation.vue'
import Settings from './components/Settings.vue'
import HelpFaq from './components/HelpFaq.vue'
import ModulePlaceholder from './components/ModulePlaceholder.vue'
import Emergency from './components/Emergency.vue'
import Laboratory from './components/Laboratory.vue'
import Radiology from './components/Radiology.vue'
import Inventory from './components/Inventory.vue'
import Reports from './components/Reports.vue'
import Billing from './components/Billing.vue'

function placeholderPage(title, message) {
  return defineComponent({
    name: 'Placeholder_' + title.replace(/[^a-zA-Z0-9]/g, ''),
    setup() {
      return () => h(ModulePlaceholder, { title, message })
    },
  })
}

const loggedIn = ref(false)
const currentUser = ref(null)
const currentView = ref('overview')
const darkMode = ref(false)
const search = ref('')

function onLogin(user) {
  currentUser.value = user || null
  loggedIn.value = true
}

async function logOut() {
  try {
    await api('/logout', { method: 'POST' })
  } catch (e) {
    // session is cleared locally either way
  }
  loggedIn.value = false
  currentUser.value = null
  currentView.value = 'overview'
}

async function restoreSession() {
  try {
    const data = await api('/me')
    currentUser.value = data.data
    loggedIn.value = true
  } catch (e) {
    loggedIn.value = false
    currentUser.value = null
  }
}

onMounted(restoreSession)

const menu = [
  { key: 'overview', label: 'Overview', icon: 'overview' },
  { key: 'patient', label: 'Patients', icon: 'patient' },
  { key: 'appointments', label: 'Appointments', icon: 'appointments' },
  { key: 'opd', label: 'OPD', icon: 'patient' },
  { key: 'emergency', label: 'Emergency', icon: 'patient' },
  { key: 'ipd', label: 'IPD', icon: 'patient' },
  { key: 'nursing', label: 'Nursing', icon: 'patient' },
  { key: 'ot', label: 'OT / Surgery', icon: 'patient' },
  { key: 'procedure', label: 'Procedure Room', icon: 'patient' },
  { key: 'laboratory', label: 'Laboratory', icon: 'patient' },
  { key: 'radiology', label: 'Radiology', icon: 'patient' },
  { key: 'pharmacy', label: 'Pharmacy', icon: 'pharmacy' },
  { key: 'pharmacy-pos', label: 'Pharmacy POS', icon: 'pharmacy' },
  { key: 'inventory', label: 'Inventory', icon: 'pharmacy' },
  { key: 'blood-bank', label: 'Blood Bank', icon: 'patient' },
  { key: 'ambulance', label: 'Ambulance', icon: 'patient' },
  { key: 'linen', label: 'Linen', icon: 'settings' },
  { key: 'cssd', label: 'CSSD', icon: 'settings' },
  { key: 'income', label: 'Income', icon: 'income' },
  { key: 'insurance', label: 'Insurance / TPA', icon: 'income' },
  { key: 'discharge', label: 'Discharge', icon: 'patient' },
  { key: 'doctors', label: 'Doctors', icon: 'doctors' },
  { key: 'departments', label: 'Departments', icon: 'doctors' },
  { key: 'reports', label: 'Reports', icon: 'overview' },
  { key: 'mis', label: 'MIS', icon: 'overview' },
  { key: 'security', label: 'Security', icon: 'settings' },
  { key: 'templates', label: 'Templates', icon: 'settings' },
  { key: 'system', label: 'System', icon: 'settings' },
  { key: 'settings', label: 'Settings', icon: 'settings' },
  { key: 'help', label: 'Help & FAQ', icon: 'help' },
]

const pages = {
  overview: Dashboard,
  patient: Patient,
  appointments: Appointments,
  opd: OpdDashboard,
  emergency: Emergency,
  ipd: IPDManagement,
  nursing: NursingStation,
  ot: placeholderPage('OT / Surgery', 'OT scheduling and perioperative module coming soon.'),
  procedure: placeholderPage('Procedure Room', 'Procedure room booking and notes module coming soon.'),
  laboratory: Laboratory,
  radiology: Radiology,
  pharmacy: Pharmacy,
  'pharmacy-pos': PharmacyPos,
  inventory: Inventory,
  'blood-bank': placeholderPage('Blood Bank', 'Blood bank inventory and cross-match module coming soon.'),
  ambulance: placeholderPage('Ambulance', 'Ambulance dispatch and tracking module coming soon.'),
  linen: placeholderPage('Linen', 'Linen management module coming soon.'),
  cssd: placeholderPage('CSSD', 'Central sterile supply module coming soon.'),
  income: Income,
  insurance: Billing,
  discharge: placeholderPage('Discharge', 'Discharge summary and clearance module coming soon.'),
  doctors: Doctors,
  departments: placeholderPage('Departments', 'Department master and roster module coming soon.'),
  reports: Reports,
  mis: placeholderPage('MIS', 'Management information dashboards coming soon.'),
  security: placeholderPage('Security', 'Access control and audit module coming soon.'),
  templates: placeholderPage('Templates', 'Clinical and admin templates module coming soon.'),
  system: placeholderPage('System', 'System configuration module coming soon.'),
  settings: Settings,
  help: HelpFaq,
}

const currentComponent = computed(() => pages[currentView.value] || Dashboard)
</script>
