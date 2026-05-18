<template>
  <div class="app-layout">
    <!-- Hamburger mobile -->
    <button class="hamburger" :class="{ open: sidebarOpen }" @click="sidebarOpen = !sidebarOpen" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>

    <!-- Overlay mobile -->
    <div v-if="sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false" />

    <!-- Sidebar -->
    <aside class="sidebar" :class="{ open: sidebarOpen }">
      <div class="sidebar-header">
        <span class="sidebar-icon">🔐</span>
        <div>
          <div class="sidebar-title">Area Riservata</div>
          <div class="sidebar-sub">Francesco Ciappa</div>
        </div>
      </div>

      <nav class="sidebar-nav">
        <NavLink to="/" icon="📊" label="Dashboard" @click="sidebarOpen = false" />
        <NavLink to="/summary" icon="📅" label="Riepilogo mensile" @click="sidebarOpen = false" />

        <template v-if="auth.isAdmin">
          <div class="nav-group-label">Gestione</div>
          <NavLink to="/collaborators" icon="👥" label="Collaboratori"      @click="sidebarOpen = false" />
          <NavLink to="/clients"       icon="🏢" label="Clienti"            @click="sidebarOpen = false" />
          <NavLink to="/projects"      icon="📁" label="Progetti"           @click="sidebarOpen = false" />
          <NavLink to="/tariffs"       icon="💰" label="Tariffario"         @click="sidebarOpen = false" />

          <div class="nav-group-label">Ore</div>
          <NavLink to="/my-hours"      icon="⏱️" label="Le mie ore"         @click="sidebarOpen = false" />
          <NavLink to="/collab-hours"  icon="🕐" label="Ore collaboratori"  @click="sidebarOpen = false" />

          <div class="nav-group-label">Fatturazione</div>
          <NavLink to="/invoices"        icon="🧾" label="Fatture clienti"        @click="sidebarOpen = false" />
          <NavLink to="/collab-invoices" icon="📄" label="Fatture collaboratori"  @click="sidebarOpen = false" />
          <NavLink to="/users"         icon="👤" label="Utenti"             @click="sidebarOpen = false" />
        </template>
      </nav>

      <div class="sidebar-footer">
        <div class="user-info">
          <span class="user-avatar">{{ userInitials }}</span>
          <div>
            <div class="user-name">{{ auth.user?.username }}</div>
            <div class="user-role">{{ auth.user?.role === 'admin' ? 'Amministratore' : 'Collaboratore' }}</div>
          </div>
        </div>
        <button class="btn-logout" @click="handleLogout">Esci</button>
      </div>
    </aside>

    <!-- Contenuto -->
    <main class="content">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import NavLink from '../components/NavLink.vue';

const auth        = useAuthStore();
const router      = useRouter();
const sidebarOpen = ref(false);

const userInitials = computed(() => {
  const u = auth.user?.username ?? '?';
  return u.slice(0, 2).toUpperCase();
});

async function handleLogout() {
  await auth.logout();
  router.push('/login');
}
</script>

<style scoped>
/* ── Layout ────────────────────────────────────────────── */
.app-layout {
  display: flex;
  min-height: 100vh;
  background: #f3f4f6;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* ── Sidebar ───────────────────────────────────────────── */
.sidebar {
  width: 260px;
  flex-shrink: 0;
  background: #1a1a2e;
  color: #e5e7eb;
  display: flex;
  flex-direction: column;
  height: 100vh;
  position: sticky;
  top: 0;
  overflow-y: auto;
}

.sidebar-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1.5rem 1.25rem 1.25rem;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}

.sidebar-icon { font-size: 1.75rem; }

.sidebar-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: #f9fafb;
  line-height: 1.2;
}

.sidebar-sub {
  font-size: 0.75rem;
  color: #9ca3af;
}

/* ── Nav ───────────────────────────────────────────────── */
.sidebar-nav {
  flex: 1;
  padding: 1rem 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.nav-group-label {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #6b7280;
  padding: 0.75rem 0.5rem 0.25rem;
}

/* ── Footer ────────────────────────────────────────────── */
.sidebar-footer {
  padding: 1rem 1.25rem;
  border-top: 1px solid rgba(255,255,255,0.08);
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.625rem;
}

.user-avatar {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: #0f3460;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  flex-shrink: 0;
}

.user-name  { font-size: 0.875rem; font-weight: 600; color: #f3f4f6; }
.user-role  { font-size: 0.75rem;  color: #9ca3af; }

.btn-logout {
  background: rgba(239,68,68,0.1);
  border: 1px solid rgba(239,68,68,0.3);
  color: #fca5a5;
  border-radius: 8px;
  padding: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
  width: 100%;
}

.btn-logout:hover {
  background: rgba(239,68,68,0.2);
}

/* ── Content ───────────────────────────────────────────── */
.content {
  flex: 1;
  overflow-y: auto;
  min-width: 0;
}

/* ── Mobile ────────────────────────────────────────────── */
.hamburger {
  display: none;
}

.sidebar-overlay {
  display: none;
}

@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    left: -260px;
    top: 0;
    bottom: 0;
    z-index: 200;
    transition: left 0.25s ease;
  }

  .sidebar.open {
    left: 0;
  }

  .sidebar-overlay {
    display: block;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 199;
  }

  .hamburger {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 5px;
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 201;
    background: #1a1a2e;
    border: none;
    padding: 0.5rem;
    border-radius: 8px;
    cursor: pointer;
    width: 40px;
    height: 40px;
  }

  .hamburger span {
    display: block;
    width: 20px;
    height: 2px;
    background: #fff;
    border-radius: 2px;
    transition: all 0.2s;
  }

  .content {
    padding-top: 3.5rem;
  }
}
</style>

