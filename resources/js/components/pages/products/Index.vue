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
                        <th v-if="user.reportMode" scope="col">Checked</th>
                        <th scope="col">Product ID</th>
                        <th scope="col">Barcode</th>
                        <th scope="col">Description</th>
                        <th scope="col">Category</th>
                        <th scope="col" class="text-nowrap">On Hand (Azad May)</th>
                        <th scope="col">Photos</th>
                        <th scope="col">Price</th>
                        <th scope="col">Cost</th>
                        <th scope="col">Action</th>
                    </tr>
                    <tr>
                        <th v-if="user.reportMode"></th>
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
                        <th v-if="user.reportMode">
                            <div class="form-check form-switch">
                                <input v-model="product.checked" @change="handleCheck(product)" class="form-check-input" type="checkbox" role="switch" id="flexSwitchCheckChecked">
                                <label class="form-check-label" for="flexSwitchCheckChecked"></label>
                            </div>
                        </th>
                        <th scope="row">{{ product.identifier }}</th>
                        <td>
                            <div>{{ product.barcodes.join("\n") }}</div>
                            <button @click.prevent="showBarcodeLinkModal(product)" type="button" class="btn btn btn-secondary btn-sm">Link a barcode</button>
                        </td>
                        <td>{{ product.customerDescription	 }}</td>
                        <td>{{ product.category.name }}</td>
                        <td>
                            {{ product.onHand }} {{ product.unitOfMeasure.name }}
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
                                    <button @click.prevent="getPos(product)" type="button" class="dropdown-item">Get PO's/Ship</button>
                                    <button @click.prevent="selectedProduct=product;getProductOnHand(product);adjustItemModal=true" type="button" class="dropdown-item" title="Adjust">Adjust quantity</button>
                                    <button @click.prevent="showUploadPhotoModal(product)" type="button" class="dropdown-item" title="Upload a photo">Upload a photo</button>
                                    <button @click.prevent="selectedProduct=product;usedItemModal=true" type="button" class="dropdown-item" title="Add used product">Add used product</button>
                                    <button @click.prevent="selectedProduct=product;uomModal=true" type="button" class="dropdown-item" title="Add used product">Edit unit of measure</button>
                                    <button v-if="user.reportMode" @click.prevent="selectedProduct=product;sellableModal=true" type="button" class="dropdown-item" title="Add used product">Add to sellable products list</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div v-if="nothingFound" class="alert alert-warning">Nothing found</div>
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
            <div class="table-responsive" style="min-height: 250px">
                <table class="table table-striped">
                    <thead class="sticky-top">
                    <tr>
                        <th>Project</th>
                        <th>Company</th>
                        <th>Phase</th>
                        <th>Quantity</th>
                        <th>Picked Quantity</th>
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
                        <td>{{ product.pickedQuantity }}</td>
                        <td>{{ product.shippedQuantity }}</td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi-three-dots-vertical"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <button v-if="product.quantity !== product.shippedQuantity" @click.prevent="selectedProjectProduct=product;shipmentModal=true" type="button" class="dropdown-item">Ship</button>
                                    <button v-if="product.shippedQuantity !== 0" @click.prevent="selectedProjectProduct=product;shipmentModal=true" type="button" class="dropdown-item">Return/Unship</button>
                                    <button v-if="product.pickedQuantity - product.shippedQuantity !== 0" @click.prevent="selectedProjectProduct=product;takeProductToAzadMayModal=true" type="button" class="dropdown-item">Take product to Azad May</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </modal>
        <barcode-link-modal @handled="getProducts()" v-model:show="barcodeLinkModal" :barcode="barcode" :product="selectedProduct"/>
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
        <modal v-model:show="shipmentModal" :modal-title="'Shipment: ' + (selectedProjectProduct ? selectedProjectProduct.catalogItem.identifier : '')">
            <div v-if="selectedProjectProduct" style="min-width: 300px;">
                <form @submit.prevent="ship($refs.shipQty.value)" :class="{ 'd-none': selectedProjectProduct.quantity === selectedProjectProduct.shippedQuantity }" class="mb-3">
                    <hr class="mt-0 mb-1"/>
                    <label class="form-label">Ship</label>
                    <div class="input-group">
                        <input required :value="selectedProjectProduct.quantity - selectedProjectProduct.shippedQuantity" type="number" min="1" :max="selectedProjectProduct.quantity - selectedProjectProduct.shippedQuantity" ref="shipQty" class="form-control"/>
                        <span class="input-group-text">{{ selectedProduct.unitOfMeasure.name }}</span>
                    </div>
                    <button type="submit" class="btn btn-success mt-2 btn-sm">Ship</button>
                </form>

                <form @submit.prevent="unship($refs.unshipQty.value)" :class="{ 'd-none': selectedProjectProduct.shippedQuantity === 0 }" class="mb-3">
                    <hr class="mt-0 mb-1"/>
                    <label class="form-label">Return/Unship</label>
                    <div class="input-group">
                        <input required :value="0" type="number" min="1" :max="selectedProjectProduct.shippedQuantity" ref="unshipQty" class="form-control"/>
                        <span class="input-group-text">{{ selectedProduct.unitOfMeasure.name }}</span>
                    </div>
                    <button type="submit" class="btn btn-danger mt-2 btn-sm">Return/Unship</button>
                </form>

                <form @submit.prevent="unshipAsUsed($refs.unshipUsedQty.value)" :class="{ 'd-none': selectedProjectProduct.shippedQuantity === 0 }" class="mb-3">
                    <hr class="mt-0 mb-1"/>
                    <label class="form-label">Return/Unship as Used</label>
                    <div class="input-group">
                        <input required :value="0" type="number" min="1" :max="selectedProjectProduct.shippedQuantity" ref="unshipUsedQty" class="form-control"/>
                        <span class="input-group-text">{{ selectedProduct.unitOfMeasure.name }}</span>
                    </div>
                    <button type="submit" class="btn btn-danger mt-2 btn-sm">Return/Unship as Used</button>
                </form>
            </div>
        </modal>
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
                        <span class="input-group-text">{{ (selectedProduct.unitOfMeasure.name.toLowerCase().replace(/\s/g, '').trim().includes('usedcable') || selectedProduct.unitOfMeasure.name.toLowerCase().replace(/\s/g, '').includes('ft)')) ? 'Ft' : 'Pcs'  }}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </modal>
        <modal v-model:show="takeProductToAzadMayModal" modal-title="Adding used catalog item">
            <form v-if="selectedProjectProduct" @submit.prevent="addToNeedsToBeTakenProducts($refs.takeProductToAzadMay.value)">
                <ul class="list-group">
                    <li class="list-group-item"><strong>Product ID:</strong> {{ selectedProjectProduct.catalogItem.identifier }}</li>
                    <li class="list-group-item"><strong>Description:</strong> {{ selectedProjectProduct.description }}</li>
                </ul>
                <div class="mb-3">
                    <label class="form-label">Quantity</label>
                    <div class="input-group">
                        <input ref="takeProductToAzadMay" type="number" min="1" class="form-control" :value="selectedProjectProduct.pickedQuantity - selectedProjectProduct.shippedQuantity" required>
                        <span class="input-group-text">{{ selectedProjectProduct.unitOfMeasure.name }}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-success">Add to list</button>
                </div>
            </form>
        </modal>
        <modal v-model:show="sellableModal" modal-title="Adding product to sellable products list">
            <form @submit.prevent="handleSellable($refs.sellableQty.value)">
                <ul class="list-group">
                    <li class="list-group-item"><strong>Product ID:</strong> {{ selectedProduct.identifier }}</li>
                    <li class="list-group-item"><strong>Description:</strong> {{ selectedProduct.onHand }}</li>
                </ul>
                <div class="mb-3">
                    <label class="form-label">Quantity</label>
                    <div class="input-group">
                        <input ref="sellableQty" type="number" min="1" :max="selectedProduct.onHand" :value="selectedProduct.onHand" class="form-control" required>
                        <span class="input-group-text">{{ selectedProduct.unitOfMeasure.name }}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </modal>
        <modal v-model:show="uomModal" modal-title="Editing unit of measure of a product">
            <form @submit.prevent="updateUom($refs.catalogItemUom.value)">
                <div class="alert alert-warning">Warning: It will change Catalog items & project products price & cost as well</div>
                <ul class="list-group">
                    <li class="list-group-item"><strong>Product ID:</strong> {{ selectedProduct.identifier }}</li>
                    <li class="list-group-item"><strong>Unit of measure:</strong> {{ selectedProduct.unitOfMeasure.name }}</li>
                </ul>
                <div class="mb-3">
                    <label class="form-label">Unit of measure</label>
                    <div class="input-group">
                        <select ref="catalogItemUom" :value="selectedProduct.unitOfMeasure.id" class="form-select" required>
                            <option v-for="option in uoms" :key="option.id" :value="option.id">{{ option.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </modal>
        <div v-if="needsToBeTakenProducts.length > 0" class="position-fixed" style="right:0;bottom:0">
            <div class="card">
                <div class="card-header"><a class="btn-link" @click.prevent="takeProductsToAzadMayModal = !takeProductsToAzadMayModal" role="button">[{{ takeProductsToAzadMayModal ? 'Hide' : 'Show' }}]</a> Products list for Azad May Inventory ({{ needsToBeTakenProducts.length }})</div>
                <div v-if="takeProductsToAzadMayModal" class="card-body">
                    <table class="table table-striped">
                        <thead class="sticky-top">
                        <tr>
                            <th>Product ID</th>
                            <th>Project</th>
                            <th>Company</th>
                            <th>Ticket</th>
                            <th>Sales Order</th>
                            <th>Quantity</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="(item, key) in needsToBeTakenProducts" :key="key">
                            <td>{{ item.product.catalogItem.identifier }}</td>
                            <td><span v-if="item.product.project">#{{ item.product.project.id }} - {{ item.product.project.name }}</span></td>
                            <td>{{ item.product.company.name }}</td>
                            <td>{{ item.product.ticket ? item.product.ticket.id : '' }}</td>
                            <td>{{ item.product.salesOrder ? item.product.salesOrder.id : '' }}</td>
                            <td>{{ item.quantity }}</td>
                        </tr>
                        </tbody>
                    </table>

                    <button @click="takeProductsToAzadMay" type="button" class="btn btn-success btn-sm">Start the process</button>
                </div>
            </div>
        </div>
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
                barcode: this.$route.query.barcode || '',
                conditions: this.$route.query.conditions || '',
            },
            pos: [],
            products: [],
            posModal: false,
            barcodeLinkModal: false,
            selectedProduct: null,
            selectedProjectProduct: null,
            photoModal: false,
            productForm: {
                rmPhotos: [],
                upPhotos: [],
            },
            loadPhotosAutomatically: false,
            adjustItemModal: false,
            shipmentModal: false,
            usedItemModal: false,
            nothingFound: false,
            sellableModal: false,
            uomModal: false,
            uoms: [],
            takeProductToAzadMayModal: false,
            takeProductsToAzadMayModal: false,
            needsToBeTakenProducts: []
        }
    },

    computed: {
        barcode() {
            return this.$store.getters.barcode
        },

        textReader() {
            return this.$store.getters.textReader
        },

        user() {
            return this.$store.getters.user
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
        'textReader.value' (val) {
            if (val) {
                this.clearFilter()
                this.filter[this.textReader.field] = val
                this.getProducts()
            }
        },
        'loadPhotosAutomatically' (val) {
            if (val) {
                this.getProductImages()
            }
        },
        'uomModal' (val) {
            if (val && !this.uoms.length) {
                this.fetchUoms()
            }
        }
    },

    created() {
        this.getProducts()
    },

    methods: {
        fetchUoms() {
            axios.get(`/api/products/uoms`).then(res => {
                this.uoms = res.data
            })
        },
        updateUom(uomId) {
            axios.post(`/api/products/${this.selectedProduct.id}/uom`, {
                uomId: uomId
            }).then(res => {
                this.uomModal=false
                this.$snotify.success('Unit of measure changed successfully!')
                setTimeout(() => {
                    this.getProducts()
                }, 500)
            })
        },
        handleCheck(product) {
            axios.post(`/api/products/${product.id}/check`, {
                checked: product.checked
            })
        },
        handleSellable(quantity) {
            axios.post(`/api/products/${this.selectedProduct.id}/sellable`, {
                quantity: quantity
            }).then(res => {
                this.sellableModal=false
                this.$snotify.success('Added to sellable products list!')
            })
        },
        ship(qty) {
            axios.post(`/api/products/ship`, {
                productId: this.selectedProjectProduct.id,
                quantity: qty
            }).then(() => {
                setTimeout(() => this.getPos(this.selectedProduct), 500)
                this.$snotify.success(`${this.selectedProjectProduct.catalogItem.identifier} shipped successfully!`)
                this.shipmentModal = false
            })
        },

        unship(qty) {
            return axios.post(`/api/products/unship`, {
                productId: this.selectedProjectProduct.id,
                quantity: qty
            }).then(() => {
                setTimeout(() => this.getPos(this.selectedProduct), 500)
                this.$snotify.success(`${this.selectedProjectProduct.catalogItem.identifier} unshipped successfully!`)
                this.shipmentModal = false
            })
        },

        takeProductsToAzadMay() {
            return axios.post(`/api/products/take-products-to-azad-may`, {
                products: this.needsToBeTakenProducts.map(item => ({
                    id: item.product.id,
                    quantity: item.quantity
                }))
            }).then(() => {
                this.$snotify.success(`Products moved to Azad May Inventory successfully!`)
                this.takeProductsToAzadMayModal = false
                this.needsToBeTakenProducts = []
            })
        },

        unshipAsUsed(qty) {
            if (this.selectedProjectProduct.catalogItem.identifier.includes('-used)') && qty === toString(this.selectedProjectProduct.shippedQuantity)) {
                return this.unship(qty)
            }

            return axios.post(`/api/products/unship-as-used`, {
                productId: this.selectedProjectProduct.id,
                quantity: qty
            }).then(() => {
                setTimeout(() => this.getPos(this.selectedProduct), 500)
                this.$snotify.success(`${this.selectedProjectProduct.catalogItem.identifier} unshipped as used successfully!`)
                this.shipmentModal = false
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
                this.nothingFound = this.catalogItems.length === 0
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
        },

        createUsedItem(qty) {
            axios.post(`/api/products/${this.selectedProduct.id}/create-used-item`, {
                quantity: qty
            }).then(res => {
                this.$snotify.success('New used product added successfully!')
                this.clearFilter()
                this.filter.identifier = res.data.identifier
                this.getProducts()
                this.usedItemModal = false
            })
        },

        addToNeedsToBeTakenProducts(qty) {
            this.needsToBeTakenProducts.push({
                product: this.selectedProjectProduct,
                quantity: qty
            })

            this.$snotify.success('Product added to the list!')

            this.takeProductToAzadMayModal = false
        }
    }
}
</script>

<style scoped>

</style>

