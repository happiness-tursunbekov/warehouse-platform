<template>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Receive</h1>
    </div>
    <div class="row">
        <div class="col-6">
            <form @submit.prevent="getItems(); identifier = ''">
                <div class="mb-3">
                    <label for="exampleFormControlInput1" class="form-label"><i class="bi-upc-scan"></i> Barcode (Enter manually if the barcode scanner does not read)</label>
                    <input v-model="barcode" type="text" class="form-control" id="exampleFormControlInput1" placeholder="Barcode" required>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-success">Search</button>
                </div>
            </form>
            <form @submit.prevent="getItems(); barcode = ''">
                <div class="mb-3">
                    <label for="exampleFormControlInput20" class="form-label"><i class="bi-text-paragraph"></i> Product ID</label>
                    <input v-model="identifier" type="text" class="form-control" id="exampleFormControlInput20" placeholder="Product ID" required>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-success">Search</button>
                </div>
            </form>
        </div>
        <div class="col-6">
            <form @submit.prevent="getPos">
                <div class="mb-3">
                    <label for="exampleFormControlInput3" class="form-label">PO Number</label>
                    <input v-model="poNumber" type="text" class="form-control" id="exampleFormControlInput3" placeholder="PO Number" required>
                </div>
                <div class="mb-3">
                    <div class="d-grid gap-2 d-md-block">
                        <button type="submit" class="btn btn-success">Search</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <modal v-model:show="posModal" modal-title="Found PO's">
        <div class="table-responsive" style="min-height: 250px">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th scope="col">PO number</th>
                    <th scope="col">Status</th>
                    <th scope="col">Action</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(item, key) in pos" :key="key">
                    <th scope="row">{{ item.poNumber }}</th>
                    <th scope="row">{{ [1,3].includes(item.status.id) && item.closedFlag === false ? 'Open' : 'Closed' }}</th>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi-three-dots-vertical"></i>
                            </button>
                            <div class="dropdown-menu">
                                <button @click.prevent="getPoItems(item)" class="dropdown-item" type="button">Get products</button>
                                <button @click.prevent="packingSlipHandle(item)" class="dropdown-item" type="button">Upload an attachment</button>
                                <button @click.prevent="poReport(item)" class="dropdown-item" type="button">View report</button>
                            </div>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </modal>
    <modal v-model:show="modal" modal-title="Please select">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th scope="col">Product ID</th>
                    <th scope="col">Action</th>
                    <th scope="col">Barcodes</th>
                    <th scope="col">Description</th>
                    <th scope="col">PO Number</th>
                    <th scope="col">Quantity</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(item, key) in items" :key="key">
                    <th scope="row">{{ item.productIdentifier }}</th>
                    <td>
                        <button v-if="!item.closedFlag && !item.cancelledFlag" @click.prevent="selectItem(item)" class="btn btn-success btn-sm" type="button">Receive</button>
                        <span v-else-if="item.cancelledFlag">Cancelled</span>
                        <span v-else>Received</span>
                    </td>
                    <td>
                        <div>{{ item.barcodes.join("\n") }}</div>
                        <button @click.prevent="showBarcodeLinkModal(item)" type="button" class="btn btn btn-secondary btn-sm">Link a barcode</button>
                    </td>
                    <td>{{ item.description }}</td>
                    <td>{{ item.poNumber }}</td>
                    <td>{{ item.quantity }}</td>
                </tr>
                </tbody>
            </table>
        </div>
    </modal>
    <modal v-model:show="receiveModal" modal-title="Receive">
        <form @submit.prevent="receive">
            <ul v-if="selectedItem" class="list-group list-group-flush mb-2">
                <li class="list-group-item"><strong>Product ID:</strong> {{ selectedItem.productIdentifier }}</li>
                <li class="list-group-item"><strong>Description:</strong> {{ selectedItem.description }}</li>
                <li class="list-group-item"><strong>PO Number:</strong> {{ selectedItem.poNumber }}</li>
                <li class="list-group-item"><strong>Quantity expecting:</strong> {{ selectedItem.quantity }}</li>
                <li v-if="selectedItem.closedFlag" class="list-group-item"><strong>Received quantity:</strong> {{ selectedItem.receivedQuantity }}</li>
                <li v-if="selectedItem.cancelledFlag" class="list-group-item"><strong>Status:</strong> Cancelled</li>
            </ul>
            <template v-if="!selectedItem.closedFlag && !selectedItem.cancelledFlag">
                <div class="mb-3">
                    <label for="exampleFormControlInput12" class="form-label">Quantity to receive</label>
                    <input v-model="quantity" type="number" class="form-control" id="exampleFormControlInput12" placeholder="Qty" min="1" required>
                </div>
                <button class="btn btn-success btn-sm" type="submit">Receive</button>
            </template>
        </form>
    </modal>
    <barcode-link-modal @handled="getItems" v-model:show="barcodeLinkModal" :barcode="barcode" :product="selectedItem ? { id: selectedItem.productId, identifier: selectedItem.productIdentifier } : null"/>
    <file-upload-modal v-model:show="packingSlipModal" modal-title="Upload an attachment" @upload="packingSlipUpload" :accept="['image/*', 'application/pdf']" multiple>
        <div class="mb-3">
            <label for="attachment-po-id" class="form-label">PO</label>
            <input v-if="packingSlip.po" readonly :value="packingSlip.po.poNumber" type="text" class="form-control" id="attachment-po-id" placeholder="Po" required>
        </div>
    </file-upload-modal>
    <modal v-model:show="poReportModal" modal-title="Report">
        <embed v-if="poReportLink" :src="poReportLink" style="min-width: 400px;min-height: 400px"/>
    </modal>
