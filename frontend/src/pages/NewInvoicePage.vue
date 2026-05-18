<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>🧾 Nuova Fattura</h2>
        <p class="page-sub">Simula e crea una nuova fattura</p>
      </div>
      <RouterLink to="/invoices" class="btn-ghost">← Torna alle fatture</RouterLink>
    </div>

    <div class="invoice-layout">
      <!-- Colonna sinistra: form -->
      <div class="invoice-form-col">

        <!-- Dati fattura -->
        <section class="card">
          <h3 class="card-title">📋 Dati generali</h3>
          <div class="form-row">
            <div class="field" :class="{ error: errors.invoice_number }">
              <label>N° Fattura *</label>
              <input v-model.trim="form.invoice_number" type="text" placeholder="2024/001" />
              <span v-if="errors.invoice_number" class="field-error">{{ errors.invoice_number }}</span>
            </div>
            <div class="field" :class="{ error: errors.invoice_date }">
              <label>Data *</label>
              <input v-model="form.invoice_date" type="date" />
              <span v-if="errors.invoice_date" class="field-error">{{ errors.invoice_date }}</span>
            </div>
          </div>

          <div class="field" :class="{ error: errors.client_id }">
            <label>Cliente *</label>
            <select v-model="form.client_id">
              <option value="">Seleziona cliente…</option>
              <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }} — {{ c.vat_number }}</option>
            </select>
            <span v-if="errors.client_id" class="field-error">{{ errors.client_id }}</span>
          </div>

          <div class="field">
            <label>Note</label>
            <textarea v-model="form.notes" rows="2" placeholder="Descrizione servizi prestati…" />
          </div>
        </section>

        <!-- Righe fattura -->
        <section class="card">
          <div class="section-header">
            <h3 class="card-title">📝 Righe fattura</h3>
            <div style="display:flex;gap:0.5rem;align-items:center;">
              <button type="button" class="btn-add-hours" @click="hoursPickerOpen = !hoursPickerOpen">
                {{ hoursPickerOpen ? '✕ Chiudi selezione ore' : '⏱️ Da ore' }}
              </button>
              <button type="button" class="btn-add" @click="addItem">+ Aggiungi riga</button>
            </div>
          </div>

          <!-- Selezione ore raggruppate -->
          <div v-if="hoursPickerOpen" class="hours-picker">
            <div class="hours-picker-filters">
              <select v-model="hFilter.project_id" class="sel">
                <option value="">Tutti i progetti</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
              <select v-model="hFilter.client_id" class="sel">
                <option value="">Tutti i clienti</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
              </select>
              <input v-model="hFilter.month" type="month" class="sel" />
              <button type="button" class="btn-ghost-sm" @click="loadGroupedHours">🔍 Cerca</button>
            </div>

            <div v-if="groupedHoursLoading" class="hp-loading">Caricamento…</div>
            <div v-else-if="groupedHoursError" class="hp-error">⚠️ {{ groupedHoursError }}</div>
            <div v-else-if="!groupedHours.length && groupedHoursSearched" class="hp-empty">Nessun risultato. Prova con filtri diversi.</div>
            <div v-else-if="!groupedHours.length" class="hp-empty">Seleziona i filtri e clicca Cerca.</div>
            <table v-else class="hp-table">
              <thead>
                <tr><th></th><th>Mese</th><th>Progetto</th><th>Tariffa</th><th>Ore</th><th>Tariffa</th><th>Lordo</th></tr>
              </thead>
              <tbody>
                <tr v-for="g in groupedHours" :key="`${g.project_id}-${g.tariff_id}-${g.month}`" :class="{ 'hp-row-invoiced': g.invoiced_count > 0 }">
                  <td><input type="checkbox" :value="g" v-model="hSelected" /></td>
                  <td class="mono">{{ g.month }}</td>
                  <td>{{ g.project_name || '—' }}</td>
                  <td>{{ g.tariff_name }}<span v-if="g.invoiced_count > 0" class="hp-invoiced-badge" title="Già fatturate">🧾</span></td>
                  <td class="mono">{{ g.total_hours }}h</td>
                  <td class="mono">€ {{ fmt(g.hourly_rate) }}{{ g.rate_type === 'daily' ? '/g' : '/h' }}</td>
                  <td class="mono green">€ {{ fmt(grossFromHours(g)) }}</td>
                </tr>
              </tbody>
            </table>
            <div v-if="groupedHours.length" class="hp-footer">
              <button type="button" class="btn-add" @click="addFromHours" :disabled="!hSelected.length">
                + Aggiungi {{ hSelected.length }} riga/e selezionate
              </button>
            </div>
          </div>

          <div v-if="!form.items.length" class="empty-items">
            Nessuna riga. Clicca "+ Aggiungi riga" per iniziare.
          </div>

          <div v-for="(item, i) in form.items" :key="i" class="item-row">
            <div class="item-top">
              <span class="item-num">{{ i + 1 }}</span>
              <button type="button" class="btn-remove" @click="removeItem(i)" title="Rimuovi riga">✕</button>
            </div>

            <div class="field">
              <label>Descrizione *</label>
              <input v-model.trim="item.description" type="text" placeholder="Consulenza sviluppo software…" />
            </div>

            <div class="form-row-3">
              <div class="field">
                <label>Tariffa *</label>
                <select v-model="item.tariff_id" @change="onTariffChange(i)">
                  <option value="">Seleziona…</option>
                  <option v-for="t in tariffs" :key="t.id" :value="t.id">
                    {{ t.name }} (€ {{ fmt(t.hourly_rate) }}/h) {{ t.is_default ? '⭐' : '' }}
                  </option>
                </select>
              </div>
              <div class="field">
                <label>Ore *</label>
                <input v-model="item.hours" type="number" min="0.25" step="0.25" placeholder="8" @input="recompute" />
              </div>
              <div class="field">
                <label>€/ora</label>
                <input v-model="item.hourly_rate" type="number" min="0" step="0.01" placeholder="0.00" @input="recompute" />
              </div>
            </div>

            <div class="item-tax-toggle">
              <label class="radio-inline" :class="{ selected: !item.tax_inclusive }">
                <input v-model="item.tax_inclusive" type="radio" :value="false" @change="recompute" />
                4% esclusivo
              </label>
              <label class="radio-inline" :class="{ selected: item.tax_inclusive }">
                <input v-model="item.tax_inclusive" type="radio" :value="true" @change="recompute" />
                4% inclusivo
              </label>
            </div>

            <div v-if="computed_items[i]" class="item-preview">
              <span>Lordo: <strong class="mono">€ {{ fmt(computed_items[i].gross) }}</strong></span>
              <span>4%: <strong class="mono">€ {{ fmt(computed_items[i].tax) }}</strong></span>
              <span>Imponibile: <strong class="mono">€ {{ fmt(computed_items[i].imponibile) }}</strong></span>
            </div>
          </div>
        </section>

        <div v-if="saveError" class="alert-error">{{ saveError }}</div>

        <div class="form-actions">
          <button type="button" class="btn-secondary" @click="simulate">🔢 Simula</button>
          <button type="button" class="btn-primary" :disabled="saving" @click="saveInvoice">
            <span v-if="saving" class="spinner" />
            {{ saving ? 'Salvataggio…' : '💾 Salva fattura' }}
          </button>
        </div>
      </div>

      <!-- Colonna destra: anteprima totali -->
      <div class="invoice-preview-col">
        <div class="preview-card" :class="{ highlighted: simulated }">
          <div class="preview-header">
            <h3>📊 Riepilogo</h3>
            <span v-if="simulated" class="simulated-badge">Simulato</span>
          </div>

          <div class="preview-client" v-if="selectedClient">
            <div class="preview-client-name">{{ selectedClient.company_name }}</div>
            <div class="preview-client-vat">P.IVA: {{ selectedClient.vat_number }}</div>
          </div>
          <div v-else class="preview-no-client">Seleziona un cliente…</div>

          <div class="preview-lines">
            <div v-for="(ci, i) in computed_items" :key="i" class="preview-line">
              <span class="preview-line-desc">{{ form.items[i].description || `Riga ${i+1}` }}</span>
              <span class="mono">€ {{ fmt(ci.gross) }}</span>
            </div>
          </div>

          <div class="preview-totals">
            <div class="preview-total-row">
              <span>Imponibile</span>
              <span class="mono">€ {{ fmt(totals.subtotal) }}</span>
            </div>
            <div class="preview-total-row">
              <span>4% ritenuta</span>
              <span class="mono">€ {{ fmt(totals.tax) }}</span>
            </div>
            <div class="preview-total-row">
              <span>Bollo virtuale</span>
              <span class="mono">€ {{ fmt(form.stamp_duty) }}</span>
            </div>
            <div class="preview-total-row grand">
              <span>TOTALE</span>
              <span class="mono">€ {{ fmt(totals.total) }}</span>
            </div>
          </div>

          <div class="preview-note">
            ℹ️ Il bollo di € 2,00 è dovuto su fatture esenti IVA superiori a € 77,47
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api.js';

