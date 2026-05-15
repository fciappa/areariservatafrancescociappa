import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const routes = [
  { path: '/login', component: () => import('../pages/LoginPage.vue'), meta: { public: true } },

  {
    path: '/',
    component: () => import('../layouts/DashboardLayout.vue'),
    children: [
      { path: '',              component: () => import('../pages/DashboardPage.vue') },
      { path: 'collaboratori',     component: () => import('../pages/CollaboratorsPage.vue'), meta: { adminOnly: true } },
      { path: 'clienti',           component: () => import('../pages/ClientsPage.vue'),       meta: { adminOnly: true } },
      { path: 'progetti',          component: () => import('../pages/ProjectsPage.vue'),       meta: { adminOnly: true } },
      { path: 'tariffario',        component: () => import('../pages/TariffsPage.vue'),        meta: { adminOnly: true } },
      { path: 'ore-mie',       component: () => import('../pages/MyHoursPage.vue'),        meta: { adminOnly: true } },
      { path: 'ore-collaboratori', component: () => import('../pages/CollabHoursPage.vue'),meta: { adminOnly: true } },
      { path: 'fatture',       component: () => import('../pages/InvoicesPage.vue'),       meta: { adminOnly: true } },
      { path: 'fatture/nuova', component: () => import('../pages/NewInvoicePage.vue'),     meta: { adminOnly: true } },
      { path: 'utenti',        component: () => import('../pages/UsersPage.vue'),          meta: { adminOnly: true } },
      { path: 'riepilogo',     component: () => import('../pages/SummaryPage.vue') },
    ],
  },

  { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to) => {
  const auth = useAuthStore();
  if (!to.meta.public && !auth.isLoggedIn) return '/login';
  if (to.meta.adminOnly && !auth.isAdmin)  return '/';
});

export default router;
