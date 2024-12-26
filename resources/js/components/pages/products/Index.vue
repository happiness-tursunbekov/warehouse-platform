<template>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Product Catalog</h1>
    </div>
    <div class="position-relative">
        <form @submit.prevent="getProducts(true)">
            <div class="table-responsive min-vh-100">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th scope="col">Product ID</th>
                        <th scope="col">Barcode</th>
                        <th scope="col">Description</th>
                        <th scope="col">Category</th>
                        <th scope="col" class="text-nowrap">On Hand</th>
                        <th scope="col">Photos</th>
                        <th scope="col">Price</th>
                        <th scope="col">Cost</th>
                        <th scope="col">Action</th>
                    </tr>
                    <tr>
                        <th scope="col"><input v-model="filter.identifier" type="text" class="form-control"/></th>
                        <th scope="col"><input v-model="filter.barcode" type="text" class="form-control"/></th>
                        <th scope="col"><input v-model="filter.description" type="text" class="form-control"/></th>
                        <th scope="col"><button type="submit" class="btn btn-light">🔎</button></th>
                        <th></th>
                        <th>
                            <input id="loadPhotosAutomatically" type="checkbox" v-model="loadPhotosAutomatically" /><label for="loadPhotosAutomatically">Auto load</label>
                        </th>
                        <th></th>
                        <th></th>
                        <th scope="col"><pagination :meta="meta" @change="handlePagination"/></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(product, key) in catalogItems" :key="key">
                        <th scope="row">{{ product.identifier }}</th>
                        <td>
                            <div>{{ product.barcodes.join("\n") }}</div>
                            <button @click.prevent="showBarcodeLinkModal(product)" type="button" class="btn btn btn-secondary btn-sm">Link a barcode</button>
                        </td>
                        <td>{{ product.description }}</td>
                        <td>{{ product.category.name }}</td>
                        <td>
                            {{ product.onHand }}
                        </td>
                        <td>
                            <button v-if="typeof productImages[product.id] === ('undefined' || null)" class="btn btn-success btn-sm" type="button" @click.prevent="getProductImages(product)">Load</button>
                            <div v-else v-viewer style="cursor: pointer">
                                <img v-for="(file,key) in productImages[product.id]" :key="key" :class="{ 'd-none': key > 0 }" style="height: 30px" :src="`/api/products/image/${file.id}/${file.fileName}`" alt="..." />
                            </div>
                        </td>
                        <td>{{ product.price }}</td>
                        <td>{{ product.cost }}</td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi-three-dots-vertical"></i>
                                </button>
                                <div class="dropdown-menu">
                                <div class="list-group">
                                    <button @click.prevent="getPos(product)" type="button" class="list-group-item">Get PO's/Ship</button>
                                    <button @click.prevent="selectedProduct=product;getProductOnHand(product);adjustItemModal=true" type="button" class="list-group-item" title="Adjust">Adjust quantity</button>
                                    <button @click.prevent="showUploadPhotoModal(product)" type="button" class="list-group-item" title="Upload a photo">Upload a photo</button>
                                    <button v-if="!product.identifier.includes('-used')" @click.prevent="selectedProduct=product;usedItemModal=true" type="button" class="list-group-item" title="Add used product">Add used product</button>
                                </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </form>
        <modal v-model:show="posModal" :modal-title="'Po\'s/Shipment - ' + (selectedProduct ? selectedProduct.identifier : '')">
            <strong>On hand:</strong> {{ selectedProduct.onHand }}
            <hr/>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead class="sticky-top">
                    <tr>
                        <th>PO Number</th>
                        <th>Product Status</th>
                        <th>Quantity</th>
                        <th>Received Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(po, key) in pos" :key="key">
                        <td>{{ po.poNumber }}</td>
                        <td>
                            <span v-if="po.canceledFlag">Cancelled</span>
                            <span v-else>{{ po.receivedStatus }}</span>
                        </td>
                        <td>{{ po.quantity }}</td>
                        <td>{{ po.dateReceived }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <hr/>
            <h6 class="h6">Projects</h6>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead class="sticky-top">
                    <tr>
                        <th>Project</th>
                        <th>Company</th>
                        <th>Phase</th>
                        <th>Quantity</th>
                        <th>Shipped Quantity</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(product, key) in products" :key="key">
                        <td><span v-if="product.project">#{{ product.project.id }} - {{ product.project.name }}</span></td>
                        <td>{{ product.company.name }}</td>
                        <td>{{ product.phase ? product.phase.name : '' }}</td>
                        <td>{{ product.quantity }}</td>
                        <td>{{ product.shippedQuantity }}</td>
                        <td>
                            <div class="d-flex justify-content-between">
                                <form @submit.prevent="ship(product, $refs.shipQty[key].value)" class="input-group" :class="{ 'd-none': product.quantity === product.shippedQuantity }">
                                    <input required :value="product.quantity - product.shippedQuantity" type="number" min="1" :max="product.quantity - product.shippedQuantity" ref="shipQty" class="form-control" style="max-width: 80px"/>
                                    <button type="submit" class="btn btn-success">Ship</button>
                                </form>
                                <form @submit.prevent="unship(product, $refs.unshipQty[key].value)" class="input-group" :class="{ 'd-none': product.shippedQuantity === 0 }">
                                    <input required :value="0" type="number" min="1" :max="product.shippedQuantity" ref="unshipQty" class="form-control"  style="max-width: 80px"/>
                                    <button type="submit" class="btn btn-danger">Return</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </modal>
        <barcode-link-modal @handled="getProducts()" v-model:show="barcodeLinkModal" :barcode="barcode" :product="selectedProduct"/>
        <modal v-model:show="usedItemModal" modal-title="Adding used catalog item">
            <form @submit.prevent="createUsedItem($refs.usedItemQty.value)">
                <ul class="list-group">
                    <li class="list-group-item"><strong>Product ID:</strong> {{ selectedProduct.identifier }}</li>
                    <li class="list-group-item"><strong>Description:</strong> {{ selectedProduct.description }}</li>
                </ul>
                <div class="mb-3">
                    <label class="form-label">Quantity</label>
                    <div class="input-group">
                        <input ref="usedItemQty" type="number" min="1" class="form-control" required>
                        <span class="input-group-text">{{ selectedProduct.unitOfMeasure.name }}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </modal>
        <modal v-model:show="adjustItemModal" modal-title="Adjusting catalog item">
            <form @submit.prevent="adjustItem($refs.adjustItemQty.value)">
                <ul class="list-group">
                    <li class="list-group-item"><strong>Product ID:</strong> {{ selectedProduct.identifier }}</li>
                    <li class="list-group-item"><strong>Description:</strong> {{ selectedProduct.description }}</li>
                    <li class="list-group-item"><strong>On hand:</strong> {{ selectedProduct.onHand }}</li>
                </ul>
                <div class="mb-3">
                    <label class="form-label">Quantity</label>
                    <div class="input-group">
                        <input ref="adjustItemQty" type="number" class="form-control" required>
                        <span class="input-group-text">{{ selectedProduct.unitOfMeasure.name }}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </modal>
        <file-upload-modal @upload="uploadPhotos" v-model:show="photoModal" :accept="['image/*']" modal-title="Manage product photos" multiple/>
    </div>
</template>

<script>
import Pagination from "../../Pagination.vue";
import Modal from "../../Modal.vue";
import BarcodeLinkModal from "../../BarcodeLinkModal.vue";
import FileUploadModal from "../../FileUploadModal.vue";

export default {
    name: "Index",
    components: {FileUploadModal, BarcodeLinkModal, Modal, Pagination},

    data() {
        return {
            catalogItems: [],
            productImages: {},
            meta: {
                total: 0,
                currentPage: 1,
                perPage: 25,
                totalPages: 0
            },
            filter: {
                page: this.$route.query.page || 1,
                identifier: this.$route.query.identifier || '',
                description: this.$route.query.description || '',
                barcode: this.$route.query.barcode || ''
            },
            pos: [],
            products: [],
            posModal: false,
            barcodeLinkModal: false,
            selectedProduct: null,
            photoModal: false,
            productForm: {
                rmPhotos: [],
                upPhotos: [],
            },
            loadPhotosAutomatically: false,
            usedItemModal: false,
            adjustItemModal: false,
        }
    },

    computed: {
        barcode() {
            return this.$store.getters.barcode
        }
    },

    watch: {
        'barcode' (val) {
            if (val && !this.barcodeLinkModal) {
                this.clearFilter()
                this.filter.barcode = val
                this.getProducts()
            }
        },
        'loadPhotosAutomatically' (val) {
            if (val) {
                this.getProductImages()
            }
        }
    },

    created() {
        this.getProducts()
    },

    methods: {
        ship(product, qty) {
            axios.post(`/api/products/ship`, {
                productId: product.id,
                quantity: qty
            }).then(() => {
                setTimeout(() => this.getPos(product.catalogItem), 500)
                this.$snotify.success(`${product.catalogItem.identifier} shipped successfully!`)
                this.getProductOnHand(product)
            })
        },

        unship(product, qty) {
            axios.post(`/api/products/unship`, {
                productId: product.id,
                quantity: qty
            }).then(() => {
                setTimeout(() => this.getPos(product.catalogItem), 500)
                this.$snotify.success(`${product.catalogItem.identifier} unshipped successfully!`)
                this.getProductOnHand(product.catalogItem)
            })
        },

        getProducts(isSearch) {
            if (isSearch) {
                this.filter.page = 1
            }

            this.$router.push({
                query: this.filter
            })

            axios.get('/api/products', {
                params: this.filter
            }).then(res => {
                this.catalogItems = res.data.products
                this.meta = res.data.meta
            }).then(() => {
                if (this.loadPhotosAutomatically) {
                    this.getProductImages();
                }
            })
        },

        getProductOnHand(product) {
            if (product) {
                axios.get(`/api/products/${product.id}/on-hand`).then(res => {
                    product.onHand = res.data
                })
            }
        },

        getProductImages(product) {

            if (!product) {
                this.productImages = {}
                this.catalogItems.map(product => {
                    axios.get(`/api/products/${product.id}/images`).then(res => {
                        this.productImages[product.id] =  res.data
                    })
                })
            } else {
                axios.get(`/api/products/${product.id}/images`).then(res => {
                    this.productImages[product.id] =  res.data
                })
            }
        },

        handlePagination(page) {
            this.filter.page = page
            this.getProducts()
        },

        getPos(item) {
            this.getProductOnHand(item)
            this.selectedProduct = item
            axios.get(`/api/products/find-po-by-product?productIdentifier=${item.identifier}`).then(res => {
                this.pos = res.data.items
                this.products = res.data.products
                this.posModal = true
            })
        },

        showBarcodeLinkModal(product) {
            this.selectedProduct = product
            this.barcodeLinkModal = true
        },

        clearFilter() {
            this.filter.barcode = ''
            this.filter.description = ''
            this.filter.identifier = ''
            this.filter.page = 1
        },

        showUploadPhotoModal(product) {
            this.selectedProduct = product
            this.photoModal = true
        },

        uploadPhotos(files) {
            let formData = new FormData();

            for (let i = 0; i < files.length; i++) {
                formData.append(`images[${i}]`, files[i]);
            }

            axios.post('/api/products/' + this.selectedProduct.id + '/upload', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }).then(res => {
                if (typeof this.productImages[this.selectedProduct.id] !== "undefined") {
                    this.productImages[this.selectedProduct.id].push(...res.data)
                } else {
                    this.productImages[this.selectedProduct.id] = res.data
                }
                this.photoModal = false
                this.selectedProduct = null
                this.$snotify.success('Photos uploaded successfully!')
                })
        },

        createUsedItem(qty) {
            axios.post(`/api/products/${this.selectedProduct.id}/create-used-item`, {
                quantity: qty
            }).then(res => {
                this.$snotify.success('New used product added successfully!')
                this.clearFilter()
                this.getProducts()
                this.usedItemModal = false
            })
        },

        adjustItem(qty) {
            if (qty === '0') {
                return this.$snotify.error('Quantity must be positive or negative')
            }
            return axios.post(`/api/products/${this.selectedProduct.id}/adjust`, {
                quantity: qty
            }).then(res => {
                this.$snotify.success('Product adjusted successfully!')
                this.clearFilter()
                this.getProductOnHand(this.selectedProduct)
                this.adjustItemModal = false
            })
        }
    }
}
</script>

<style scoped>

</style>

