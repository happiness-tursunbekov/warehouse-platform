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
                                    <button @click.prevent="showUploadPhotoModal(product)" type="button" class="dropdown-item" title="Upload a photo">Upload a photo</button>
                                    <button @click.prevent="syncImages(product.id)" type="button" class="dropdown-item" title="Sync images">Sync images</button>
                                    <button @click.prevent="selectedProduct=product;usedItemModal=true" type="button" class="dropdown-item" title="Add used product">Add used product</button>
                                    <button @click.prevent="selectedProduct=product;uomModal=true" type="button" class="dropdown-item" title="Add used product">Edit unit of measure</button>
                                    <button v-if="user.reportMode" @click.prevent="selectedProduct=product;sellableModal=true" type="button" class="dropdown-item" title="Add used product">Add to sellable products list</button>
                                    <button @click.prevent="selectedProduct=product;takeCatalogItemToAzadMayModal=true;fetchCin7Suppliers()" type="button" class="dropdown-item">Sell to/list on Azad May</button>
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
                        <th>Cost</th>
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
                        <td>{{ po.cost }}</td>
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
                                    <button v-if="product.pickedQuantity - product.shippedQuantity !== 0" @click.prevent="selectedProjectProduct=product;takeProductToAzadMayModal=true;fetchCin7Suppliers()" type="button" class="dropdown-item">Take product to Azad May</button>
                                    <button v-if="product.pickedQuantity - product.shippedQuantity !== 0" @click.prevent="fetchProjects();selectedProjectProduct=product;moveProductModal=true" type="button" class="dropdown-item">Move product to different project</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </modal>
        <barcode-link-modal @handled="getProducts()" v-model:show="barcodeLinkModal" :barcode="barcode" :product="selectedProduct"/>
        <file-upload-modal @upload="uploadPhotos" v-model:show="photoModal" :accept="['image/*']" modal-title="Manage product photos" multiple/>
        <modal v-model:show="shipmentModal" :modal-title="'Shipment: ' + (selectedProjectProduct ? selectedProjectProduct.catalogItem.identifier : '')">
            <div v-if="selectedProjectProduct" style="min-width: 300px;">
                <form @submit.prevent="ship($refs.shipQty.value)" :class="{ 'd-none': selectedProjectProduct.quantity === selectedProjectProduct.shippedQuantity }" class="mb-3">
                    <hr class="mt-0 mb-1"/>
                    <label class="form-label">Ship</label>
                    <div class="input-group">
                        <input required :value="selectedProjectProduct.pickedQuantity - selectedProjectProduct.shippedQuantity" type="number" min="1" :max="selectedProjectProduct.pickedQuantity - selectedProjectProduct.shippedQuantity" ref="shipQty" class="form-control"/>
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
            </div>
        </modal>
        <modal v-model:show="usedItemModal" modal-title="Adding used catalog item">
            <form @submit.prevent="createUsedItem($refs.usedItemQty.value, $refs.usedItemCost.value)">
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
                    <label class="form-label">Cost</label>
                    <div class="input-group">
                        <input ref="usedItemCost" type="number" :value="selectedProduct.cost" class="form-control" required>
                        <span class="input-group-text">$</span>
                    </div>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </modal>
        <modal v-model:show="takeProductToAzadMayModal" modal-title="Taking product to Azad May list">
            <form v-if="selectedProjectProduct" @submit.prevent="addToNeedsToBeTakenProducts($refs.takeProductToAzadMay.value, $refs.takeProductToAzadMayCost.value)">
                <ul class="list-group">
                    <li class="list-group-item"><strong>Product ID:</strong> {{ selectedProjectProduct.catalogItem.identifier }}</li>
                    <li class="list-group-item"><strong>Description:</strong> {{ selectedProjectProduct.description }}</li>
                </ul>
                <div class="mb-3">
                    <label class="form-label">Quantity</label>
                    <div class="input-group">
                        <input ref="takeProductToAzadMay" type="number" min="1" class="form-control" :max="selectedProjectProduct.pickedQuantity - selectedProjectProduct.shippedQuantity" :value="selectedProjectProduct.pickedQuantity - selectedProjectProduct.shippedQuantity" required>
                        <span class="input-group-text">{{ selectedProjectProduct.unitOfMeasure.name }}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Cost</label>
                    <div class="input-group">
                        <input ref="takeProductToAzadMayCost" type="number" min="1" class="form-control" :value="selectedProjectProduct.cost" required>
                        <span class="input-group-text">$</span>
                    </div>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-success">Add to list</button>
                </div>
            </form>
        </modal>
        <modal v-model:show="takeCatalogItemToAzadMayModal" modal-title="Selling to/listing on Azad May">
            <form v-if="selectedProduct" @submit.prevent="addToNeedsToBeTakenCatalogItems($refs.takeCatalogItemToAzadMay.value, $refs.takeCatalogItemToAzadMayCost.value)">
                <ul class="list-group">
                    <li class="list-group-item"><strong>Product ID:</strong> {{ selectedProduct.identifier }}</li>
                    <li class="list-group-item"><strong>Description:</strong> {{ selectedProduct.description }}</li>
                </ul>
                <div class="mb-3">
                    <label class="form-label">Quantity</label>
                    <div class="input-group">
                        <input ref="takeCatalogItemToAzadMay" type="number" min="1" class="form-control" value="1" required>
                        <span class="input-group-text">{{ selectedProduct.unitOfMeasure.name }}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Cost</label>
                    <div class="input-group">
                        <input ref="takeCatalogItemToAzadMayCost" type="number" min="1" class="form-control" :value="selectedProduct.cost" required>
                        <span class="input-group-text">$</span>
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input ref="takeCatalogItemToAzadMayDoNotCharge" class="form-check-input" type="checkbox" id="flexCheckChecked-doNotCharge">
                    <label class="form-check-label" for="flexCheckChecked-doNotCharge">
                        Do not charge
                    </label>
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
        <modal v-model:show="moveProductModal" modal-title="Moving product to a different project">
            <form v-if="selectedProjectProduct" @submit.prevent="moveProduct($refs.takeProductToDifferentProject.value)">
                <ul class="list-group mb-3">
                    <li class="list-group-item"><strong>Product ID:</strong> {{ selectedProjectProduct.catalogItem.identifier }}</li>
                    <li class="list-group-item"><strong>Description:</strong> {{ selectedProjectProduct.description }}</li>
                </ul>
                <div class="mb-3">
                    <label class="form-label">Quantity</label>
                    <div class="input-group">
                        <input ref="takeProductToDifferentProject" type="number" min="1" :max="selectedProjectProduct.pickedQuantity - selectedProjectProduct.shippedQuantity" class="form-control" :value="selectedProjectProduct.pickedQuantity - selectedProjectProduct.shippedQuantity" required>
                        <span class="input-group-text">{{ selectedProjectProduct.unitOfMeasure.name }}</span>
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="flexCheckChecked" v-model="isExistingProduct">
                    <label class="form-check-label" for="flexCheckChecked">
                        Existing product
                    </label>
                </div>
                <div v-if="isExistingProduct">
                    <ul class="list-group mb-3">
                        <li v-for="product in products.filter(prod => prod.id !== selectedProjectProduct.id && prod.quantity !== prod.pickedQuantity)" :key="product.id" class="list-group-item">
                            <div class="form-check">
                                <input required class="form-check-input" type="radio" :id="'flexCheckChecked' + product.id" v-model="moveProductForm.toProductId" :value="product.id">
                                <label class="form-check-label" :for="'flexCheckChecked' + product.id">
                                    <template v-if="product.project">
                                        #{{ product.project.id }} - {{ product.project.name }} ({{ product.company.name }})
                                    </template>
                                    <template v-else>
                                        {{ product.company.name }}
                                    </template>
                                    <span class="small fst-italic d-block w-100 text-success">
                                        Max qty: {{ product.quantity - product.pickedQuantity }}
                                    </span>
                                </label>
                            </div>
                        </li>
                    </ul>
                </div>
                <div v-else>
                    <div class="mb-3">
                        <label class="form-label">Project</label>
                        <v-select
                            v-model="moveProductForm.projectId"
                            :options="projects"
                            :get-option-label="option => option.id !== '0' ? `#${option.id} - ${option.name} (${option.company.name})` : option.name"
                            :reduce="option => option.id"
                            placeholder="Select a project"
                            required
                        />
                    </div>
                    <div v-if="moveProductForm.projectId && moveProductForm.projectId !== '0'" class="mb-3">
                        <label class="form-label">Phase</label>
                        <v-select
                            v-model="moveProductForm.phaseId"
                            :options="phases"
                            label="title"
                            :reduce="option => option.id"
                            placeholder="Select a phase"
                            required
                        />
                    </div>
                    <div v-if="moveProductForm.projectId && moveProductForm.projectId !== '0'" class="mb-3">
                        <label class="form-label">Project Ticket</label>
                        <select v-model="moveProductForm.ticketId" class="form-control" required>
                            <option value="">Select a ticket</option>
                            <option value="0" :disabled="tickets.length > 0">No ticket</option>
                            <option v-for="(ticket) in tickets" :key="ticket.id" :value="ticket.id" :disabled="ticket.closedFlag">{{ ticket.summary }} (Status: {{ ticket.status.name }})</option>
                        </select>
                    </div>
                    <div v-if="moveProductForm.projectId && moveProductForm.projectId !== '0' && bundles.length > 0" class="mb-3">
                        <label class="form-label">Bundle</label>
                        <select v-model="moveProductForm.bundleId" class="form-control" required>
                            <option value="">Select a bundle</option>
                            <option value="0" :disabled="tickets.length > 0">No bundle</option>
                            <option v-for="(bundle) in bundles" :key="bundle.id" :value="bundle.id">{{ bundle.catalogItem.identifier }}</option>
                        </select>
                    </div>
                    <div v-if="moveProductForm.projectId === '0'" class="mb-3">
                        <label class="form-label">Company</label>
                        <v-select
                            v-model="moveProductForm.companyId"
                            :options="companies"
                            label="name"
                            :reduce="option => option.id"
                            placeholder="Select a company"
                            required
                        />
                    </div>
                    <div v-if="moveProductForm.projectId === '0'" class="mb-3">
                        <label class="form-label">Service ticket</label>
                        <v-select
                            v-model="moveProductForm.ticketId"
                            :options="tickets"
                            :get-option-label="option => `#${option.id} - ${option.summary}`"
                            :reduce="option => option.id"
                            placeholder="Select a ticket"
                            required
                        />
                    </div>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-success">Move</button>
                </div>
            </form>
        </modal>
        <div class="position-fixed bg-white p-3" style="right:0;bottom:0;max-height: 100%;max-width: 100%;overflow: auto; z-index: 1021">
            <form @submit.prevent="handleAzadMayList">
                <div v-if="needsToBeTakenCatalogItems.length || needsToBeTakenProducts.length > 0" class="mb-3">
                    <label class="form-label">Cin7 Supplier</label>
                    <select v-model="supplierId" class="form-control" required>
                        <option value="">Select a supplier</option>
                        <option value="0">Listing only</option>
                        <option v-for="(supplier) in cin7Suppliers" :key="supplier.ID" :value="supplier.ID">{{ supplier.Name }}</option>
                    </select>
                </div>

                <div v-if="needsToBeTakenCatalogItems.length > 0" class="card">
                    <div class="card-header"><a class="btn-link" @click.prevent="takeCatalogItemsToAzadMayModal = !takeCatalogItemsToAzadMayModal" role="button">[{{ takeCatalogItemsToAzadMayModal ? 'Hide' : 'Show' }}]</a> Sale list for Azad May Inventory ({{ needsToBeTakenCatalogItems.length }})</div>
                    <div v-if="takeCatalogItemsToAzadMayModal" class="card-body table-responsive">
                        <table class="table table-striped">
                            <thead class="sticky-top">
                            <tr>
                                <th>Product ID</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Cost</th>
                                <th>Do Not Charge</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(item, key) in needsToBeTakenCatalogItems" :key="key">
                                <td>{{ item.catalogItem.identifier }}</td>
                                <td>{{ item.catalogItem.category.name }}</td>
                                <td>{{ item.quantity }}</td>
                                <td>{{ item.cost }}</td>
                                <td>{{ item.doNotCharge }}</td>
                                <td>
                                    <button @click="needsToBeTakenCatalogItems=needsToBeTakenCatalogItems.filter(it => it.catalogItem.id !== item.catalogItem.id)" type="button" class="btn btn-sm btn-outline-danger">
                                        <i class="bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            </tbody>
                        </table>

                        <button type="submit" class="btn btn-success btn-sm">Start the process</button>
                    </div>
                </div>
                <div v-if="needsToBeTakenProducts.length > 0" class="card">
                    <div class="card-header"><a class="btn-link" @click.prevent="takeProductsToAzadMayModal = !takeProductsToAzadMayModal" role="button">[{{ takeProductsToAzadMayModal ? 'Hide' : 'Show' }}]</a> Products list for Azad May Inventory ({{ needsToBeTakenProducts.length }})</div>
                    <div v-if="takeProductsToAzadMayModal" class="card-body table-responsive">
                        <table class="table table-striped">
                            <thead class="sticky-top">
                            <tr>
                                <th>Product ID</th>
                                <th>Project</th>
                                <th>Company</th>
                                <th>Ticket</th>
                                <th>Sales Order</th>
                                <th>Quantity</th>
                                <th>Cost</th>
                                <th>Action</th>
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
                                <td>{{ item.cost }}</td>
                                <td>
                                    <button @click="needsToBeTakenProducts=needsToBeTakenProducts.filter(it => it.product.id !== item.product.id)" type="button" class="btn btn-sm btn-outline-danger">
                                        <i class="bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            </tbody>
                        </table>

                        <button type="submit" class="btn btn-success btn-sm">Start the process</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import Pagination from "../../Pagination.vue";
