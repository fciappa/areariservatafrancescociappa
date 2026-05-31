<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>📌 Scadenze</h2>
        <p class="page-sub">Visualizza e inserisci scadenze collegate ai clienti</p>
      </div>
      <button class="btn-primary" @click="openNew">+ Nuova scadenza</button>
    </div>

    <div class="toolbar card">
      <input
        v-model="search"
        class="search-input"
        type="search"
        placeholder="Cerca per tipo, descrizione, dominio o note..."
      />
      <select v-model="selectedClientId" class="client-select">
        <option value="">Tutti i clienti</option>
        <option v-for="c in clients" :key="c.id" :value="String(c.id)">
          {{ c.company_name }}
        </option>
      </select>
      <div class="sort-badge" :class="sortDirection === 'asc' ? 'asc' : 'desc'">
        Ordinato per: {{ activeSortLabel }}
      </div>
    </div>

    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 5" :key="i" class="skeleton-row" />
    </div>

    <div v-else-if="!filtered.length" class="empty-state">
      <span>📌</span>
      <p>Nessuna scadenza trovata.</p>
    </div>

    <div v-else class="table-wrapper">
      <table class="data-table deadlines-table">
        <thead>
          <tr>
            <th><button class="th-sort" @click="toggleSort('due_date')">Scadenza <span class="sort-indicator">{{ sortIndicator('due_date') }}</span></button></th>
            <th><button class="th-sort" @click="toggleSort('company_name')">Cliente <span class="sort-indicator">{{ sortIndicator('company_name') }}</span></button></th>
            <th><button class="th-sort" @click="toggleSort('item_type')">Tipo <span class="sort-indicator">{{ sortIndicator('item_type') }}</span></button></th>
            <th><button class="th-sort" @click="toggleSort('description')">Descrizione <span class="sort-indicator">{{ sortIndicator('description') }}</span></button></th>
            <th><button class="th-sort" @click="toggleSort('linked_to')">Collegato a <span class="sort-indicator">{{ sortIndicator('linked_to') }}</span></button></th>
            <th><button class="th-sort" @click="toggleSort('avada_version')">Avada <span class="sort-indicator">{{ sortIndicator('avada_version') }}</span></button></th>
            <th><button class="th-sort" @click="toggleSort('php_version')">PHP <span class="sort-indicator">{{ sortIndicator('php_version') }}</span></button></th>
            <th><button class="th-sort" @click="toggleSort('mysql_version')">MySQL <span class="sort-indicator">{{ sortIndicator('mysql_version') }}</span></button></th>
            <th><button class="th-sort" @click="toggleSort('wp_version')">WP <span class="sort-indicator">{{ sortIndicator('wp_version') }}</span></button></th>
            <th><button class="th-sort" @click="toggleSort('test_email')">Test email <span class="sort-indicator">{{ sortIndicator('test_email') }}</span></button></th>
            <th><button class="th-sort" @click="toggleSort('notes')">Note <span class="sort-indicator">{{ sortIndicator('notes') }}</span></button></th>
            <th><button class="th-sort" @click="toggleSort('amount')">Importo <span class="sort-indicator">{{ sortIndicator('amount') }}</span></button></th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in filtered" :key="d.id">
            <td class="mono">{{ formatDate(d.due_date) }}</td>
            <td class="fw">{{ d.company_name }}</td>
            <td>{{ d.item_type }}</td>
            <td>{{ d.description }}</td>
            <td>{{ d.linked_to || '—' }}</td>
            <td>{{ d.avada_version || '—' }}</td>
            <td>{{ d.php_version || '—' }}</td>
            <td>{{ d.mysql_version || '—' }}</td>
            <td>{{ d.wp_version || '—' }}</td>
            <td>{{ d.test_email || '—' }}</td>
            <td class="notes">{{ d.notes || '—' }}</td>
            <td class="mono amount">{{ d.amount != null ? formatAmount(d.amount) : '—' }}</td>
            <td class="actions">
              <button class="btn-icon" title="Rinnova data +1 anno" @click="renewDate(d)">🔁</button>
              <button class="btn-icon" title="Modifica scadenza" @click="openEdit(d)">✏️</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="modalState.open" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <div class="modal-header">
            <h3>{{ modalState.isNew ? 'Nuova scadenza' : 'Modifica scadenza' }}</h3>
            <button class="modal-close" @click="closeModal">✕</button>
          </div>

          <form class="modal-form" @submit.prevent="save">
            <div class="field" :class="{ error: formErrors.client_id }">
              <label>Cliente *</label>
              <select v-model="form.client_id">
                <option value="">Seleziona cliente</option>
                <option v-for="c in clients" :key="c.id" :value="String(c.id)">
                  {{ c.company_name }}
                </option>
              </select>
              <span v-if="formErrors.client_id" class="field-error">{{ formErrors.client_id }}</span>
            </div>

            <div class="form-row">
              <div class="field" :class="{ error: formErrors.due_date }">
                <label>Scadenza *</label>
                <input v-model="form.due_date" type="date" />
                <span v-if="formErrors.due_date" class="field-error">{{ formErrors.due_date }}</span>
              </div>
              <div class="field" :class="{ error: formErrors.item_type }">
                <label>Tipo *</label>
                <input v-model.trim="form.item_type" type="text" placeholder="es. dominio con redirect" />
                <span v-if="formErrors.item_type" class="field-error">{{ formErrors.item_type }}</span>
              </div>
            </div>

            <div class="field" :class="{ error: formErrors.description }">
              <label>Descrizione *</label>
              <input v-model.trim="form.description" type="text" placeholder="es. enpabil.com" />
              <span v-if="formErrors.description" class="field-error">{{ formErrors.description }}</span>
            </div>

            <div class="field">
              <label>Collegato a</label>
              <input v-model.trim="form.linked_to" type="text" placeholder="es. dominio/progetto collegato" />
            </div>

            <div class="form-row triple-row">
              <div class="field">
                <label>Avada</label>
                <input v-model.trim="form.avada_version" type="text" placeholder="es. 7.13.2" />
              </div>
              <div class="field">
                <label>PHP</label>
                <input v-model.trim="form.php_version" type="text" placeholder="es. 8.4.12" />
              </div>
              <div class="field">
                <label>MySQL</label>
                <input v-model.trim="form.mysql_version" type="text" placeholder="es. 5.7" />
              </div>
            </div>

            <div class="form-row triple-row">
              <div class="field">
                <label>WP</label>
                <input v-model.trim="form.wp_version" type="text" placeholder="es. 6.8.2" />
              </div>
              <div class="field">
                <label>Test email</label>
                <input v-model.trim="form.test_email" type="text" placeholder="es. OK / NO" />
              </div>
              <div class="field">
                <label>Importo (€)</label>
                <input v-model="form.amount" type="number" min="0" step="0.01" placeholder="0.00" />
              </div>
            </div>

            <div class="field">
              <label>Note</label>
              <textarea v-model="form.notes" rows="3" placeholder="Note operative..." />
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
import { computed, onMounted, reactive, ref, watch } from 'vue';
import api from '../services/api.js';

