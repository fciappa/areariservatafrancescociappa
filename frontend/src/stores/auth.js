import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api.js';

export const useAuthStore = defineStore('auth', () => {
  const user  = ref(JSON.parse(localStorage.getItem('user') || 'null'));
  const token = ref(localStorage.getItem('accessToken') || null);

  const isLoggedIn  = computed(() => !!token.value);
  const isAdmin     = computed(() => user.value?.role === 'admin');

  async function login(username, password) {
    const { data } = await api.post('/auth/login', { username, password });
    token.value = data.accessToken;
    user.value  = data.user;
    localStorage.setItem('accessToken',  data.accessToken);
    localStorage.setItem('refreshToken', data.refreshToken);
    localStorage.setItem('user',         JSON.stringify(data.user));
  }

  async function logout() {
    const refreshToken = localStorage.getItem('refreshToken');
    await api.post('/auth/logout', { refreshToken }).catch(() => {});
    token.value = null;
    user.value  = null;
    localStorage.clear();
  }

  return { user, token, isLoggedIn, isAdmin, login, logout };
});
