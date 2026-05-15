<template>
  <div class="dashboard">
    <header class="page-header">
      <div>
        <h2>Benvenuto, {{ auth.user?.username }} 👋</h2>
        <p class="page-sub">{{ todayFormatted }}</p>
      </div>
    </header>

    <!-- KPI cards -->
    <section class="kpi-grid">
      <KpiCard
        v-for="card in kpiCards"
        :key="card.label"
        :icon="card.icon"
        :label="card.label"
        :value="card.value"
        :sub="card.sub"
        :color="card.color"
        :loading="loading"
      />
    </section>

    <!-- Sezioni per admin -->
    <template v-if="auth.isAdmin">
      <div class="two-col">
        <!-- Ultime fatture -->
        <section class="card">
          <div class="card-header">
            <h3>🧾 Ultime fatture</h3>
            <RouterLink to="/fatture" class="card-link">Vedi tutte →</RouterLink>
          </div>
          <div v-if="loading" class="skeleton-list">
            <div v-for="i in 4" :key="i" class="skeleton-row" />
          </div>
          <div v-else-if="!recentInvoices.length" class="empty-state">Nessuna fattura ancora.</div>
          <table v-else class="mini-table">
            <thead>
              <tr><th>N°</th><th>Cliente</th><th>Data</th><th>Totale</th><th>Stato</th></tr>
            </thead>
            <tbody>
              <tr v-for="inv in recentInvoices" :key="inv.id">
                <td class="mono">{{ inv.invoice_number }}</td>
                <td>{{ inv.company_name }}</td>
                <td>{{ formatDate(inv.invoice_date) }}</td>
                <td class="mono amount">€ {{ formatAmount(inv.total) }}</td>
                <td><span :class="['badge', inv.status]">{{ statusLabel(inv.status) }}</span></td>
              </tr>
            </tbody>
          </table>
        </section>

        <!-- Ore questo mese -->
        <section class="card">
          <div class="card-header">
            <h3>⏱️ Ore questo mese</h3>
            <RouterLink to="/ore-mie" class="card-link">Gestisci →</RouterLink>
          </div>
          <div v-if="loading" class="skeleton-list">
            <div v-for="i in 3" :key="i" class="skeleton-row" />
          </div>
          <div v-else-if="!myHoursThisMonth.length" class="empty-state">Nessuna ora registrata.</div>
          <table v-else class="mini-table">
            <thead>
              <tr><th>Cliente</th><th>Ore</th><th>Tariffa</th></tr>
            </thead>
            <tbody>
              <tr v-for="h in myHoursThisMonth" :key="h.client_id">
                <td>{{ h.company_name }}</td>
                <td class="mono">{{ h.total_hours }}h</td>
                <td class="mono">€ {{ formatAmount(h.hourly_rate) }}/h</td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>
    </template>

    <!-- Vista collaboratore -->
    <template v-else>
      <section class="card">
        <div class="card-header">
          <h3>🕐 Le mie ore questo mese</h3>
          <RouterLink to="/riepilogo" class="card-link">Riepilogo →</RouterLink>
        </div>
        <div v-if="loading" class="skeleton-list">
          <div v-for="i in 4" :key="i" class="skeleton-row" />
        </div>
        <div v-else-if="!collabHours.length" class="empty-state">Nessuna ora registrata questo mese.</div>
        <table v-else class="mini-table">
          <thead>
            <tr><th>Data</th><th>Ore</th><th>Tariffa</th><th>Importo</th></tr>
          </thead>
          <tbody>
            <tr v-for="h in collabHours" :key="h.id">
              <td>{{ formatDate(h.work_date) }}</td>
              <td class="mono">{{ h.hours }}h</td>
              <td>{{ h.tariff_name }}</td>
              <td class="mono amount">€ {{ formatAmount(h.hours * h.hourly_rate) }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import api from '../services/api.js';
import KpiCard from '../components/KpiCard.vue';

const auth    = useAuthStore();
const loading = ref(true);

// Data
const invoiceSummary    = ref([]);
const recentInvoices    = ref([]);
const myHoursThisMonth  = ref([]);
const collabHours       = ref([]);
const collaboratorsCount = ref(0);
const clientsCount       = ref(0);

const now = new Date();
const year  = now.getFullYear();
const month = now.getMonth() + 1;

const todayFormatted = computed(() =>
  now.toLocaleDateString('it-IT', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
);

const currentMonthSummary = computed(() =>
  invoiceSummary.value.find(s => s.year == year && s.month == month) ?? { count: 0, total_invoiced: 0 }
);

const kpiCards = computed(() => {
  if (auth.isAdmin) {
    return [
      {
        icon: '€',
        label: 'Fatturato questo mese',
        value: `€ ${formatAmount(currentMonthSummary.value.total_invoiced)}`,
        sub: `${currentMonthSummary.value.count} fatture`,
        color: 'green',
      },
      {
        icon: '👥',
        label: 'Collaboratori attivi',
        value: collaboratorsCount.value,
        sub: 'in organico',
        color: 'blue',
      },
      {
        icon: '🏢',
        label: 'Clienti attivi',
        value: clientsCount.value,
        sub: 'nel portafoglio',
        color: 'purple',
      },
      {
        icon: '⏱️',
        label: 'Ore lavorate (mese)',
        value: `${totalMyHours.value}h`,
        sub: 'ore fatturabili',
        color: 'orange',
      },
    ];
  }
  return [
    {
      icon: '⏱️',
      label: 'Ore lavorate (mese)',
      value: `${totalCollabHours.value}h`,
      sub: 'questo mese',
      color: 'blue',
    },
    {
      icon: '€',
      label: 'Da fatturare (mese)',
      value: `€ ${formatAmount(totalCollabAmount.value)}`,
      sub: 'lordo imposte',
      color: 'green',
    },
  ];
});

const totalMyHours = computed(() =>
  myHoursThisMonth.value.reduce((s, h) => s + parseFloat(h.total_hours), 0)
);

const totalCollabHours = computed(() =>
  collabHours.value.reduce((s, h) => s + parseFloat(h.hours), 0)
);

const totalCollabAmount = computed(() =>
  collabHours.value.reduce((s, h) => s + parseFloat(h.hours) * parseFloat(h.hourly_rate), 0)
);

// Helpers
function formatAmount(v) {
  return Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function statusLabel(s) {
  return { draft: 'Bozza', issued: 'Emessa', paid: 'Pagata' }[s] ?? s;
}

// Fetch
async function loadAdmin() {
  const [summary, invoices, collaborators, clients, hours] = await Promise.allSettled([
    api.get('/invoices/summary/monthly'),
    api.get(`/invoices?year=${year}&month=${month}`),
    api.get('/collaborators'),
    api.get('/clients'),
    api.get('/hours/my'),
  ]);

  if (summary.status === 'fulfilled')       invoiceSummary.value    = summary.value.data;
  if (invoices.status === 'fulfilled')      recentInvoices.value    = invoices.value.data.slice(0, 5);
  if (collaborators.status === 'fulfilled') collaboratorsCount.value = collaborators.value.data.filter(c => c.is_active).length;
  if (clients.status === 'fulfilled')       clientsCount.value       = clients.value.data.filter(c => c.is_active).length;

  if (hours.status === 'fulfilled') {
    // Raggruppa per cliente solo il mese corrente
    const thisMonth = hours.value.data.filter(h => {
      const d = new Date(h.work_date);
      return d.getFullYear() === year && d.getMonth() + 1 === month;
    });
    const map = {};
    for (const h of thisMonth) {
      if (!map[h.client_id]) map[h.client_id] = { ...h, total_hours: 0 };
      map[h.client_id].total_hours += parseFloat(h.hours);
    }
    myHoursThisMonth.value = Object.values(map);
  }
}

async function loadCollaborator() {
  const { data } = await api.get('/hours/collaborators');
  collabHours.value = data.filter(h => {
    const d = new Date(h.work_date);
    return d.getFullYear() === year && d.getMonth() + 1 === month;
  });
}

onMounted(async () => {
  try {
    if (auth.isAdmin) await loadAdmin();
    else              await loadCollaborator();
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.dashboard {
  padding: 2rem;
  max-width: 1200px;
}

/* ── Header ────────────────────────────────────────────── */
.page-header {
  margin-bottom: 1.75rem;
}

.page-header h2 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
}

.page-sub {
  font-size: 0.875rem;
  color: #6b7280;
  margin-top: 0.25rem;
  text-transform: capitalize;
}

/* ── KPI grid ──────────────────────────────────────────── */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1rem;
  margin-bottom: 1.75rem;
}

/* ── Two col ───────────────────────────────────────────── */
.two-col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 900px) {
  .two-col { grid-template-columns: 1fr; }
}

/* ── Card ──────────────────────────────────────────────── */
.card {
  background: #fff;
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  margin-bottom: 1rem;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}

.card-header h3 {
  font-size: 0.95rem;
  font-weight: 700;
  color: #111827;
}

.card-link {
  font-size: 0.8rem;
  color: #0f3460;
  font-weight: 600;
  text-decoration: none;
}

.card-link:hover { text-decoration: underline; }

/* ── Table ─────────────────────────────────────────────── */
.mini-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

.mini-table th {
  text-align: left;
  padding: 0.375rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: 1px solid #e5e7eb;
}

.mini-table td {
  padding: 0.5rem;
  border-bottom: 1px solid #f3f4f6;
  color: #374151;
}

.mini-table tr:last-child td { border-bottom: none; }
.mono   { font-family: 'Courier New', monospace; }
.amount { font-weight: 600; color: #059669; }

/* ── Badge ─────────────────────────────────────────────── */
.badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.7rem;
  font-weight: 600;
}

.badge.draft   { background: #f3f4f6; color: #6b7280; }
.badge.issued  { background: #dbeafe; color: #1d4ed8; }
.badge.paid    { background: #d1fae5; color: #065f46; }

/* ── Skeleton ──────────────────────────────────────────── */
.skeleton-list { display: flex; flex-direction: column; gap: 0.5rem; }
.skeleton-row  {
  height: 2rem;
  border-radius: 6px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}
@keyframes shimmer { to { background-position: -200% 0; } }

/* ── Empty ─────────────────────────────────────────────── */
.empty-state {
  text-align: center;
  padding: 2rem;
  color: #9ca3af;
  font-size: 0.875rem;
}

@media (max-width: 768px) {
  .dashboard { padding: 1rem; }
}
</style>
