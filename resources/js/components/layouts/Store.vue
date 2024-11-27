<template>
    <!-- Navigation-->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container px-4 px-lg-5">
            <router-link class="navbar-brand" :to="{ name: 'homepage' }">Binyod Store</router-link>
            <div>
                <div class="btn-group">
                    <button class="btn btn-outline-light me-1 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi-person me-1"></i>
                        <span class="d-sm-inline-block d-none">{{ user.name }}</span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><h6 class="dropdown-header">Welcome {{ user.name }}!</h6></li>
                        <li v-if="user.isAdmin"><router-link class="dropdown-item" :to="{ name: 'control-panel.index' }"><i class="bi-display"></i> Control Panel</router-link></li>
                        <li><a @click.prevent="ordersModal = true" class="dropdown-item" href="#"><i class="bi-cart-check"></i> My Orders</a></li>
                        <li><a @click.prevent="$emit('signOut')" class="dropdown-item" href="#"><i class="bi-door-closed"></i> Sign Out</a></li>
                    </ul>
                </div>
                <button @click.prevent="cartModal = true" class="btn btn-outline-light" type="button">
                    <i class="bi-cart-fill me-1"></i>
                    <span class="d-sm-inline-block d-none">Cart</span>
                    <span class="badge bg-dark text-white ms-1 rounded-pill">{{ cart.total.qty }}</span>
                </button>
            </div>
        </div>
    </nav>
    <!-- Section-->
    <section class="py-4">
        <div class="container px-4 px-lg-5">
            <router-view/>
        </div>
    </section>
    <!-- Footer-->
    <footer class="py-5 bg-dark">
        <div class="container"><p class="m-0 text-center text-white">Copyright &copy; Binyod Store 2024</p></div>
    </footer>
    <modal v-model:show="cartModal">
        <div class="row g-0">
            <div class="col-lg-8">
                <div class="p-5">
                    <div class="d-flex justify-content-between align-items-center mb-5">
                        <h1 class="fw-bold mb-0">Shopping Cart</h1>
                        <h6 class="mb-0 text-muted">{{ cart.total.qty }} items</h6>
                    </div>
                    <template v-for="(item, key) in cart.items" :key="key">
                        <hr class="my-4">

                        <div class="row mb-4 d-flex justify-content-between align-items-center">
                            <div class="col-md-2 col-lg-2 col-xl-2">
                                <img src="https://dummyimage.com/450x300/dee2e6/6c757d.jpg" class="img-fluid rounded-3" alt="Cotton T-shirt">
                            </div>
                            <div class="col-md-3 col-lg-3 col-xl-3">
                                <h6 class="text-muted">{{ item.product.identifier }}</h6>
                                <h6 class="mb-0">{{ item.product.description }}</h6>
                                <small>In Stock: {{ item.product.wpDetails.onHandAvailable }}</small>
                            </div>
                            <div class="col-md-3 col-lg-3 col-xl-3 d-flex">
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-primary" @click.prevent="updateQty(item, item.qty + 1)">+</button>
                                    <input id="form1" min="1" name="quantity" :value="item.qty" @change="updateQty(item, $event.target.value)" type="number" class="form-control form-control-sm">
                                    <span class="input-group-text">{{ item.product.unitOfMeasure.name }}</span>
                                    <button type="button" class="btn btn-outline-primary" @click.prevent="updateQty(item, item.qty - 1)">-</button>
                                </div>
                            </div>
                            <div class="col-md-2 col-lg-1 col-xl-1 offset-lg-1">
                                <h6 class="mb-0">$ {{ roundPrice(item.qty * item.product.cost) }}</h6>
                            </div>
                            <div class="col-md-1 col-lg-1 col-xl-1 text-end">
                                <a @click.prevent="$store.dispatch('removeItemFromCart', item.product.id)" href="#" style="color: #cecece;"><i class="bi-trash"></i></a>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div  class="col-lg-4 bg-body-tertiary">
                <form @submit.prevent="placeOrder" class="p-5">
                    <h3 class="fw-bold mb-5 mt-2 pt-1">Summary</h3>
                    <hr class="my-4">

                    <div class="d-flex justify-content-between mb-4">
                        <h5 class="text-uppercase">items {{ cart.total.qty }}</h5>
                        <h5>$ {{ cart.total.price }}</h5>
                    </div>

                    <h5 class="text-uppercase mb-3">Project</h5>

                    <div class="mb-4 pb-2">
                        <v-select
                            :options="cartOptions.projects"
                            v-model="cartForm.projectId"
                            :get-option-label="(option) => '#' + option.id + ' - ' + option.name + ' - ' + option.company.name"
                            placeholder="select"
                            required
                            :reduce="option => option.id"
                        />
                    </div>

                    <h5 class="text-uppercase mb-3">Team</h5>

                    <div class="mb-5">
                        <v-select
                            :options="cartOptions.teams"
                            v-model="cartForm.teamId"
                            label="name"
                            placeholder="select"
                            required
                            :reduce="option => option.id"
                        />
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between mb-5">
                        <h5 class="text-uppercase">Total price</h5>
                        <h5>$ {{ cart.total.price }}</h5>
                    </div>

                    <button v-if="cart.total.qty > 0" type="submit" data-mdb-button-init="" data-mdb-ripple-init="" class="btn btn-dark btn-block btn-lg" data-mdb-ripple-color="dark" data-mdb-button-initialized="true">Checkout</button>

                </form>
            </div>
        </div>
    </modal>
    <modal v-model:show="ordersModal" modal-title="My orders">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>#</th>
                </tr>
                </thead>
            </table>
        </div>
    </modal>
