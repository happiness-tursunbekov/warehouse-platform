import './bootstrap';

import snotify from 'vue3-snotify';
import 'vue3-snotify/style'; // Base styles
import 'vue3-snotify/theme/material';
import 'viewerjs/dist/viewer.css'
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap-icons/font/bootstrap-icons.css'
import 'bootstrap'
import VueViewer from 'v-viewer'

import { createApp } from 'vue';

import router from './router.js';
import store from "./store.js";

import App from "./App.vue";

const app = createApp(App)
    .use(router)
    .use(store)
    .use(snotify)
    .use(VueViewer);

app.provide('snotify', app.config.globalProperties.$snotify)

app.mount('#app');
