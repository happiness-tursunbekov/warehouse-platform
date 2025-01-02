<template>
    <barcode-handler v-model:camera-modal="cameraBarcodeReaderModal"/>
    <text-reader v-model:camera-modal="textReaderModal"/>
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
            <li class="nav-item text-nowrap">
                <button @click.prevent="$store.dispatch('textReaderModal', true)" class="nav-link px-3 text-white" type="button">
                    <i class="bi-card-text"></i>
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

                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-body-secondary text-uppercase">
                            <span>Reports</span>
                            <a class="link-secondary" href="#" aria-label="Add a new report">
                                <svg class="bi"><use xlink:href="#plus-circle"></use></svg>
                            </a>
                        </h6>

                        <ul class="nav flex-column mb-auto" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu">
                            <li class="nav-item">
                                <router-link :to="{ name: 'control-panel.reports' }" class="nav-link d-flex align-items-center gap-2" :class="{ 'active': $route.name === 'control-panel.reports' }">
                                    <i class="bi-file-text"></i>
                                    Shipment/Returns
                                </router-link>
                            </li>
                            <li class="nav-item">
                                <router-link :to="{ name: 'control-panel.reports.recount' }" class="nav-link d-flex align-items-center gap-2" :class="{ 'active': $route.name === 'control-panel.reports.recount' }">
                                    <i class="bi-file-text"></i>
                                    Recounting
                                </router-link>
                            </li>
                        </ul>

                        <hr class="my-3">

                        <ul class="nav flex-column mb-auto" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu">
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
import TextReader from "../TextReader.vue";

export default {
    name: 'ControlPanel',
    components: {TextReader, BarcodeHandler},

    emits: ['signOut'],

    data() {
        return {
            cameraBarcodeReaderModal: false,
            textReaderModal: false
        }
    },

    computed: {
        readerModal() {
            return this.$store.getters.cameraBarcodeReaderModal
        },

        tReaderModal() {
            return this.$store.getters.textReader.modal
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
        },

        'textReaderModal' (val) {
            this.$store.dispatch('textReaderModal', val)
        },

        'tReaderModal' (val) {
            this.textReaderModal = val
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
