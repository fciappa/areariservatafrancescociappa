<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>📎 File caricati</h2>
        <p class="page-sub">Archivio condiviso gestito direttamente dalla cartella del server</p>
      </div>
      <label class="btn-primary" :class="{ disabled: uploading }">
        <input type="file" :disabled="uploading" @change="upload" />
        {{ uploading ? 'Caricamento...' : '+ Carica file' }}
      </label>
    </div>

    <div v-if="error" class="alert-error">{{ error }}</div>

    <div v-if="loading" class="skeleton-list">
      <div v-for="index in 4" :key="index" class="skeleton-row" />
    </div>

    <div v-else-if="!files.length" class="empty-state">
      <span>📁</span>
      <p>Nessun file caricato.</p>
    </div>

    <div v-else class="file-list">
      <div v-for="file in files" :key="file.name" class="file-row">
        <span class="file-icon">📄</span>
        <div class="file-main">
          <strong>{{ file.name }}</strong>
          <span>Caricato il {{ formatDate(file.uploaded_at) }}</span>
        </div>
        <span class="file-size">{{ formatSize(file.size) }}</span>
        <div class="file-actions">
          <button class="btn-icon" title="Scarica file" :disabled="downloading === file.name" @click="download(file)">⬇</button>
          <button class="btn-icon delete" title="Elimina file" :disabled="deleting === file.name" @click="remove(file)">🗑</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../services/api.js';

const files = ref([]);
const loading = ref(true);
const uploading = ref(false);
const downloading = ref('');
const deleting = ref('');
const error = ref('');

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/files');
    files.value = data;
  } catch {
    error.value = 'Impossibile caricare l’elenco dei file.';
  } finally {
    loading.value = false;
  }
}

async function download(file) {
  downloading.value = file.name;
  error.value = '';
  try {
    const { data } = await api.get(`/files/${encodeURIComponent(file.name)}`, { responseType: 'blob' });
    const url = URL.createObjectURL(data);
    const link = document.createElement('a');
    link.href = url;
    link.download = file.name;
    link.click();
    URL.revokeObjectURL(url);
  } catch {
    error.value = 'Download non riuscito.';
  } finally {
    downloading.value = '';
  }
}

async function remove(file) {
  if (!window.confirm(`Eliminare definitivamente il file "${file.name}"?`)) return;

  deleting.value = file.name;
  error.value = '';
  try {
    await api.delete(`/files/${encodeURIComponent(file.name)}`);
    files.value = files.value.filter((currentFile) => currentFile.name !== file.name);
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Eliminazione non riuscita.';
  } finally {
    deleting.value = '';
  }
}

async function upload(event) {
  const [file] = event.target.files;
  if (!file) return;

  uploading.value = true;
  error.value = '';
  const formData = new FormData();
  formData.append('file', file);

  try {
    await api.post('/files', formData);
    await load();
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Caricamento non riuscito.';
  } finally {
    uploading.value = false;
    event.target.value = '';
  }
}

function formatDate(value) {
  return new Date(value).toLocaleString('it-IT', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  });
}

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

onMounted(load);
</script>

<style scoped>
.page { padding: 2rem; max-width: 1000px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; }
h2 { margin: 0; color: #1f2937; font-size: 1.5rem; }
.page-sub { margin: 0.35rem 0 0; color: #6b7280; font-size: 0.9rem; }
.btn-primary { display: inline-flex; align-items: center; cursor: pointer; flex-shrink: 0; background: #2563eb; color: #fff; border: 0; border-radius: 6px; padding: 0.65rem 1rem; font: inherit; font-weight: 600; }
.btn-primary input { display: none; }
.btn-primary.disabled { opacity: 0.65; cursor: wait; }
.alert-error { margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: 6px; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; }
.file-list { border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; background: #fff; }
.file-row { display: flex; align-items: center; gap: 0.9rem; padding: 1rem; border-bottom: 1px solid #e5e7eb; }
.file-row:last-child { border-bottom: 0; }
.file-icon { font-size: 1.25rem; }
.file-main { display: flex; flex: 1; min-width: 0; flex-direction: column; gap: 0.2rem; }
.file-main strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #1f2937; }
.file-main span, .file-size { color: #6b7280; font-size: 0.8rem; }
.file-size { white-space: nowrap; }
.file-actions { display: flex; gap: 0.35rem; }
.btn-icon { width: 2rem; height: 2rem; padding: 0; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; cursor: pointer; font-size: 1rem; }
.btn-icon:hover:not(:disabled) { background: #f3f4f6; }
.btn-icon.delete:hover:not(:disabled) { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
.btn-icon:disabled { cursor: wait; opacity: 0.55; }
.empty-state { padding: 3rem; text-align: center; color: #6b7280; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; }
.empty-state span { font-size: 2rem; }
.skeleton-row { height: 68px; margin-bottom: 0.5rem; border-radius: 6px; background: #e5e7eb; animation: pulse 1.4s ease-in-out infinite; }
@keyframes pulse { 50% { opacity: 0.5; } }
@media (max-width: 640px) { .page { padding: 1.25rem; } .page-header { flex-direction: column; } .btn-primary { width: 100%; justify-content: center; } }
</style>