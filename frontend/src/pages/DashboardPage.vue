<template>
  <div class="dashboard">
    <header class="page-header">
      <div>
        <h2>Benvenuto, {{ auth.user?.username }} 👋</h2>
        <p class="page-sub">{{ todayFormatted }}</p>
      </div>
      <input
        v-if="auth.isReferent"
        v-model="selectedMonth"
        type="month"
        class="month-picker"
      />
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
            <RouterLink to="/invoices" class="card-link">Vedi tutte →</RouterLink>
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
            <RouterLink to="/my-hours" class="card-link">Gestisci →</RouterLink>
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

    <!-- Vista referente -->
    <template v-else-if="auth.isReferent">
      <section class="card">
        <div class="card-header">
          <h3>📉 Progetti sotto controllo</h3>
          <RouterLink to="/referent-overview" class="card-link">Dettaglio uscite →</RouterLink>
        </div>
        <div v-if="loading" class="skeleton-list">
          <div v-for="i in 4" :key="i" class="skeleton-row" />
        </div>
        <div v-else-if="!referentSummary.length" class="empty-state">Nessun progetto assegnato.</div>
        <table v-else class="mini-table">
          <thead>
            <tr><th>Progetto</th><th>Ore da fatturare</th><th>€ da fatturare</th><th>€ fatturate</th></tr>
          </thead>
          <tbody>
            <tr v-for="p in referentSummary" :key="p.id">
              <td>{{ p.name }}</td>
              <td class="mono">{{ formatAmount(p.to_invoice_hours) }}h</td>
              <td class="mono amount">€ {{ formatAmount(p.to_invoice_gross) }}</td>
              <td class="mono">€ {{ formatAmount(p.invoiced_gross) }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>

    <!-- Vista collaboratore -->
    <template v-else>
      <div class="two-col">
        <section class="card">
          <div class="card-header">
            <h3>🕐 Le mie ore questo mese</h3>
            <RouterLink to="/summary" class="card-link">Riepilogo →</RouterLink>
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

        <section class="card">
          <div class="card-header">
            <h3>📄 Le mie fatture proforma</h3>
            <RouterLink to="/my-invoices" class="card-link">Vedi tutte →</RouterLink>
          </div>
          <div v-if="loading" class="skeleton-list">
            <div v-for="i in 3" :key="i" class="skeleton-row" />
          </div>
          <div v-else-if="!collabInvoices.length" class="empty-state">Nessuna fattura ancora.</div>
          <table v-else class="mini-table">
            <thead>
              <tr><th>N°</th><th>Inviata il</th><th>Pagata il</th><th>Totale</th><th>Stato</th></tr>
            </thead>
            <tbody>
              <tr v-for="inv in collabInvoices" :key="inv.id">
                <td class="mono">{{ inv.invoice_number }}</td>
                <td>{{ formatDate(inv.invoice_date) }}</td>
                <td>{{ inv.paid_at ? formatDate(inv.paid_at) : '—' }}</td>
                <td class="mono amount">€ {{ formatAmount(inv.total) }}</td>
                <td><span :class="['badge', inv.status]">{{ collabStatusLabel(inv.status) }}</span></td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import api from '../services/api.js';
import KpiCard from '../components/KpiCard.vue';

const auth    = useAuthStore();
const loading = ref(true);

// Data
const invoiceSummary    = ref([]);
const recentInvoices    = ref([]);
const myHoursThisMonth  = ref([]);
const collabHours        = ref([]);
const collabInvoices     = ref([]);
const referentSummary    = ref([]);
const collaboratorsCount = ref(0);
const clientsCount       = ref(0);

const now = new Date();
const year  = now.getFullYear();
const month = now.getMonth() + 1;
const selectedMonth = ref(now.toISOString().slice(0, 7));

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
  if (auth.isReferent) {
    return [
      {
        icon: '📁',
        label: 'Progetti assegnati',
        value: referentSummary.value.length,
        sub: 'con monitoraggio costi',
        color: 'blue',
      },
      {
        icon: '✅',
        label: 'Ore fatturate (mese)',
        value: `${formatAmount(totalReferentInvoicedHours.value)}h`,
        sub: `€ ${formatAmount(totalReferentInvoicedGross.value)}`,
        color: 'green',
      },
      {
        icon: '⏳',
        label: 'Ore da fatturare',
        value: `${formatAmount(totalReferentToInvoiceHours.value)}h`,
        sub: 'incluse pending/approved',
        color: 'orange',
      },
      {
        icon: '€',
        label: 'Da fatturare (mese)',
        value: `€ ${formatAmount(totalReferentToInvoiceGross.value)}`,
        sub: 'uscite previste',
        color: 'purple',
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
    {
      icon: '📄',
      label: 'Fatture in attesa',
      value: sentInvoicesCount.value,
      sub: 'da incassare',
      color: 'orange',
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

const sentInvoicesCount = computed(() =>
  collabInvoices.value.filter(i => i.status === 'sent').length
);

const totalReferentInvoicedHours = computed(() =>
  referentSummary.value.reduce((s, p) => s + parseFloat(p.invoiced_hours || 0), 0)
);

const totalReferentToInvoiceHours = computed(() =>
  referentSummary.value.reduce((s, p) => s + parseFloat(p.to_invoice_hours || 0), 0)
);

const totalReferentInvoicedGross = computed(() =>
  referentSummary.value.reduce((s, p) => s + parseFloat(p.invoiced_gross || 0), 0)
);

const totalReferentToInvoiceGross = computed(() =>
  referentSummary.value.reduce((s, p) => s + parseFloat(p.to_invoice_gross || 0), 0)
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

function collabStatusLabel(s) {
  return { draft: 'Bozza', sent: 'Inviata', paid: 'Pagata' }[s] ?? s;
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
  const [hours, invoices] = await Promise.allSettled([
    api.get('/hours/collaborators'),
    api.get('/collab-invoices/mine'),
  ]);

  if (hours.status === 'fulfilled') {
    collabHours.value = hours.value.data.filter(h => {
      const d = new Date(h.work_date);
      return d.getFullYear() === year && d.getMonth() + 1 === month;
    });
  }

  if (invoices.status === 'fulfilled') {
    collabInvoices.value = invoices.value.data;
  }
}

async function loadReferent() {
  const { data } = await api.get(`/referent/projects/summary?month=${selectedMonth.value}`);
  referentSummary.value = data;
}

watch(selectedMonth, async () => {
  if (!auth.isReferent) return;
  loading.value = true;
  try {
    await loadReferent();
  } finally {
    loading.value = false;
  }
});

onMounted(async () => {
  try {
    if (auth.isAdmin) await loadAdmin();
    else if (auth.isReferent) await loadReferent();
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

.page-sub {
  text-transform: capitalize;
}

.month-picker {
  padding: 0.5rem 0.875rem;
  border: 1.5px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.9rem;
  outline: none;
  background: #fff;
  color: #374151;
}

.month-picker:focus {
  border-color: #0f3460;
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
.amount { font-weight: 600; color: #059669; }

/* ── Badge ─────────────────────────────────────────────── */
.badge.draft   { background: #f3f4f6; color: #6b7280; }
.badge.issued  { background: #dbeafe; color: #1d4ed8; }
.badge.sent    { background: #fef3c7; color: #92400e; }
.badge.paid    { background: #d1fae5; color: #065f46; }

/* ── Empty ─────────────────────────────────────────────── */
.empty-state {
  padding: 2rem;
  font-size: 0.875rem;
}

@media (max-width: 768px) {
  .dashboard { padding: 1rem; }
}
</style>