const router    = useRouter();
const clients   = ref([]);
const tariffs   = ref([]);
const projects  = ref([]);
const saving    = ref(false);
const saveError = ref('');
const simulated = ref(false);

// ── Hours picker state ────────────────────────────────────
const hoursPickerOpen      = ref(false);
const groupedHours         = ref([]);
const groupedHoursLoading  = ref(false);
const groupedHoursError    = ref('');
const groupedHoursSearched = ref(false);
const hSelected            = ref([]);
const hFilter              = reactive({ project_id: '', client_id: '', month: '' });

const form = reactive({
  invoice_number: '',
  invoice_date:   new Date().toISOString().slice(0, 10),
  client_id:      '',
  stamp_duty:     2.00,
  notes:          '',
  items:          [],
});

const errors = reactive({ invoice_number: '', invoice_date: '', client_id: '' });

// ── Computed ─────────────────────────────────────────────
const selectedClient = computed(() => clients.value.find(c => c.id == form.client_id) ?? null);

const computed_items = computed(() =>
  form.items.map(item => {
    const gross = (parseFloat(item.hourly_rate) || 0) * (parseFloat(item.hours) || 0);
    let imponibile, tax;
    if (item.tax_inclusive) {
      imponibile = gross / 1.04;
      tax        = gross - imponibile;
    } else {
      imponibile = gross;
      tax        = gross * 0.04;
    }
    return { gross, imponibile, tax };
  })
);