import Modal from "../../Modal.vue";
import BarcodeLinkModal from "../../BarcodeLinkModal.vue";
import FileUploadModal from "../../FileUploadModal.vue";
import VSelect from "../../v-select/components/VSelect.vue";

export default {
    name: "Index",
    components: {VSelect, FileUploadModal, BarcodeLinkModal, Modal, Pagination},

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
            shipmentModal: false,
            usedItemModal: false,
            nothingFound: false,
            sellableModal: false,
            uomModal: false,
            uoms: [],
            takeProductToAzadMayModal: false,
            takeCatalogItemToAzadMayModal: false,
            takeCatalogItemsToAzadMayModal: false,
            takeProductsToAzadMayModal: false,
            needsToBeTakenProducts: [],
            needsToBeTakenCatalogItems: [],
            moveProductModal: false,
            moveProductForm: {
                projectId: '',
                phaseId: '',
                ticketId: '',
                companyId: '',
                bundleId: '',
                toProductId: ''
            },
            isExistingProduct: false,
            projects: [],
            tickets: [],
            phases: [],
            companies: [],
            bundles: [],
            cin7Suppliers: [],
            supplierId: ''
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
        },
        'moveProductForm.projectId' (val) {
            this.moveProductForm.phaseId = ''
            this.moveProductForm.ticketId = ''
            this.moveProductForm.bundleId = ''

            if (val === '0') {
                this.fetchCompanies()
            } else {
                this.fetchPhases()
            }
        },
        'moveProductForm.phaseId' () {
            this.fetchProjectTickets()
        },
        'moveProductForm.companyId' () {
            this.fetchServiceTickets()
        },
        'moveProductForm.ticketId' (val) {
            this.fetchBundles(val)
        },
        'moveProductForm.toProductId' (val) {

            if (this.selectedProjectProduct) {

                const available = this.selectedProjectProduct.pickedQuantity - this.selectedProjectProduct.shippedQuantity

                if (val) {
                    const product = this.products.filter(product => product.id === val)[0]

                    const max = product.quantity - product.pickedQuantity

                    this.$refs.takeProductToDifferentProject.max = (max > available ? available : max) + ""
                } else {
                    this.$refs.takeProductToDifferentProject.max = available + ""
                }
            }
        },
        'isExistingProduct' (val) {
            if (!val) {
                this.moveProductForm.toProductId = ''
            }
        }
    },

    created() {
        this.getProducts()
    },

    methods: {
        moveProduct(quantity) {
            axios.post(`/api/products/move-product-to-different-project`, {
                ...this.moveProductForm,
                quantity,
                productId: this.selectedProjectProduct.id
            }).then(() => {
                this.getPos(this.selectedProduct)
                this.$snotify.success('Product moved successfully!')
                this.moveProductModal = false
                this.moveProductForm.toProductId = ''
                this.selectedProjectProduct = null
            })
        },
        syncImages(productId) {
            axios.post(`/api/products/${productId}/sync-images`).then(() => {
                this.$snotify.success('Product images synced successfully!')
            })
        },
        fetchProjects() {
            if (!this.projects.length) {
                axios.get(`/api/binyod/projects`).then(res => {
                    this.projects = [{
                        id: '0',
                        name: 'No project'
                    }, ...res.data]
                })
            }
        },
        fetchCin7Suppliers() {
            if (!this.cin7Suppliers.length) {
                axios.get(`/api/products/cin7-suppliers`).then(res => {
                    this.cin7Suppliers = res.data
                })
            }
        },
        fetchBundles(ticketId) {
            if (ticketId) {
                axios.get(`/api/binyod/bundles`, {
                    params: ticketId !== '0' ? ({ ticketId }) : ({ projectId: this.moveProductForm.projectId })
                }).then(res => {
                    this.bundles = res.data
                })
            } else {
                this.bundles = []
            }
        },
        fetchCompanies() {
            if (!this.companies.length) {
                axios.get(`/api/binyod/companies`).then(res => {
                    this.companies = res.data
                })
            }
        },
        fetchPhases() {
            if (this.moveProductForm.projectId) {
                axios.get(`/api/binyod/phases`, {
                    params: { projectId: this.moveProductForm.projectId }
                }).then(res => {
                    this.phases = [{
                        id: '0',
                        title: 'No phase'
                    }, ...res.data]
                })
            } else {
                this.phases = []
            }
        },
        fetchProjectTickets() {
            if (this.moveProductForm.phaseId) {
                axios.get(`/api/binyod/project-tickets`, {
                    params: {
                        projectId: this.moveProductForm.projectId || null,
                        phaseId: this.moveProductForm.phaseId || null
                    }
                }).then(res => {
                    this.tickets = res.data
                })
            } else {
                this.tickets = []
            }

            this.moveProductForm.bundleId = ''
        },
        fetchServiceTickets() {
            if (this.moveProductForm.companyId) {
                axios.get(`/api/binyod/service-tickets`, {
                    params: {
                        companyId: this.moveProductForm.companyId
                    }
                }).then(res => {
                    this.tickets = res.data
                })
            } else {
                this.tickets = []
            }

            this.moveProductForm.bundleId = ''
        },
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
                })),
                supplierId: this.supplierId
            }).then(() => {
                this.$snotify.success(`Products moved to Azad May Inventory successfully!`)
                this.takeProductsToAzadMayModal = false
                this.needsToBeTakenProducts = []
            })
        },

        takeCatalogItemsToAzadMay() {
            return axios.post(`/api/products/take-products-to-azad-may`, {
                products: this.needsToBeTakenCatalogItems.map(item => ({
                    id: item.catalogItem.id,
                    quantity: item.quantity,
                    doNotCharge: item.doNotCharge,
                })),
                isCatalogItem: true,
                supplierId: this.supplierId
            }).then(() => {
                this.$snotify.success(`Sale/list process finished successfully!`)
                this.takeCatalogItemsToAzadMayModal = false
                this.needsToBeTakenCatalogItems = []
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
            axios.get(`/api/products/find-po-by-product?catalogItemId=${item.id}`).then(res => {
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

        createUsedItem(qty, cost) {
            axios.post(`/api/products/${this.selectedProduct.id}/create-used-item`, {
                quantity: qty,
                cost
            }).then(res => {
                this.$snotify.success('New used product added successfully!')
                this.clearFilter()
                this.filter.identifier = res.data.identifier
                this.getProducts()
                this.usedItemModal = false
            })
        },

        addToNeedsToBeTakenProducts(qty, cost) {
            this.needsToBeTakenProducts.push({
                product: this.selectedProjectProduct,
                quantity: qty,
                cost
            })

            this.$snotify.success('Product added to the list!')

            this.takeProductToAzadMayModal = false
        },

        addToNeedsToBeTakenCatalogItems(qty, cost) {
            this.needsToBeTakenCatalogItems.push({
                catalogItem: this.selectedProduct,
                quantity: qty,
                doNotCharge: this.$refs.takeCatalogItemToAzadMayDoNotCharge.checked,
                cost
            })

            this.$snotify.success('CatalogItem added to the list!')

            this.takeCatalogItemToAzadMayModal = false
        },

        handleAzadMayList() {
            if (this.needsToBeTakenCatalogItems.length > 0) {
                this.takeCatalogItemsToAzadMay()
            }

            if (this.needsToBeTakenProducts.length > 0) {
                this.takeProductsToAzadMay()
            }
        }
    }
}
</script>

<style scoped>

</style>

