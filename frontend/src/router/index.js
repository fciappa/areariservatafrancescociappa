import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const routes = [
  { path: '/login', component: () => import('../pages/LoginPage.vue'), meta: { public: true } },

  {
    path: '/',
    component: () => import('../layouts/DashboardLayout.vue'),
    children: [
      { path: '',              component: () => import('../pages/DashboardPage.vue') },
      { path: 'collaborators',  component: () => import('../pages/CollaboratorsPage.vue'), meta: { adminOnly: true } },
      { path: 'clients',        component: () => import('../pages/ClientsPage.vue'),       meta: { adminOnly: true } },
      { path: 'projects',       component: () => import('../pages/ProjectsPage.vue'),       meta: { adminOnly: true } },
      { path: 'tariffs',        component: () => import('../pages/TariffsPage.vue'),        meta: { adminOnly: true } },
      { path: 'my-hours',       component: () => import('../pages/MyHoursPage.vue'),        meta: { adminOnly: true } },
      { path: 'collab-hours',   component: () => import('../pages/CollabHoursPage.vue'),    meta: { adminOnly: true } },
      { path: 'invoices',              component: () => import('../pages/InvoicesPage.vue'),          meta: { adminOnly: true } },
      { path: 'invoices/new',          component: () => import('../pages/NewInvoicePage.vue'),       meta: { adminOnly: true } },
      { path: 'collab-invoices',       component: () => import('../pages/CollabInvoicesPage.vue'),   meta: { adminOnly: true } },
      { path: 'collab-invoices/new',   component: () => import('../pages/NewCollabInvoicePage.vue'), meta: { adminOnly: true } },
      { path: 'users',component: () => import('../pages/UsersPage.vue'),          meta: { adminOnly: true } },
      { path: 'summary',        component: () => import('../pages/SummaryPage.vue') },
      { path: 'my-invoices',   component: () => import('../pages/MyInvoicesPage.vue') },
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
