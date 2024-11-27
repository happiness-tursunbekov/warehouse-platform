<template>
    <modal v-if="product" v-model:show="modal" :modal-title="'Linking a barcode'">
        <div class="mb-2">
            <div class="w-100"><strong>Product ID: </strong>{{ product.identifier }}</div>
            <span v-if="barcode">Barcode: <strong>{{ barcode }}</strong></span>
            <div v-else>
                <i class="bi-upc-scan"></i> Scan the barcode to link
            </div>
        </div>
        <div class="w-100 d-flex justify-content-between">
            <button @click.prevent="link" :disabled="!barcode" type="button" class="btn btn-success">Link it!</button>
            <button @click.prevent="$store.dispatch('cameraBarcodeReaderModal', true)" type="button" class="btn btn-light"><i class="bi-camera"></i></button>
        </div>
    </modal>
    <modal v-else v-model:show="modal" :modal-title="'Linking the barcode: ' + barcode">
        <form @submit.prevent="searchItem">
            <div class="mb-3">
                <label for="barcodeLinkItem" class="form-label">Product ID</label>
                <input v-model="identifier" type="text" class="form-control" id="barcodeLinkItem" placeholder="Product ID">
            </div>
            <div class="mb-3">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>

        <form @submit.prevent="link">
            <table v-if="items.length > 0" class="table table-striped">
                <thead>
                <tr>
                    <th scope="col">Select</th>
                    <th scope="col">Product ID</th>
                    <th scope="col">Description</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(item, key) in items" :key="key">
                    <td><input v-model="selectedItems" type="checkbox" :value="item.id" :required="selectedItems.length === 0"></td>
                    <th scope="row">{{ item.identifier }}</th>
                    <td>{{ item.description }}</td>
                </tr>
                </tbody>
            </table>
            <div class="mb-3">
                <button type="submit" class="btn btn-success">Link</button>
            </div>
        </form>
    </modal>
</template>

<script>
import Modal from "./Modal.vue";

export default {
    name: "BarcodeLinkModal",
    components: {Modal},

    props: {
        barcode: String,
        show: Boolean,
        product: Object
    },

    data() {
        return {
            modal: false,
            identifier: '',
            items: [],
            selectedItems: []
        }
    },

    watch: {
        'modal' (val) {
            this.$emit('update:show', val)
        },

        'show' (val) {
            this.modal = val
            if (this.product) {
                this.$store.dispatch('setBarcode', '')
            }
        }
    },

    created() {
        this.modal = this.show
    },

    methods: {
        searchItem() {
            axios.get('/api/products', {
                params: {
                    identifier: this.identifier
                }
            }).then(res => {
                this.items = res.data.products

                if (res.data.meta.total === 0)
                    this.$snotify.error('There is no product with Product ID: ' + this.identifier)
            })
        },
        link() {
            const items = this.product ? [this.product] : this.items.filter(item => this.selectedItems.includes(item.id));
            axios.post('/api/products/add-barcode', {
                productIds: items.map(item => item.id),
                barcode: this.barcode
            }).then(() => {
                this.$emit('handled', items)
                this.$snotify.success('Barcode linked successfully!')
                this.modal = false
                this.identifier = ''
            })
        }
    }
}
</script>

<style scoped>

</style>
