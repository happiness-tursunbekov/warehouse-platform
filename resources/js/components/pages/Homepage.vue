<template>
    <div class="row">
        <div class="col-12 d-sm-flex ps-0">
            <form class="d-inline-block w-100" @submit.prevent="getProducts(true)">
                <div class="input-group mb-4">
                    <select @change="getProducts(true)" v-model="filterForm.type" class="form-select-sm">
                        <option :value="''">All</option>
                        <option :value="'NEW'">New</option>
                        <option :value="'USED'">Used</option>
                    </select>
                    <input v-model="filterForm.query" class="form-control" type="text" placeholder="Search for..." aria-label="Search for..." aria-describedby="btnNavbarSearch">
                    <button class="btn btn-primary" id="btnNavbarSearch" type="submit">
                        <i class="bi-search"></i> <span class="d-sm-inline-block d-none">Search</span>
                    </button>
                </div>
            </form>
            <div class="m-1">
                <pagination :meta="meta" @change="handlePagination"/>
                <button class="btn btn-light px-3 float-end d-inline-block d-md-none btn-sm mt-sm-1 mb-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="bi-funnel"></i> Filter
                </button>
            </div>
        </div>
        <div class="sidebar border-right col-md-3 col-lg-2 p-0 bg-body-tertiary">
            <div class="offcanvas-md offcanvas-end bg-body-tertiary" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
                <div class="offcanvas-header">
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body d-md-flex flex-column p-0 pt-sm-3 overflow-y-auto">

                    <div class="w-100 ps-2">
                        <i class="bi-funnel"></i> Filter
                    </div>

                    <hr class="my-3">

                    <ul class="list-unstyled ps-0">
                        <li class="nav-item">
                            <a @click.prevent="resetFilterForm" :class="{ active: !filterForm.categoryId }" class="nav-link d-flex align-items-center gap-2" href="#">
                                All
                            </a>
                            <hr class="m-0">
                        </li>
                        <li v-for="(category, key) in options.categories" :key="key">
                            <div class="d-flex w-100 justify-content-between">
                                <a @click.prevent="() => { filterForm.categoryId = category.id;filterForm.subcategoryId = ''; getProducts(true) }" :class="{ active: category.id === filterForm.categoryId }" class="nav-link d-flex align-items-center gap-2 w-100" href="#">
                                    {{ category.name }}
                                </a>
                                <button v-if="category.subcategories.length > 0" class="btn btn-dark btn-toggle align-items-center rounded collapsed" data-bs-toggle="collapse" :data-bs-target="'#cat-collapse' + key" aria-expanded="false"></button>
                            </div>
                            <div class="collapse" :id="'cat-collapse' + key" style="">
                                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
                                    <li v-for="(subcategory, key) in category.subcategories" :key="key">
                                        <a @click.prevent="() => { filterForm.subcategoryId = subcategory.id; filterForm.categoryId = subcategory.category.id; getProducts(true) }" :class="{ active: subcategory.id === filterForm.subcategoryId }" href="#" class="link-dark rounded">{{ subcategory.name }}</a>
                                    </li>
                                </ul>
                            </div>
                            <hr class="m-0">
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="row gx-4 gx-lg-5 row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-3 row-cols-xxl-4 row-cols-sm-2 justify-content-center">
                <div v-for="(product, key) in products" :key="key" class="col mb-5">
                    <div class="card h-100">
                        <!-- Product image-->
                        <div v-viewer>
                            <template v-if="product.wpDetails.files.length > 0">
                                <img v-for="(file,key) in product.wpDetails.files" :key="key" :class="{ 'd-none': key > 0 }" class="card-img-top" :src="file.path" alt="..." />
                            </template>
                            <img v-else class="card-img-top" src="https://dummyimage.com/450x300/dee2e6/6c757d.jpg" alt="..." />
                        </div>
                        <!-- Product details-->
                        <div class="card-body p-4">
                            <div class="text-center">
                                <!-- Product name-->
                                <h5 class="fw-bolder">{{ product.identifier }}</h5>
                                <small class="text-muted w-100 d-inline-block">{{ product.description }}</small>
                                <!-- Product price-->
                                <div class="text-success">${{ product.cost }}</div>
                                <div class="text-primary">In Stock: <strong>{{ product.wpDetails.onHandAvailable }}</strong>{{ product.unitOfMeasure.name }}</div>
                            </div>
                        </div>
                        <!-- Product actions-->
                        <div :class="{ 'd-none': product.wpDetails.onHandAvailable < 1 }" class="card-footer p-4 pt-0 border-top-0 bg-transparent">
                            <form @submit.prevent="addToCart(product, key)" class="text-center" :key="key">
                                <div class="input-group">
                                    <input ref="item" type="number" min="1" class="form-control" required value="1">
                                    <span class="input-group-text">{{ product.unitOfMeasure.name }}</span>
                                    <button type="submit" class="btn btn-success" title="Add To Cart"><i class="bi-cart-plus"></i></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Pagination from "../Pagination.vue";

