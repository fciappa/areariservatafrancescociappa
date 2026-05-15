<template>
  <div class="page">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h2>💰 Tariffario</h2>
        <p class="page-sub">Gestisci le tariffe orarie applicabili a collaboratori e clienti</p>
      </div>
      <button class="btn-primary" @click="openNew">+ Nuova tariffa</button>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 4" :key="i" class="skeleton-row" />
    </div>

    <!-- Empty -->
    <div v-else-if="!tariffs.length" class="empty-state">
      <span>💰</span>
      <p>Nessuna tariffa ancora inserita.</p>
    </div>

    <!-- Cards tariffe -->
    <div v-else class="tariff-grid">
      <div
        v-for="t in tariffs"
        :key="t.id"
        class="tariff-card"
        :class="{ 'is-default': t.is_default }"
      >
        <div class="card-top">
          <div class="card-name">
            {{ t.name }}
            <span v-if="t.is_default" class="default-badge">⭐ Default</span>
          </div>
          <div class="card-actions">
            <button class="btn-icon" title="Modifica" @click="openEdit(t)">✏️</button>
            <button class="btn-icon" title="Elimina" @click="remove(t)">🗑️</button>
          </div>
        </div>

        <div class="card-rate">
          € {{ formatAmount(t.hourly_rate) }}<span class="rate-unit">{{ t.rate_type === 'daily' ? '/giorno (8h)' : '/ora' }}</span>
        </div>

        <div class="card-meta">
          <span class="meta-pill" :class="t.tax_inclusive ? 'inclusive' : 'exclusive'">
            {{ t.tax_inclusive ? '4% incluso' : '4% esclusivo' }}
          </span>
        </div>

        <div class="card-validity">
          <span>📅 Dal <strong>{{ formatDate(t.valid_from) }}</strong></span>
          <span v-if="t.valid_to"> → <strong>{{ formatDate(t.valid_to) }}</strong></span>
          <span v-else class="no-expiry"> · nessuna scadenza</span>
        </div>

        <div v-if="t.notes" class="card-notes">{{ t.notes }}</div>
      </div>
    </div>

    <!-- Modale -->
    <Teleport to="body">
      <div v-if="modal.open" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <div class="modal-header">
            <h3>{{ modal.isNew ? 'Nuova tariffa' : 'Modifica tariffa' }}</h3>
            <button class="modal-close" @click="closeModal">✕</button>
          </div>

          <form class="modal-form" @submit.prevent="save">

            <div class="field" :class="{ error: formErrors.name }">
              <label>Nome tariffa *</label>
              <input v-model.trim="form.name" type="text" placeholder="es. Tariffa Standard 2024" />
              <span v-if="formErrors.name" class="field-error">{{ formErrors.name }}</span>
            </div>

            <!-- Tipo tariffa -->
            <div class="field-group">
              <div class="field-group-label">Tipo tariffa</div>
              <label class="radio-card" :class="{ selected: form.rate_type === 'hourly' }">
                <input v-model="form.rate_type" type="radio" value="hourly" />
                <div>
                  <div class="radio-title">⏱️ Oraria</div>
                  <div class="radio-sub">La tariffa è espressa per ora lavorata</div>
                </div>
              </label>
              <label class="radio-card" :class="{ selected: form.rate_type === 'daily' }">
                <input v-model="form.rate_type" type="radio" value="daily" />
                <div>
                  <div class="radio-title">📅 Giornaliera</div>
                  <div class="radio-sub">La tariffa è per giornata intera (8 ore)</div>
                </div>
              </label>
            </div>

            <div class="field" :class="{ error: formErrors.hourly_rate }">
              <label>{{ form.rate_type === 'daily' ? 'Tariffa giornaliera (€)' : 'Tariffa oraria (€)' }} *</label>
              <input v-model="form.hourly_rate" type="number" min="0" step="0.01" placeholder="0.00" />
              <span v-if="formErrors.hourly_rate" class="field-error">{{ formErrors.hourly_rate }}</span>
            </div>

            <div class="form-row">
              <div class="field" :class="{ error: formErrors.valid_from }">
                <label>Valida dal *</label>
                <input v-model="form.valid_from" type="date" />
                <span v-if="formErrors.valid_from" class="field-error">{{ formErrors.valid_from }}</span>
              </div>
              <div class="field">
                <label>Valida fino al <span class="optional">(opzionale)</span></label>
                <input v-model="form.valid_to" type="date" :min="form.valid_from" />
              </div>
            </div>

            <!-- 4% -->
            <div class="field-group">
              <div class="field-group-label">Gestione 4% ritenuta</div>
              <label class="radio-card" :class="{ selected: !form.tax_inclusive }">
                <input v-model="form.tax_inclusive" type="radio" :value="false" />
                <div>
                  <div class="radio-title">Esclusiva</div>
                  <div class="radio-sub">Il 4% viene aggiunto al totale → <em>tariffa × ore + 4%</em></div>
                </div>
              </label>
              <label class="radio-card" :class="{ selected: form.tax_inclusive }">
                <input v-model="form.tax_inclusive" type="radio" :value="true" />
                <div>
                  <div class="radio-title">Inclusiva</div>
                  <div class="radio-sub">Il 4% è già nel prezzo → viene scorporato dal totale</div>
                </div>
              </label>
            </div>

            <!-- Default -->
            <label class="checkbox-card" :class="{ selected: form.is_default }">
              <input v-model="form.is_default" type="checkbox" />
              <div>
                <div class="radio-title">⭐ Tariffa di default</div>
                <div class="radio-sub">Verrà proposta automaticamente all'inserimento delle ore</div>
              </div>
            </label>

            <div class="field">
              <label>Note</label>
              <textarea v-model="form.notes" rows="2" placeholder="Note aggiuntive…" />
            </div>

            <!-- Preview calcolo -->
            <div class="preview-box">
              <div class="preview-title">🔢 Anteprima calcolo (es. {{ preview.label }})</div>
              <div class="preview-rows">
                <div class="preview-row">
                  <span>Imponibile</span>
                  <span class="mono">€ {{ preview.imponibile }}</span>
                </div>
                <div class="preview-row">
                  <span>4% ritenuta</span>
                  <span class="mono">€ {{ preview.tax }}</span>
                </div>
                <div class="preview-row total">
                  <span>Totale (+ bollo € 2)</span>
                  <span class="mono">€ {{ preview.total }}</span>
                </div>
              </div>
            </div>

            <div v-if="saveError" class="alert-error">{{ saveError }}</div>

            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="closeModal">Annulla</button>
              <button type="submit" class="btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner" />
                {{ saving ? 'Salvataggio…' : 'Salva' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '../services/api.js';

// ── State ────────────────────────────────────────────────
const tariffs   = ref([]);
const loading   = ref(true);
const saving    = ref(false);
const saveError = ref('');

const modal = reactive({ open: false, isNew: true, _id: null });

const emptyForm = () => ({
  name: '', rate_type: 'hourly', hourly_rate: '', valid_from: today(),
  valid_to: '', is_default: false, tax_inclusive: false, notes: '',
});

const form       = reactive(emptyForm());
const formErrors = reactive({ name: '', hourly_rate: '', valid_from: '' });

// ── Preview calcolo ──────────────────────────────────────
const preview = computed(() => {
  const rate  = parseFloat(form.hourly_rate) || 0;
  // Se giornaliera: 1 giorno = 8 ore → tariffa effettiva/ora = rate / 8
  const effectiveHourly = form.rate_type === 'daily' ? rate / 8 : rate;
  const hours = form.rate_type === 'daily' ? 8 : 10; // anteprima: 1 giorno o 10 ore
  const gross = effectiveHourly * hours;
  let imponibile, tax;
  if (form.tax_inclusive) {
    imponibile = gross / 1.04;
    tax        = gross - imponibile;
  } else {
    imponibile = gross;
    tax        = gross * 0.04;
  }
  const total = imponibile + tax + 2; // bollo
  return {
    label:      form.rate_type === 'daily' ? '1 giorno (8h)' : '10 ore',
    imponibile: fmt(imponibile),
    tax:        fmt(tax),
    total:      fmt(total),
  };
});

// ── Helpers ──────────────────────────────────────────────
function today() {
  return new Date().toISOString().slice(0, 10);
}

function fmt(v) {
  return Number(v).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatAmount(v) { return fmt(v); }

function formatDate(d) {
  if (!d) return '';
  return new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function resetForm() {
  Object.assign(form, emptyForm());
  Object.assign(formErrors, { name: '', hourly_rate: '', valid_from: '' });
  saveError.value = '';
}

function validate() {
  formErrors.name        = form.name        ? '' : 'Campo obbligatorio';
  formErrors.hourly_rate = form.hourly_rate ? '' : 'Campo obbligatorio';
  formErrors.valid_from  = form.valid_from  ? '' : 'Campo obbligatorio';
  return !formErrors.name && !formErrors.hourly_rate && !formErrors.valid_from;
}

// ── Fetch ────────────────────────────────────────────────
async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/tariffs');
    tariffs.value = data;
  } finally {
    loading.value = false;
  }
}

// ── Modal ────────────────────────────────────────────────
function openNew() {
  resetForm();
  modal.isNew = true;
  modal.open  = true;
  modal._id   = null;
}

function openEdit(t) {
  resetForm();
  Object.assign(form, {
    name:          t.name,
    rate_type:     t.rate_type ?? 'hourly',
    hourly_rate:   t.hourly_rate,
    valid_from:    t.valid_from?.slice(0, 10) ?? '',
    valid_to:      t.valid_to?.slice(0, 10)   ?? '',
    is_default:    Boolean(t.is_default),
    tax_inclusive: Boolean(t.tax_inclusive),
    notes:         t.notes ?? '',
  });
  modal.isNew = false;
  modal.open  = true;
  modal._id   = t.id;
}

function closeModal() { modal.open = false; }

// ── CRUD ─────────────────────────────────────────────────
async function save() {
  if (!validate()) return;
  saving.value    = true;
  saveError.value = '';
  try {
    const payload = { ...form, valid_to: form.valid_to || null };
    if (modal.isNew) {
      await api.post('/tariffs', payload);
    } else {
      await api.put(`/tariffs/${modal._id}`, payload);
    }
    await load();
    closeModal();
  } catch (err) {
    saveError.value = err.response?.data?.message ?? 'Errore durante il salvataggio.';
  } finally {
    saving.value = false;
  }
}

async function remove(t) {
  if (!confirm(`Eliminare la tariffa "${t.name}"?\nAttenzione: l'operazione è irreversibile.`)) return;
  await api.delete(`/tariffs/${t.id}`);
  await load();
}

onMounted(load);
</script>

<style scoped>
.page { padding: 2rem; max-width: 1100px; }

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.75rem;
  gap: 1rem;
  flex-wrap: wrap;
}

.page-header h2 { font-size: 1.5rem; font-weight: 700; color: #111827; }
.page-sub       { font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem; }

/* ── Tariff Cards ───────────────────────────────────────── */
.tariff-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
}

.tariff-card {
  background: #fff;
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  border: 2px solid transparent;
  transition: transform 0.15s, border-color 0.15s;
}

.tariff-card:hover { transform: translateY(-2px); }

.tariff-card.is-default {
  border-color: #f59e0b;
  background: linear-gradient(135deg, #fffbeb 0%, #fff 40%);
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.card-name {
  font-size: 1rem;
  font-weight: 700;
  color: #111827;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
}

.default-badge {
  font-size: 0.7rem;
  background: #fef3c7;
  color: #92400e;
  padding: 0.15rem 0.5rem;
  border-radius: 9999px;
  font-weight: 600;
}

.card-actions { display: flex; gap: 0.25rem; flex-shrink: 0; }

.card-rate {
  font-size: 2rem;
  font-weight: 800;
  color: #059669;
  font-family: 'Courier New', monospace;
  margin-bottom: 0.75rem;
}

.rate-unit { font-size: 1rem; color: #6b7280; font-weight: 400; }

.card-meta { margin-bottom: 0.75rem; }

.meta-pill {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.meta-pill.inclusive  { background: #d1fae5; color: #065f46; }
.meta-pill.exclusive  { background: #dbeafe; color: #1e40af; }

.card-validity {
  font-size: 0.8rem;
  color: #6b7280;
  margin-bottom: 0.5rem;
}

.no-expiry { color: #9ca3af; }

.card-notes {
  font-size: 0.8rem;
  color: #9ca3af;
  font-style: italic;
  border-top: 1px solid #f3f4f6;
  padding-top: 0.5rem;
  margin-top: 0.5rem;
}

/* ── Form ──────────────────────────────────────────────── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 500;
  padding: 1rem;
}

.modal {
  background: #fff;
  border-radius: 16px;
  width: 100%;
  max-width: 540px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 { font-size: 1.1rem; font-weight: 700; color: #111827; }

.modal-close {
  background: none;
  border: none;
  font-size: 1.1rem;
  cursor: pointer;
  color: #6b7280;
  padding: 0.25rem;
  border-radius: 4px;
  line-height: 1;
}

.modal-close:hover { background: #f3f4f6; color: #111827; }

.modal-form {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 480px) { .form-row { grid-template-columns: 1fr; } }

.field { display: flex; flex-direction: column; gap: 0.375rem; }

.field label { font-size: 0.8rem; font-weight: 600; color: #374151; }

.optional { font-weight: 400; color: #9ca3af; }

.field input[type="text"],
.field input[type="number"],
.field input[type="date"],
.field textarea {
  padding: 0.5rem 0.75rem;
  border: 1.5px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.9rem;
  color: #111827;
  background: #f9fafb;
  outline: none;
  transition: border-color 0.2s;
  font-family: inherit;
  resize: vertical;
}

.field input:focus,
.field textarea:focus {
  border-color: #0f3460;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(15,52,96,0.1);
}

.field.error input { border-color: #ef4444; }
.field-error { font-size: 0.78rem; color: #ef4444; }

/* ── Radio cards ────────────────────────────────────────── */
.field-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.field-group-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: #374151;
}

.radio-card,
.checkbox-card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}

.radio-card input,
.checkbox-card input { margin-top: 0.15rem; flex-shrink: 0; cursor: pointer; }

.radio-card.selected,
.checkbox-card.selected {
  border-color: #0f3460;
  background: #eff6ff;
}

.radio-title { font-size: 0.875rem; font-weight: 600; color: #111827; }
.radio-sub   { font-size: 0.78rem; color: #6b7280; margin-top: 0.125rem; }

/* ── Preview ────────────────────────────────────────────── */
.preview-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1rem;
}

.preview-title {
  font-size: 0.8rem;
  font-weight: 700;
  color: #374151;
  margin-bottom: 0.75rem;
}

.preview-rows { display: flex; flex-direction: column; gap: 0.375rem; }

.preview-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  color: #374151;
}

.preview-row.total {
  border-top: 1px solid #e2e8f0;
  padding-top: 0.375rem;
  font-weight: 700;
  color: #059669;
}

.mono { font-family: 'Courier New', monospace; }

/* ── Buttons ────────────────────────────────────────────── */
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  background: linear-gradient(135deg, #0f3460, #1a6fb5);
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 0.6rem 1.1rem;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s;
  white-space: nowrap;
}

.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
  border: none;
  border-radius: 8px;
  padding: 0.6rem 1.1rem;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-secondary:hover { background: #e5e7eb; }

.btn-icon {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.25rem 0.375rem;
  border-radius: 6px;
  font-size: 1rem;
  transition: background 0.15s;
}

.btn-icon:hover { background: #f3f4f6; }

.alert-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  color: #b91c1c;
  padding: 0.75rem;
  font-size: 0.875rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding-top: 0.5rem;
}

.spinner {
  display: inline-block;
  width: 0.875rem;
  height: 0.875rem;
  border: 2px solid rgba(255,255,255,0.35);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.skeleton-list { display: flex; flex-direction: column; gap: 0.5rem; }

.skeleton-row {
  height: 160px;
  border-radius: 12px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}

@keyframes shimmer { to { background-position: -200% 0; } }

.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  color: #9ca3af;
}

.empty-state span { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; }

@media (max-width: 768px) { .page { padding: 1rem; } }
</style>
