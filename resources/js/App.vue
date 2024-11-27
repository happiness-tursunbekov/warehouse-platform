<template>
    <div v-if="$store.getters.loading" class="pace-backdrop">
        <div title="loading" class="loader">
            <div class="spinner"></div>
        </div>
    </div>
    <vue-snotify />
    <router-view v-if="$route.name === 'login'" />
    <template v-if="$store.getters.user">
        <control-panel v-if="$route.path.includes('/control-panel')" @signOut="signOut"/>
        <store v-else @signOut="signOut"/>
    </template>
</template>

<script>

import Store from "./components/layouts/Store.vue";
import ControlPanel from "./components/layouts/ControlPanel.vue";

export default {
    components: {ControlPanel, Store},
    beforeCreate() {
        // Add a request interceptor
        axios.interceptors.request.use(
            (config) => {
                // Modify config if needed
                // config.headers['Authorization'] = 'Bearer your_token';

                this.$store.dispatch('setLoading', true)

                return config;
            },
            (error) => {

                this.$store.dispatch('setLoading', true)

                return Promise.reject(error);
            }
        );

        // Add a response interceptor
        axios.interceptors.response.use(
            (response) => {

                this.$store.dispatch('setLoading', false)

                return response;
            },
            (error) => {

                this.$store.dispatch('setLoading', false)

                switch (error.response.status) {
                    case 401:
                        this.$router.push({
                            name: 'login'
                        })
                        break;

                    case 403:
                        this.$router.push({
                            name: 'homepage'
                        })
                        this.$snotify.error(error.response.data.message)
                        break;

                    default:
                        this.$snotify.error(error.response.data.message)
                }

                return Promise.reject(error);
            }
        );

        const token = localStorage.getItem('token')
        if (!token) {
            this.$router.push({
                name: 'login'
            })
        } else {
            axios.defaults.headers['Authorization'] = token
            axios.get('/api/auth/user').then(res1 => {
                this.$store.dispatch('setUser', res1.data)
            })
        }
    },

    methods: {
        signOut() {
            this.$store.dispatch('setUser', null)
            localStorage.removeItem('token')
            delete axios.defaults.headers['Authorization']
            this.$router.push({
                name: 'login'
            })
        }
    }
}
</script>

<style scoped>
.loader {
    display: inline-block;
    position: fixed;
    margin-left: 48%;
    background-color: white;
    border-radius: 50%;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.30);
    padding: 2px;
    margin-top: 13px;
}

.spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    animation: spin 0.5s linear infinite;
}

.pace-backdrop {
    position: fixed;
    left:0;
    right:0;
    top: 0;
    bottom: 0;
    background-color: rgba(255,255,255,0.5);
    z-index: 10000000;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
