import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router/index.js';
import './assets/global.css';
import './assets/shared.css';

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.mount('#app');
