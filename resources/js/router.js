import { createRouter, createWebHistory } from "vue-router";

import Scan from "./components/pages/Scan.vue";
import Page404 from "./components/pages/404.vue";
import ProductsIndex from "./components/pages/products/Index.vue";
import ProductsReceive from "./components/pages/products/Receive.vue";
import ProductsLinkBarcode from "./components/pages/products/LinkBarcode.vue";
import ProductsShip from "./components/pages/products/Ship.vue";
import Homepage from "./components/pages/Homepage.vue";
import Login from "./components/pages/Login.vue";
import Settings from "./components/pages/Settings.vue";
import Reports from "./components/pages/reports/Reports.vue";
import Recount from "./components/pages/reports/Recount.vue";
import SellableProducts from "./components/pages/reports/SellableProducts.vue";

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'homepage',
            component: Homepage
        },
        {
            path: '/login',
            name: 'login',
            component: Login
        },
        {
            path: '/control-panel',
            children: [
                {
                    path: '/control-panel',
                    name: 'control-panel.index',
                    component: Scan
                },
                {
                    path: 'settings',
                    name: 'control-panel.settings',
                    component: Settings
                },
                {
                    path: 'reports',
                    name: 'control-panel.reports',
                    component: Reports
                },
                {
                    path: 'reports/recount',
                    name: 'control-panel.reports.recount',
                    component: Recount
                },
                {
                    path: 'reports/sellable',
                    name: 'control-panel.reports.sellable',
                    component: SellableProducts
                },
                {
                    path: 'products',
                    name: 'control-panel.products.index',
                    component: ProductsIndex,
                    meta: {
                        handlesBarcode: true
                    }
                },
                {
                    path: 'receive',
                    name: 'control-panel.products.receive',
                    component: ProductsReceive,
                    meta: {
                        handlesBarcode: true
                    }
                },
                {
                    path: 'link-barcode',
                    name: 'control-panel.products.link-barcode',
                    component: ProductsLinkBarcode,
                    meta: {
                        handlesBarcode: true
                    }
                },
                {
                    path: 'ship',
                    name: 'control-panel.products.ship',
                    component: ProductsShip,
                    meta: {
                        handlesBarcode: true
                    }
                }
            ]
        },

        {
            path: '/:pathMatch(.*)*',
            name: '404',
            component: Page404
        }
    ]
})

export default router
