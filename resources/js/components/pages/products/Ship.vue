<template>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Shipments</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button @click.prevent="addModal = true" type="button" class="btn btn-sm btn-outline-secondary">Add a new shipment</button>
            </div>
        </div>
    </div>
    <div class="accordion" id="accordionExample">
        <div v-for="(form, key) in forms" :key="key" class="accordion-item">
            <h2 class="accordion-header">
                <button @click="selectedForm = selectedForm === key ? null : key" :ref="'accBtn' + key" class="accordion-button" :class="{ collapsed: forms.length - 1 !== key }" type="button" data-bs-toggle="collapse" :data-bs-target="'#collapse-' + key" :aria-expanded="forms.length - 1 === key ? 'true' : 'false'" :aria-controls="'collapse-' + key">
                    #{{ form.project.id }} - {{ form.project.name }} - {{ form.project.company.name }}
                </button>
            </h2>
            <div :id="'collapse-' + key" class="accordion-collapse collapse" :class="{ show: forms.length - 1 === key }" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    <button @click="addItemModal = true" class="btn btn-outline-success">Add item</button> or <i class="bi-upc-scan"></i> Scan the item barcode
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th scope="col">NO</th>
                                <th scope="col">ITEM #</th>
                                <th scope="col">QTY</th>
                                <th scope="col">ITEM DESCRIPTION</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(item, key) in searchForm.items" :key="key">
                                <td></td>
                                <th scope="row">{{ item.identifier }}</th>
                                <td>{{ item.description }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <modal v-if="selectedForm !== null" v-model:show="addItemModal" :modal-title="'Adding new item - #' + forms[selectedForm].project.id">
        <form @submit.prevent="searchItem">
            <div class="mb-3">
                <label for="searchForm.identifier" class="form-label">Product ID</label>
                <input required v-model="searchForm.identifier" type="text" class="form-control" id="searchForm.identifier" placeholder="Product ID">
            </div>
            <div class="mb-3">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>

        <div class="table-responsive">
            <table v-if="searchForm.items.length > 0" class="table table-striped">
                <thead>
                <tr>
                    <th scope="col">Action</th>
                    <th scope="col">Product ID</th>
                    <th scope="col">Description</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(item, key) in searchForm.items" :key="key">
                    <td><button @click.prevent="selectItem(item)" type="button" class="btn btn-outline-success btn-sm">Select</button></td>
                    <th scope="row">{{ item.identifier }}</th>
                    <td>{{ item.description }}</td>
                </tr>
                </tbody>
            </table>
        </div>

        <form @submit.prevent="addNewProductToForm" v-if="newItem.product">
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text" id="basic-addon2">Selected Product</span>
                    <input :value="newItem.product.identifier" readonly type="text" class="form-control" placeholder="Product ID">
                </div>
            </div>
            <div class="mb-3">
                <label for="newItem.quantity" class="form-label">Product ID</label>
                <div class="input-group">
                    <input required v-model="newItem.quantity" min="1" type="number" class="form-control" id="newItem.quantity" placeholder="Qty">
                    <span class="input-group-text" id="basic-addon2">Pcs</span>
                </div>
            </div>
            <div class="mb-3">
                <button type="submit" class="btn btn-success">Add</button>
            </div>
        </form>
    </modal>
    <modal v-model:show="addModal" modal-title="New shipment">
        <form @submit.prevent="addNewForm">
            <div class="mb-3">
                <label class="form-label">Projects</label>
                <v-select
                    :options="formOptions.projects"
                    v-model="newForm.project"
                    :get-option-label="(option) => '#' + option.id + ' - ' + option.name + ' - ' + option.company.name"
                    placeholder="select"
                    required
                />
            </div>
            <div class="mb-3">
                <label class="form-label">Team</label>
                <v-select
                    :options="formOptions.teams"
                    v-model="newForm.team"
                    label="name"
                    placeholder="select"
                    required
                />
            </div>
            <div class="mb-3">
                <label class="form-label">Member (accepting)</label>
                <v-select
                    :options="formOptions.members"
                    v-model="newForm.acceptedByMember"
                    :get-option-label="(option) => option.firstName + ' ' + option.lastName"
                    placeholder="select"
                    required
                />
            </div>
            <div class="mb-3">
                <button type="submit" class="btn btn-success">Add</button>
            </div>
        </form>
    </modal>
    <barcode-link-modal @handled="searchItem(barcode)" v-model:show="barcodeLinkModal" :barcode="barcode" />
</template>

<script>
import Modal from "../../Modal.vue";
import VSelect from "../../v-select/components/VSelect.vue";
import BarcodeLinkModal from "../../BarcodeLinkModal.vue";
const newForm = {
    project: '',
    team: '',
    acceptedByMember: '',
    items: [],
    signature: null
}
const newItem = {
    product: '',
    quantity: ''
}

export default {
    name: "Ship",
    components: {BarcodeLinkModal, VSelect, Modal},

    data() {
        return {
            forms: [],
            formOptions: {
                projects: [],
                teams: [],
                members: []
            },
            addModal: false,
            addItemModal: false,
            barcodeLinkModal: false,
            newForm: null,
            newItem: null,
            selectedForm: null,
            searchForm: {
                identifier: '',
                items: [],
                barcode: ''
            },
        }
    },

    computed: {
        barcode() {
            return this.$store.getters.barcode
        }
    },

    watch: {
        'barcode' (val) {
            if (val) {
                if (this.selectedForm !== null) {
                    this.addItemModal = true
                    this.searchItem(val)
                } else {
                    this.addModal = true
                    this.searchForm.barcode = val
                }
            }
        }
    },

    created() {
        this.getOptions().then(() => {
            this.resetNewForm()
            this.resetNewItem()
        })

    },

    methods: {
        getOptions() {
            return axios.options('/api/products/ship').then(res => {
                this.formOptions.projects = res.data.projects
                this.formOptions.teams = res.data.teams
                this.formOptions.members = res.data.members
            });
        },

        resetNewForm() {
            this.newForm = JSON.parse(JSON.stringify(newForm))
            const defaultKey = this.formOptions.teams.findIndex(item => item.id === 11);
            if (defaultKey) {
                this.newForm.team = this.formOptions.teams[defaultKey]
            }
        },

        resetNewItem() {
            this.newItem = JSON.parse(JSON.stringify(newItem))
        },

        addNewForm() {
            this.addModal = false
            this.forms.push(this.newForm)
            this.resetNewForm()
            this.selectedForm = this.forms.length - 1
            if (this.searchForm.barcode) {
                this.addItemModal = true
                this.searchItem(this.searchForm.barcode)
                this.searchForm.barcode = ''
            }
        },

        searchItem(barcode) {
            if (barcode) {
                this.searchForm.identifier = ''
            }
            axios.get('/api/products', {
                params: {
                    identifier: this.searchForm.identifier,
                    barcode: barcode || ''
                }
            }).then(res => {
                this.searchForm.items = res.data.products

                if (res.data.meta.total === 0) {
                    if (!barcode)
                        this.$snotify.error('There is no product with Product ID: ' + this.searchForm.identifier)
                    else {
                        this.barcodeLinkModal = true
                    }
                } else if (this.barcode) {
                    this.addItemModal = true
                }
            })
        },

        selectItem(item) {
            this.newItem.product = item
            this.searchForm.items = []
            this.searchForm.identifier = ''
        },

        addNewProductToForm() {
            const itemIndex = this.forms[this.selectedForm].items.indexOf(item => this.newItem.product.id === item.product.id);
            if (itemIndex > -1) {
                this.forms[this.selectedForm].items[itemIndex].quantity += this.newItem.quantity
            } else {
                this.forms[this.selectedForm].items.push(this.newItem)
            }
            this.addItemModal = false
            this.$snotify.success(`Product ${this.newItem.product.identifier} added successfully!`)
            this.resetNewItem()
        }
    }
}
</script>

<style scoped>

</style>