const SORT_STORAGE_KEY = 'deadlines.sort';

function loadSortPreference() {
  try {
    const raw = localStorage.getItem(SORT_STORAGE_KEY);
    if (!raw) return { key: 'due_date', direction: 'asc' };

    const parsed = JSON.parse(raw);
    const key = String(parsed?.key ?? 'due_date');
    const direction = parsed?.direction === 'desc' ? 'desc' : 'asc';
    return { key, direction };
  } catch {
    return { key: 'due_date', direction: 'asc' };
  }
}

function saveSortPreference(key, direction) {
  localStorage.setItem(SORT_STORAGE_KEY, JSON.stringify({ key, direction }));
}

const sortPreference = loadSortPreference();

const deadlines = ref([]);
const clients = ref([]);
const loading = ref(true);
const saving = ref(false);
const saveError = ref('');
const search = ref('');
const selectedClientId = ref('');
const sortKey = ref(sortPreference.key);
const sortDirection = ref(sortPreference.direction);

const modalState = reactive({ open: false, isNew: true, id: null });

const form = reactive(emptyForm());
const formErrors = reactive({
  client_id: '',
  due_date: '',
  item_type: '',
  description: '',
});

const sortLabels = {
  due_date: 'Scadenza',
  company_name: 'Cliente',
  item_type: 'Tipo',
  description: 'Descrizione',
  linked_to: 'Collegato a',
  avada_version: 'Avada',
  php_version: 'PHP',
  mysql_version: 'MySQL',
  wp_version: 'WP',
  test_email: 'Test email',
  notes: 'Note',
  amount: 'Importo',
};

const activeSortLabel = computed(() => {
  const label = sortLabels[sortKey.value] ?? sortKey.value;
  const direction = sortDirection.value === 'asc' ? '↑ crescente' : '↓ decrescente';
  return `${label} (${direction})`;
});

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  const rows = deadlines.value.filter((d) => {
    if (selectedClientId.value && String(d.client_id) !== selectedClientId.value) return false;
    if (!q) return true;

    return [
      d.item_type,
      d.description,
      d.linked_to,
      d.notes,
      d.company_name,
      d.php_version,
      d.mysql_version,
      d.wp_version,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
      .includes(q);
  });

  return rows;
});

function toggleSort(key) {
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    saveSortPreference(sortKey.value, sortDirection.value);
    return;
  }

  sortKey.value = key;
  sortDirection.value = 'asc';
  saveSortPreference(sortKey.value, sortDirection.value);
}

function sortIndicator(key) {
  if (sortKey.value !== key) return '↕';
  return sortDirection.value === 'asc' ? '↑' : '↓';
}

function emptyForm() {
  return {
    client_id: '',
    due_date: today(),
    item_type: '',
    description: '',
    linked_to: '',
    avada_version: '',
    php_version: '',
    mysql_version: '',
    wp_version: '',
    test_email: '',
    notes: '',
    amount: '',
  };
}

