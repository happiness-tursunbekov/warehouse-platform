<template>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Products</h1>
    </div>
    <div class="position-relative">
        <form @submit.prevent="getProducts(true)">
            <div class="table-responsive">
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
                        <th>
                            <input id="loadOnHandAutomatically" type="checkbox" v-model="loadOnHandAutomatically" /><label for="loadOnHandAutomatically">Auto load</label>
                        </th>
                        <th>
                            <input id="loadPhotosAutomatically" type="checkbox" v-model="loadPhotosAutomatically" /><label for="loadPhotosAutomatically">Auto load</label>
                        </th>
                        <th></th>
                        <th></th>
                        <th scope="col"><pagination :meta="meta" @change="handlePagination"/></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(product, key) in products" :key="key">
                        <th scope="row">{{ product.identifier }}</th>
                        <td>
                            <div>{{ product.barcodes.join("\n") }}</div>
                            <button @click.prevent="showBarcodeLinkModal(product)" type="button" class="btn btn btn-secondary btn-sm">Link a barcode</button>
                        </td>
                        <td>{{ product.description }}</td>
                        <td>{{ product.category.name }}</td>
                        <td>
                            <button v-if="typeof productOnHand[product.id] === ('undefined' || null)" class="btn btn-success btn-sm" type="button" @click.prevent="getProductOnHand(product)">Load</button>
                            <span v-else>{{ productOnHand[product.id] }}</span>
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
                            <button @click.prevent="getPos(product)" type="button" class="btn btn-outline-primary btn-sm">Get PO's</button>
                            <button @click.prevent="showUploadPhotoModal(product)" type="button" class="btn btn-outline-primary btn-sm" title="Upload a photo"><i class="bi-upload"></i></button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </form>
        <modal v-model:show="posModal" :modal-title="'Po\'s - ' + (selectedProduct ? selectedProduct.identifier : '')">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead class="sticky-top">
                    <tr>
                        <th>PO Number</th>
                        <th>PO Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(po, key) in pos" :key="key">
                        <td>{{ po.poNumber }}</td>
                        <td>
                            <span v-if="po.closedFlag && !po.canceledFlag">Received</span>
                            <span v-else-if="po.canceledFlag">Cancelled</span>
                            <span v-else>Open</span>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <h6 class="h6">Projects</h6>
            <table class="table table-striped">
                <thead class="sticky-top">
                <tr>
                    <th>Project</th>
                    <th>Company</th>
                    <th>Phase</th>
                    <th>Quantity</th>
                    <th>PO Approved</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(project, key) in projects" :key="key">
                    <td><span v-if="project.project">#{{ project.project.id }} - {{ project.project.name }}</span></td>
                    <td>{{ project.company.name }}</td>
                    <td>{{ project.phase ? project.phase.name : '' }}</td>
                    <td>{{ project.quantity }}</td>
                    <td>{{ project.poApprovedFlag }}</td>
                </tr>
                </tbody>
            </table>
        </modal>
        <barcode-link-modal @handled="getProducts()" v-model:show="barcodeLinkModal" :barcode="barcode" :product="selectedProduct"/>
        <modal v-model:show="photoModal" modal-title="Manage product photos">
            <form @submit.prevent="uploadPhotos">
                <div class="mb-3">
                    <label for="upload-photos" class="form-label">File</label>
                    <input v-on:change="handleProductPhotos" multiple type="file" class="form-control" id="upload-photos" required>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-success">Upload</button>
                </div>
            </form>
        </modal>
    </div>
</template>

<script>
import Pagination from "../../Pagination.vue";
import Modal from "../../Modal.vue";
import BarcodeLinkModal from "../../BarcodeLinkModal.vue";

export default {
    name: "Index",
    components: {BarcodeLinkModal, Modal, Pagination},

    data() {
        return {
            products: [],
            productOnHand: {},
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
            projects: [],
            posModal: false,
            barcodeLinkModal: false,
            selectedProduct: null,
            photoModal: false,
            productForm: {
                rmPhotos: [],
                upPhotos: [],
            },
            loadOnHandAutomatically: false,
            loadPhotosAutomatically: false
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
        'loadOnHandAutomatically' (val) {
            if (val) {
                this.getProductOnHand()
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
                this.products = res.data.products
                this.meta = res.data.meta
            }).then(() => {
                if (this.loadOnHandAutomatically) {
                    this.getProductOnHand()
                }
            })
        },

        getProductOnHand(product) {

            if (!product) {
                this.productOnHand = {}
                this.products.map(product => {
                    axios.get(`/api/products/${product.id}/on-hand`).then(res => {
                        this.productOnHand[product.id] =  res.data
                    })
                })
            } else {
                axios.get(`/api/products/${product.id}/on-hand`).then(res => {
                    this.productOnHand[product.id] =  res.data
                })
            }
        },

        getProductImages(product) {

            if (!product) {
                this.productImages = {}
                this.products.map(product => {
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
            this.selectedProduct = item
            axios.get(`/api/products/find-po-by-product?productIdentifier=${item.identifier}`).then(res => {
                this.pos = res.data.items
                this.projects = res.data.projects
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

        handleProductPhotos(e) {
            for (let i = 0; i < e.target.files.length; i++) {
                this.productForm.upPhotos.push(e.target.files[i]);
            }
        },

        uploadPhotos() {
            let formData = new FormData();

            for (let i = 0; i < this.productForm.upPhotos.length; i++) {
                formData.append(`images[${i}]`, this.productForm.upPhotos[i]);
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
        }
    }
}
</script>

<style scoped>

</style>