const totals = computed(() => {
  const subtotal = computed_items.value.reduce((s, i) => s + i.imponibile, 0);
  const tax      = computed_items.value.reduce((s, i) => s + i.tax, 0);
  const total    = subtotal + tax + parseFloat(form.stamp_duty || 0);
  return { subtotal, tax, total };
});

// ── Helpers ──────────────────────────────────────────────
function fmt(v) { return Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function recompute() { simulated.value = false; }
function effectiveHourlyRate(g) { return g.rate_type === 'daily' ? g.hourly_rate / 8 : g.hourly_rate; }
function grossFromHours(g) { return effectiveHourlyRate(g) * g.total_hours; }

async function loadGroupedHours() {
  groupedHoursLoading.value  = true;
  groupedHoursError.value    = '';
  groupedHoursSearched.value = false;
  hSelected.value = [];
  try {
    const params = new URLSearchParams();
    if (hFilter.project_id) params.set('project_id', hFilter.project_id);
    if (hFilter.client_id)  params.set('client_id',  hFilter.client_id);
    if (hFilter.month)      params.set('month',       hFilter.month);
    const { data } = await api.get(`/hours/my/grouped?${params}`);
    groupedHours.value = data;
    groupedHoursSearched.value = true;
  } catch (err) {
    groupedHoursError.value = err.response?.data?.message ?? 'Errore durante la ricerca.';
  } finally {
    groupedHoursLoading.value = false;
  }
}

function addFromHours() {
  for (const g of hSelected.value) {
    const monthLabel = g.month ? ` — ${g.month}` : '';
    const projectLabel = g.project_name ? ` (${g.project_name})` : '';
    form.items.push({
      description:   `${g.tariff_name}${projectLabel}${monthLabel}`,
      tariff_id:     g.tariff_id,
      hours:         g.total_hours,
      hourly_rate:   effectiveHourlyRate(g),
      tax_inclusive: Boolean(g.tax_inclusive),
      line_total:    0,
      _work_hour_ids: g.work_hour_ids,
    });
  }
  hSelected.value = [];
  hoursPickerOpen.value = false;
  recompute();
}

function addItem() {
  const def = tariffs.value.find(t => t.is_default);
  form.items.push({
    description:   '',
    tariff_id:     def?.id ?? '',
    hours:         '',
    hourly_rate:   def?.hourly_rate ?? '',
    tax_inclusive: def ? Boolean(def.tax_inclusive) : false,
    line_total:    0,
  });
}

function removeItem(i) { form.items.splice(i, 1); }

function onTariffChange(i) {
  const t = tariffs.value.find(x => x.id == form.items[i].tariff_id);
  if (t) {
    form.items[i].hourly_rate   = t.hourly_rate;
    form.items[i].tax_inclusive = Boolean(t.tax_inclusive);
  }
  recompute();
}

function validate() {
  errors.invoice_number = form.invoice_number ? '' : 'Campo obbligatorio';
  errors.invoice_date   = form.invoice_date   ? '' : 'Campo obbligatorio';
  errors.client_id      = form.client_id      ? '' : 'Seleziona un cliente';
  if (Object.values(errors).some(Boolean)) return false;
  if (!form.items.length) { saveError.value = 'Aggiungi almeno una riga.'; return false; }
  return true;
}

// ── Actions ──────────────────────────────────────────────
async function simulate() {
  if (!form.items.length) return;
  simulated.value = true;
}

async function saveInvoice() {
  saveError.value = '';
  if (!validate()) return;

  const items = form.items.map((item, i) => ({
    description:   item.description || `Riga ${i + 1}`,
    tariff_id:     item.tariff_id,
    hours:         parseFloat(item.hours),
    hourly_rate:   parseFloat(item.hourly_rate),
    tax_inclusive: item.tax_inclusive,
    line_total:    computed_items.value[i].gross,
    work_hour_ids: item._work_hour_ids ?? [],
  }));

  const payload = {
    invoice_number: form.invoice_number,
    client_id:      form.client_id,
    invoice_date:   form.invoice_date,
    stamp_duty:     form.stamp_duty,
    subtotal:       totals.value.subtotal,
    tax_amount:     totals.value.tax,
    total:          totals.value.total,
    notes:          form.notes,
    items,
  };

  saving.value = true;
  try {
    await api.post('/invoices', payload);
    router.push('/invoices');
  } catch (err) {
    saveError.value = err.response?.data?.message ?? 'Errore durante il salvataggio.';
  } finally {
    saving.value = false;
  }
}

// ── Fetch ────────────────────────────────────────────────
onMounted(async () => {
  const [c, t, p] = await Promise.all([api.get('/clients'), api.get('/tariffs'), api.get('/projects')]);
  clients.value  = c.data.filter(x => x.is_active);
  tariffs.value  = t.data;
  projects.value = p.data.filter(x => x.is_active);
  // Proponi numero fattura automatico
  form.invoice_number = `${new Date().getFullYear()}/001`;
});
</script>

<style scoped>

.btn-ghost { background: #f3f4f6; color: #374151; border: none; border-radius: 8px; padding: 0.55rem 1rem; font-size: 0.875rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
.btn-ghost:hover { background: #e5e7eb; }

/* ── Layout ────────────────────────────────────────────── */
.invoice-layout {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 1.5rem;
  align-items: start;
}

@media (max-width: 900px) {
  .invoice-layout { grid-template-columns: 1fr; }
  .invoice-preview-col { order: -1; }
}

/* ── Card ──────────────────────────────────────────────── */
.card {
  background: #fff;
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  margin-bottom: 1rem;
}

.card-title { font-size: 0.95rem; font-weight: 700; color: #111827; margin-bottom: 1rem; }

.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.section-header .card-title { margin-bottom: 0; }

/* ── Form ──────────────────────────────────────────────── */
.form-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.form-row-3 { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem; }
@media (max-width: 580px) { .form-row, .form-row-3 { grid-template-columns: 1fr; } }

.field { margin-bottom: 0.75rem; }

/* ── Items ─────────────────────────────────────────────── */
.btn-add {
  background: #eff6ff; color: #1d4ed8; border: 1px solid #93c5fd;
  border-radius: 8px; padding: 0.4rem 0.875rem; font-size: 0.85rem;
  font-weight: 600; cursor: pointer;
}
.btn-add:hover { background: #dbeafe; }

.empty-items { text-align: center; padding: 1.5rem; color: #9ca3af; font-size: 0.875rem; }

.item-row {
  border: 1.5px solid #e5e7eb; border-radius: 10px;
  padding: 1rem; margin-bottom: 0.75rem; background: #fafafa;
}

.item-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
.item-num { background: #0f3460; color: #fff; width: 1.5rem; height: 1.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; }

.btn-remove { background: none; border: none; color: #ef4444; cursor: pointer; font-size: 0.9rem; padding: 0.25rem; border-radius: 4px; }
.btn-remove:hover { background: #fef2f2; }

.item-tax-toggle { display: flex; gap: 0.75rem; margin-bottom: 0.75rem; }

.radio-inline {
  display: flex; align-items: center; gap: 0.375rem;
  padding: 0.375rem 0.75rem; border: 1.5px solid #e5e7eb;
  border-radius: 8px; font-size: 0.8rem; font-weight: 500;
  color: #6b7280; cursor: pointer; transition: all 0.15s;
}
.radio-inline.selected { border-color: #0f3460; background: #eff6ff; color: #1d4ed8; font-weight: 600; }
.radio-inline input { display: none; }

.item-preview {
  display: flex; gap: 1rem; flex-wrap: wrap;
  font-size: 0.8rem; color: #6b7280;
  background: #f0fdf4; border-radius: 6px; padding: 0.5rem 0.75rem;
}
.item-preview .mono { color: #059669; }

/* ── Form actions ───────────────────────────────────────── */
.form-actions { display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap; }

/* ── Preview card ───────────────────────────────────────── */
.preview-card {
  background: #fff; border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  padding: 1.25rem; position: sticky; top: 1rem;
  border: 2px solid transparent; transition: border-color 0.3s;
}

.preview-card.highlighted { border-color: #059669; }

.preview-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.preview-header h3 { font-size: 0.95rem; font-weight: 700; color: #111827; }
.simulated-badge { background: #d1fae5; color: #065f46; font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px; }

.preview-client { margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f3f4f6; }
.preview-client-name { font-size: 0.9rem; font-weight: 700; color: #111827; }
.preview-client-vat  { font-size: 0.78rem; color: #9ca3af; margin-top: 0.125rem; }
.preview-no-client { color: #d1d5db; font-size: 0.875rem; margin-bottom: 1rem; font-style: italic; }

.preview-lines { margin-bottom: 1rem; display: flex; flex-direction: column; gap: 0.375rem; }
.preview-line { display: flex; justify-content: space-between; font-size: 0.8rem; color: #6b7280; }
.preview-line-desc { max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.preview-totals { border-top: 1px solid #e5e7eb; padding-top: 0.75rem; display: flex; flex-direction: column; gap: 0.375rem; }
.preview-total-row { display: flex; justify-content: space-between; font-size: 0.875rem; color: #374151; }
.preview-total-row.grand { border-top: 2px solid #e5e7eb; padding-top: 0.5rem; font-weight: 800; font-size: 1.1rem; color: #059669; margin-top: 0.25rem; }
.preview-note { margin-top: 1rem; font-size: 0.75rem; color: #9ca3af; line-height: 1.4; }

/* ── Hours picker ───────────────────────────────────────── */
.btn-add-hours {
  background: #f0fdf4; color: #059669; border: 1px solid #6ee7b7;
  border-radius: 8px; padding: 0.4rem 0.875rem; font-size: 0.85rem;
  font-weight: 600; cursor: pointer;
}
.btn-add-hours:hover { background: #dcfce7; }

.hours-picker { border: 1.5px solid #e5e7eb; border-radius: 10px; padding: 1rem; margin-bottom: 1rem; background: #fafafa; }
.hours-picker-filters { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem; }
.sel { padding: 0.4rem 0.6rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; background: #fff; outline: none; color: #374151; }
.sel:focus { border-color: #0f3460; }
.btn-ghost-sm { background: #f3f4f6; color: #374151; border: none; border-radius: 8px; padding: 0.4rem 0.75rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
.btn-ghost-sm:hover { background: #e5e7eb; }

.hp-loading, .hp-empty { padding: 1rem; text-align: center; color: #9ca3af; font-size: 0.875rem; }
.hp-error { padding: 1rem; text-align: center; color: #b91c1c; font-size: 0.875rem; background: #fef2f2; border-radius: 6px; }

.hp-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; margin-bottom: 0.75rem; }
.hp-table th { text-align: left; padding: 0.5rem 0.5rem; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
.hp-table td { padding: 0.5rem 0.5rem; border-bottom: 1px solid #f3f4f6; color: #374151; }
.hp-table tbody tr:hover td { background: #f0fdf4; }
.hp-row-invoiced td { background: #fefce8; }
.hp-invoiced-badge { margin-left: 0.3rem; font-size: 0.85rem; cursor: default; }

.hp-footer { display: flex; justify-content: flex-end; }

.alert-error { margin-bottom: 0.75rem; }
</style>