export default {
    name: "Homepage",
    components: {Pagination},

    data() {
        return {
            options: {
                categories: []
            },
            filterForm: {
                categoryId: parseInt(this.$route.query.categoryId) || '',
                subcategoryId: parseInt(this.$route.query.subcategoryId) || '',
                query: this.$route.query.query || '',
                page: this.$route.query.page || 1,
                type: this.$route.query.type || ''
            },
            products: [],
            meta: {
                total: 0,
                currentPage: 1,
                perPage: 25,
                totalPages: 0
            }
        }
    },

    computed: {},

    created() {
        this.getOptions()
        this.getProducts()
    },

    methods: {
        addToCart(product, key) {
            const qty = parseInt(this.$refs.item[key].value)

            if (product.onHandAvailable < qty) {
                this.$snotify.error('Quantity cannot exceed stock quantity!')
            } else {
                this.$store.dispatch('addItemToCart', {
                    product: product,
                    qty: qty
                })
                this.$snotify.success('Product added to cart successfully!')
            }
        },

        getOptions() {
            axios.options('/api/store/products').then(res => {
                this.options.categories = res.data.categories
            })
        },

        getProducts(resetPage) {
            if (resetPage) {
                this.filterForm.page = 1
            }

            this.$router.push({
                query: this.filterForm
            })

            axios.get('/api/store/products', {
                params: this.filterForm
            }).then(res => {
                this.products = res.data.products
                this.meta = res.data.meta
            })
        },

        handlePagination(page) {
            this.filterForm.page = page
            this.getProducts()
        },

        resetFilterForm() {
            this.filterForm.page = 1
            this.filterForm.categoryId = ''
            this.filterForm.subcategoryId = ''
            this.filterForm.query = ''
            this.getProducts(true)
        }
    }
}
</script>

<style scoped>
.nav-link {
    border-radius: 0;
    padding-left: 15px;
    min-height: 30px;
}
.nav-link:hover {
    background-color: #1a202c;
    color: #ffffff;
}

.btn-toggle {
    display: inline-flex;
    align-items: center;
    padding: .25rem .5rem;
    font-weight: 600;
    color: rgba(0, 0, 0, .65);
    background-color: transparent;
    border: 0;
}
.btn-toggle:hover,
.btn-toggle:focus {
    color: rgba(0, 0, 0, .85);
    background-color: #d2f4ea;
}

.btn-toggle::before {
    width: 1.25em;
    line-height: 0;
    content: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='rgba%280,0,0,.5%29' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 14l6-6-6-6'/%3e%3c/svg%3e");
    transition: transform .35s ease;
    transform-origin: .5em 50%;
}

.btn-toggle[aria-expanded="true"] {
    color: rgba(0, 0, 0, .85);
}
.btn-toggle[aria-expanded="true"]::before {
    transform: rotate(90deg);
}

.btn-toggle-nav a {
    display: inline-flex;
    padding: .1875rem .5rem;
    margin-top: .125rem;
    margin-left: 1.25rem;
    text-decoration: none;
}
.btn-toggle-nav a:hover,
.btn-toggle-nav a:focus {
    background-color: #d2f4ea;
}

.btn-toggle-nav a.active {
    background-color: #1a202c;
    color: #ffffff !important;
}

</style>