function today() {
  return new Date().toISOString().slice(0, 10);
}

function formatDate(value) {
  if (!value) return '';
  return new Date(value).toLocaleDateString('it-IT', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

function formatAmount(value) {
  return `€ ${Number(value).toLocaleString('it-IT', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function resetForm() {
  Object.assign(form, emptyForm());
  Object.assign(formErrors, { client_id: '', due_date: '', item_type: '', description: '' });
  saveError.value = '';
}

function closeModal() {
  modalState.open = false;
}

function openNew() {
  resetForm();
  modalState.isNew = true;
  modalState.id = null;

  const enpabil = clients.value.find((c) => c.company_name?.toLowerCase() === 'enpabil');
  if (enpabil) {
    form.client_id = String(enpabil.id);
  }

  modalState.open = true;
}

function openEdit(deadline) {
  resetForm();
  modalState.isNew = false;
  modalState.id = deadline.id;

  Object.assign(form, {
    client_id: String(deadline.client_id ?? ''),
    due_date: deadline.due_date?.slice(0, 10) ?? today(),
    item_type: deadline.item_type ?? '',
    description: deadline.description ?? '',
    linked_to: deadline.linked_to ?? '',
    avada_version: deadline.avada_version ?? '',
    php_version: deadline.php_version ?? '',
    mysql_version: deadline.mysql_version ?? '',
    wp_version: deadline.wp_version ?? '',
    test_email: deadline.test_email ?? '',
    notes: deadline.notes ?? '',
    amount: deadline.amount ?? '',
  });

  modalState.open = true;
}

function validate() {
  formErrors.client_id = form.client_id ? '' : 'Campo obbligatorio';
  formErrors.due_date = form.due_date ? '' : 'Campo obbligatorio';
  formErrors.item_type = form.item_type ? '' : 'Campo obbligatorio';
  formErrors.description = form.description ? '' : 'Campo obbligatorio';

  return !formErrors.client_id && !formErrors.due_date && !formErrors.item_type && !formErrors.description;
}

async function load() {
  loading.value = true;
  try {
    const [deadlinesRes, clientsRes] = await Promise.all([
      api.get('/deadlines', {
        params: {
          client_id: selectedClientId.value || undefined,
          sort_by: sortKey.value,
          sort_dir: sortDirection.value,
        },
      }),
      api.get('/clients'),
    ]);
    deadlines.value = deadlinesRes.data;
    clients.value = clientsRes.data;
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!validate()) return;

  saving.value = true;
  saveError.value = '';

  try {
    const payload = {
      ...form,
      client_id: Number(form.client_id),
      amount: form.amount === '' ? null : Number(form.amount),
    };

    if (modalState.isNew) {
      await api.post('/deadlines', payload);
    } else {
      await api.put(`/deadlines/${modalState.id}`, payload);
    }

    await load();
    closeModal();
  } catch (err) {
    saveError.value = err.response?.data?.message ?? 'Errore durante il salvataggio.';
  } finally {
    saving.value = false;
  }
}

async function renewDate(deadline) {
  if (!confirm(`Rinnovare la scadenza di ${deadline.description} portandola a +1 anno?`)) return;

  saving.value = true;
  saveError.value = '';

  try {
    await api.put(`/deadlines/${deadline.id}/renew`);
    await load();
  } catch (err) {
    saveError.value = err.response?.data?.message ?? 'Errore durante il rinnovo della data.';
  } finally {
    saving.value = false;
  }
}

watch([selectedClientId, sortKey, sortDirection], async () => {
  await load();
});

onMounted(load);
</script>

<style scoped>
.toolbar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.search-input,
.client-select {
  min-height: 40px;
  border: 1.5px solid #d1d5db;
  border-radius: 8px;
  padding: 0.5rem 0.75rem;
  background: #fff;
  font-size: 0.9rem;
}

.search-input {
  flex: 1;
  min-width: 250px;
}

.client-select {
  min-width: 220px;
}

.sort-badge {
  margin-left: auto;
  border-radius: 9999px;
  padding: 0.35rem 0.75rem;
  font-size: 0.8rem;
  font-weight: 600;
  white-space: nowrap;
}

.sort-badge.asc {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.sort-badge.desc {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.deadlines-table {
  min-width: 1300px;
}

.th-sort {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border: none;
  background: transparent;
  color: inherit;
  font: inherit;
  font-weight: inherit;
  cursor: pointer;
  padding: 0;
}

.sort-indicator {
  font-size: 0.72rem;
  opacity: 0.8;
}

.notes {
  max-width: 260px;
}

.amount {
  white-space: nowrap;
}

.actions {
  white-space: nowrap;
  display: flex;
  gap: 0.25rem;
  align-items: center;
}

.triple-row {
  grid-template-columns: repeat(3, 1fr);
}

@media (max-width: 960px) {
  .sort-badge {
    margin-left: 0;
  }

  .triple-row {
    grid-template-columns: 1fr;
  }
}
</style>