</template>

<script>
import Modal from "../../Modal.vue";
import BarcodeLinkModal from "../../BarcodeLinkModal.vue";
import FileUploadModal from "../../FileUploadModal.vue";
export default {
    name: "Receive",
    components: {FileUploadModal, BarcodeLinkModal, Modal},
    data() {
        return {
            modal: false,
            receiveModal: false,
            barcodeLinkModal: false,
            packingSlipModal: false,
            posModal: false,
            items: [],
            identifier: '',
            quantity: 0,
            poNumber: '',
            selectedItem: null,
            barcode: this.$route.query.barcode || '',
            packingSlip: {
                po: null
            },
            pos: [],
            poReportModal: false,
            poReportLink: ''
        }
    },

    computed: {
        scannedBarcode() {
            return this.$store.getters.barcode
        }
    },

    watch: {
        'scannedBarcode' (val) {
            this.barcode = val
            if (!this.barcodeLinkModal) {
                this.getItems()
            }
        }
    },

    methods: {
        receive() {
            axios.post('/api/products/receive', {
                quantity: this.quantity,
                id: this.selectedItem.id
            }).then(res => {
                switch (res.data.code) {
                    case 'SUCCESS':
                        this.$snotify.success(`Product ${res.data.item.productIdentifier} received successfully!`)
                        this.receiveModal = false
                        this.identifier = ''
                        this.barcode = ''
                        this.quantity = 0
                        this.selectedItem = null
                        break;
                    case 'ERROR':
                        this.$snotify.error(`Error: ${res.data.message}`);
                        break;
                }
            })
        },

        getPoItems(po) {
            axios.get('/api/products/po-items', {
                params: {
                    poId: po.id
                }
            }).then(res => this.handleResult(res));
        },

        getPos() {
            axios.get('/api/products/pos', {
                params: {
                    poNumber: this.poNumber
                }
            }).then(res => {
                if (res.data.items.length === 0) {
                    this.$snotify.error('There is no such PO!')
                } else {
                    this.pos = res.data.items
                    this.posModal = true
                }
            });
        },

        getItems() {
            axios.get('/api/products/po-items', {
                params: {
                    identifier: this.identifier,
                    barcode: this.barcode
                }
            }).then(res => this.handleResult(res));
        },

        selectItem(item) {
            this.identifier = item.productIdentifier
            this.selectedItem = item
            this.quantity = item.quantity
            this.modal = false
            this.receiveModal = true
        },

        handleResult(res) {
            if (res.data.code === 'BARCODE_NOT_FOUND') {
                this.barcodeLinkModal = true
            } else switch (res.data.items.length) {
                case 0:
                    this.$snotify.error('No such product found in open PO(\'s) to receive!')
                    break;
                case 1:
                    this.selectItem(res.data.items[0])
                    break;
                default:
                    this.items = res.data.items
                    this.modal = true
            }
        },

        packingSlipHandle(item) {
            this.packingSlipModal = true
            this.packingSlip.po = item
        },

        packingSlipUpload(files) {
            const formData = new FormData()
            for (let i = 0; i < files.length; i++) {
                formData.append(`files[${i}]`, files[i]);
            }
            formData.append('poId', this.packingSlip.po.id)
            axios.post('/api/products/upload-po-attachment', formData).then(res => {
                switch (res.data.code) {
                    case 'SUCCESS':
                        this.$snotify.success(`File uploaded successfully!`)
                        this.packingSlipModal = false
                        this.packingSlip.po = null
                        this.packingSlipModal = false
                        break;
                    case 'ERROR':
                        this.$snotify.error(`Error: ${res.data.message}`);
                        break;
                }
            })
        },

        showBarcodeLinkModal(item) {
            this.selectedItem = item
            this.barcodeLinkModal = true
        },

        poReport(item) {
            axios.get(`/api/products/po-report?poId=${item.id}`, { responseType: 'blob' }).then(res => {
                this.poReportLink = URL.createObjectURL(res.data)
                this.poReportModal = true
            })
        }
    }
}
</script>

<style scoped>

</style>
