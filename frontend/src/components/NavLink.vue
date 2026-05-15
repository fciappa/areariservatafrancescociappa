<template>
  <RouterLink
    :to="to"
    class="nav-link"
    :class="{ active: isActive }"
    v-bind="$attrs"
  >
    <span class="nav-icon">{{ icon }}</span>
    <span class="nav-label">{{ label }}</span>
  </RouterLink>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const props = defineProps({
  to:    { type: String, required: true },
  icon:  { type: String, default: '' },
  label: { type: String, required: true },
});

const route   = useRoute();
const isActive = computed(() =>
  props.to === '/' ? route.path === '/' : route.path.startsWith(props.to)
);
</script>

<style scoped>
.nav-link {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  color: #9ca3af;
  transition: background 0.15s, color 0.15s;
  text-decoration: none;
  cursor: pointer;
}

.nav-link:hover {
  background: rgba(255,255,255,0.07);
  color: #f3f4f6;
}

.nav-link.active {
  background: rgba(15, 52, 96, 0.6);
  color: #93c5fd;
  font-weight: 600;
}

.nav-icon  { font-size: 1rem; flex-shrink: 0; }
.nav-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