</template>

<script>
import Modal from "../Modal.vue";
import VSelect from "../v-select/components/VSelect.vue";

export default {
    name: "Store",
    components: {VSelect, Modal},

    emits: ['signOut'],

    data() {
        return {
            cartModal: false,
            ordersModal: false,
            cartOptions: {
                projects: [],
                teams: []
            },
            cartForm: {
                projectId: '',
                teamId: 11
            }
        }
    },

    computed: {
        cart() {
            return this.$store.getters.cart
        },

        user() {
            return this.$store.getters.user
        }
    },

    watch: {
        'cartModal' (val) {
            if (val && this.cartOptions.projects.length === 0) {
                this.fetchOptions()
            }
        }
    },

    methods: {
        updateQty(item, qty) {
            qty = parseInt(qty)

            if (qty === 0) {
                qty = 1
            } else if (qty < 1) {
                qty = qty * (-1)
            }

            if (qty > item.product.wpDetails.onHandAvailable) {
                qty = item.product.wpDetails.onHandAvailable
            }

            this.$store.dispatch('addItemToCart', {
                product: item.product,
                qty: qty - item.qty
            })
        },

        roundPrice(num) {
            return Math.round((num + Number.EPSILON) * 100) / 100
        },

        fetchOptions() {
            axios.get('/api/store/orders/create').then(res => {
                this.cartOptions.projects = res.data.projects
                this.cartOptions.teams = res.data.teams
            })
        },

        placeOrder() {
            const data = {
                projectId: this.cartForm.projectId,
                teamId: this.cartForm.teamId,
                totalCost: this.cart.total.price,
                items: this.cart.items.map(item => ({
                    productId: item.product.id,
                    quantity: item.qty,
                    cost: this.roundPrice(item.qty * item.product.cost)
                }))
            }

            axios.post('/api/store/orders', data).then(res => {
                this.$snotify.success(`Your order(#${res.data.id}) placed successfully! You can see its status on "My orders" section.`)
                this.cartModal = false
                this.$store.dispatch('resetCart')
            })
        }
    }
}
</script>

<style scoped>

</style>
