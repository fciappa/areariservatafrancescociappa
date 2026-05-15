<template>
  <div class="kpi-card" :class="color">
    <div class="kpi-top">
      <span class="kpi-icon">{{ icon }}</span>
      <span v-if="loading" class="kpi-skeleton" />
      <span v-else class="kpi-value">{{ value }}</span>
    </div>
    <div class="kpi-label">{{ label }}</div>
    <div class="kpi-sub">{{ sub }}</div>
  </div>
</template>

<script setup>
defineProps({
  icon:    { type: String,  default: '' },
  label:   { type: String,  required: true },
  value:   { type: [String, Number], default: '—' },
  sub:     { type: String,  default: '' },
  color:   { type: String,  default: 'blue' },
  loading: { type: Boolean, default: false },
});
</script>

<style scoped>
.kpi-card {
  background: #fff;
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  border-left: 4px solid #d1d5db;
  transition: transform 0.15s;
}

.kpi-card:hover { transform: translateY(-2px); }

.kpi-card.green  { border-color: #10b981; }
.kpi-card.blue   { border-color: #3b82f6; }
.kpi-card.purple { border-color: #8b5cf6; }
.kpi-card.orange { border-color: #f59e0b; }

.kpi-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.kpi-icon  { font-size: 1.5rem; }

.kpi-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
  font-family: 'Courier New', monospace;
}

.kpi-skeleton {
  display: block;
  width: 80px;
  height: 1.5rem;
  border-radius: 6px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}

@keyframes shimmer { to { background-position: -200% 0; } }

.kpi-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: #374151;
}

.kpi-sub {
  font-size: 0.75rem;
  color: #9ca3af;
  margin-top: 0.125rem;
}
</style>
