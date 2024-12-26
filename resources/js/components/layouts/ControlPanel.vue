<template>
    <barcode-handler v-model:camera-modal="cameraBarcodeReaderModal"/>
    <div class="dropdown position-fixed bottom-0 end-0 mb-3 me-3 bd-mode-toggle">
        <button class="btn btn-bd-primary py-2 dropdown-toggle d-flex align-items-center"
                id="bd-theme"
                type="button"
                aria-expanded="false"
                data-bs-toggle="dropdown"
                aria-label="Toggle theme (auto)">
            <svg class="bi my-1 theme-icon-active" width="1em" height="1em"><use href="#circle-half"></use></svg>
            <span class="visually-hidden" id="bd-theme-text">Toggle theme</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="bd-theme-text">
            <li>
                <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="light" aria-pressed="false">
                    <svg class="bi me-2 opacity-50" width="1em" height="1em"><use href="#sun-fill"></use></svg>
                    Light
                    <svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
                </button>
            </li>
            <li>
                <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="dark" aria-pressed="false">
                    <svg class="bi me-2 opacity-50" width="1em" height="1em"><use href="#moon-stars-fill"></use></svg>
                    Dark
                    <svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
                </button>
            </li>
            <li>
                <button type="button" class="dropdown-item d-flex align-items-center active" data-bs-theme-value="auto" aria-pressed="true">
                    <svg class="bi me-2 opacity-50" width="1em" height="1em"><use href="#circle-half"></use></svg>
                    Auto
                    <svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
                </button>
            </li>
        </ul>
    </div>

    <header class="navbar sticky-top bg-dark flex-md-nowrap p-0 shadow" data-bs-theme="dark">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3 fs-6 text-white" href="#">
            <span class="h4">Binyod</span>
            <br/>
            <span class="small">Warehouse Platform</span>
        </a>

        <ul class="navbar-nav flex-row">
            <li class="nav-item text-nowrap">
                <button @click.prevent="$store.dispatch('cameraBarcodeReaderModal', true)" class="nav-link px-3 text-white" type="button">
                    <i class="bi-camera"></i>
                </button>
            </li>
            <li class="nav-item text-nowrap d-md-none">
                <button class="nav-link px-3 text-white" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="bi-list"></i>
                </button>
            </li>
        </ul>
    </header>

    <div class="container-fluid">
        <div class="row">
            <div class="sidebar border border-right col-md-3 col-lg-2 p-0 bg-body-tertiary">
                <div class="offcanvas-md offcanvas-end bg-body-tertiary" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
                    <div class="offcanvas-header">
                        <h5 class="offcanvas-title" id="sidebarMenuLabel">
                            <span class="h4">Binyod</span>
                            <br/>
                            <span class="small">Warehouse Platform</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
                    </div>
                    <div class="offcanvas-body d-md-flex flex-column p-0 pt-lg-3 overflow-y-auto">
                        <ul class="nav flex-column" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu">
                            <li class="nav-item">
                                <router-link :to="{ name: 'control-panel.index' }" class="nav-link d-flex align-items-center gap-2" :class="{ 'active': $route.name === 'control-panel.index' }" aria-current="page">
                                    <i class="bi-upc-scan"></i>
                                    Scan
                                </router-link>
                            </li>
                            <li class="nav-item">
                                <router-link class="nav-link d-flex align-items-center gap-2" :to="{ name: 'control-panel.products.index' }" :class="{ 'active': $route.name === 'control-panel.products.index' }">
                                    <i class="bi-basket2"></i>
                                    Product Catalog
                                </router-link>
                            </li>
                            <li class="nav-item">
                                <router-link :to="{ name: 'control-panel.products.receive' }" class="nav-link d-flex align-items-center gap-2" :class="{ 'active': $route.name === 'control-panel.products.receive' }">
                                    <i class="bi-box-arrow-in-down"></i>
                                    Receive
                                </router-link>
                            </li>
                            <li class="nav-item">
                                <router-link :to="{ name: 'control-panel.products.link-barcode' }" class="nav-link d-flex align-items-center gap-2" :class="{ 'active': $route.name === 'control-panel.products.link-barcode' }">
                                    <i class="bi-link"></i>
                                    Link Barcode
                                </router-link>
                            </li>
                            <li class="nav-item">
                                <router-link :to="{ name: 'control-panel.products.ship' }" class="nav-link d-flex align-items-center gap-2" :class="{ 'active': $route.name === 'control-panel.products.ship' }">
                                    <i class="bi-box-arrow-up"></i>
                                    Ship
                                </router-link>
                            </li>

                        </ul>

                        <hr class="my-3">

                        <ul class="nav flex-column mb-auto" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu">
                            <li class="nav-item">
                                <router-link :to="{ name: 'control-panel.reports' }" class="nav-link d-flex align-items-center gap-2" :class="{ 'active': $route.name === 'control-panel.reports' }">
                                    <i class="bi-file-text"></i>
                                    Reports
                                </router-link>
                            </li>
                            <li class="nav-item">
                                <router-link :to="{ name: 'control-panel.settings' }" class="nav-link d-flex align-items-center gap-2" :class="{ 'active': $route.name === 'control-panel.settings' }">
                                    <i class="bi-gear-wide-connected"></i>
                                    Settings
                                </router-link>
                            </li>
                            <li class="nav-item">
                                <a @click.prevent="$emit('signOut')" class="nav-link d-flex align-items-center gap-2" href="#">
                                    <i class="bi-door-closed"></i>
                                    Sign out
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <router-view/>
            </main>
        </div>
    </div>
</template>

<script>
import BarcodeHandler from "../../components/BarcodeHandler.vue";

export default {
    name: 'ControlPanel',
    components: {BarcodeHandler},

    emits: ['signOut'],

    data() {
        return {
            cameraBarcodeReaderModal: false
        }
    },

    computed: {
        readerModal() {
            return this.$store.getters.cameraBarcodeReaderModal
        },

        user() {
            return this.$store.getters.user
        }
    },

    watch: {
        'readerModal' (val) {
            this.cameraBarcodeReaderModal = val
        },

        'cameraBarcodeReaderModal' (val) {
            this.$store.dispatch('cameraBarcodeReaderModal', val)
        }
    },

    beforeCreate() {
        if (!this.$store.getters.user.isAdmin) {
            setTimeout(() => {
                this.$router.push({
                    name: 'homepage'
                })
            }, 100)
            this.$snotify.error('You don\'t have a permission to view this page!')
        }
    }
}
</script>

<style scoped>

</style>
