<template>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Reports</h1>
        <button @click.prevent="export2csv" type="button" class="btn btn-outline-success">Export as CSV</button>
    </div>
    <div class="row">
        <div class="col-12">
            <h5 v-if="!user.reportMode" class="text-danger h5">Report mode is off. Turn it on to use this feature. You can do it on Settings page :)</h5>
            <h5 v-else-if="noRecord" class="h5">No records yet :)</h5>
            <div v-else>
                <template v-if="reports.ProductShipment">
                    <h5 class="h5">Product Shipment</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="sticky-top">
                            <tr>
                                <th>Action</th>
                                <th>Product</th>
                                <th>Cost</th>
                                <th>Project</th>
                                <th>Company</th>
                                <th>Phase</th>
                                <th>Quantity</th>
                                <th>Record Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(shipment, key) in reports.ProductShipment" :key="key">
                                <td>{{ shipment.action }}</td>
                                <td>{{ shipment.item.productInfo.catalogItem.identifier }}</td>
                                <td>{{ shipment.item.productInfo.cost }}</td>
                                <td><span v-if="shipment.item.productInfo.project">#{{ shipment.item.productInfo.project.id }} - {{ shipment.item.productInfo.project.name }}</span></td>
                                <td>{{ shipment.item.productInfo.company.name }}</td>
                                <td>{{ shipment.item.productInfo.phase ? shipment.item.productInfo.phase.name : '' }}</td>
                                <td>{{ shipment.item.shippedQuantity }}</td>
                                <td>{{ shipment.item._info.lastUpdated }}</td>
                            </tr>
                            <template v-if="reports.CatalogProductUsed">
                                <tr v-for="(used, key) in reports.CatalogProductUsed" :key="key + (reports.ProductShipment ? reports.ProductShipment.length : 0)">
                                    <td>{{ used.action }}</td>
                                    <td>{{ used.item.catalogItem.identifier }}</td>
                                    <td>{{ used.item.catalogItem.cost }}</td>
                                    <td><span v-if="used.item.project">#{{ used.item.project.id }} - {{ used.item.project.name }}</span></td>
                                    <td>{{ used.item.company.name }}</td>
                                    <td>{{ used.item.phase ? used.item.phase.name : '' }}</td>
                                    <td></td>
                                    <td>{{ used.item.catalogItem._info.lastUpdated }}</td>
                                </tr>
                            </template>
                            <template v-if="reports.UsedCatalogItem">
                                <tr v-for="(item, key) in reports.UsedCatalogItem" :key="key + (reports.ProductShipment ? reports.ProductShipment.length : 0) + (reports.CatalogProductUsed ? reports.CatalogProductUsed.length : 0)">
                                    <td>{{ item.action }}</td>
                                    <td>{{ item.item.identifier }}</td>
                                    <td>{{ item.item.cost }}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>{{ item.item._info.dateEntered }}</td>
                                </tr>
                            </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
<script>
export default {
    name: "Reports",

    computed: {
        user() {
            return this.$store.getters.user
        },
        noRecord() {
            return Object.keys(this.reports).length === 0
        }
    },

    data() {
        return {
            reports: {}
        }
    },

    mounted() {
        this.fetchReports()
    },

    methods: {
        fetchReports() {
            axios.get('/api/auth/user/reports').then(res => {
                this.reports = res.data
            })
        },

        export2csv() {
            let data = "";
            const tableData = [];
            const rows = document.querySelectorAll("table tr");
            for (const row of rows) {
                const rowData = [];
                for (const [index, column] of row.querySelectorAll("th, td").entries()) {
                    // To retain the commas in the "Description" column, we can enclose those fields in quotation marks.
                    if ((index + 1) % 3 === 0) {
                        rowData.push('"' + column.innerText + '"');
                    } else {
                        rowData.push(column.innerText);
                    }
                }
                tableData.push(rowData.join(","));
            }
            data += tableData.join("\n");
            const a = document.createElement("a");
            a.href = URL.createObjectURL(new Blob([data], { type: "text/csv" }));
            a.setAttribute("download", "reports.csv");
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    }
}
</script>

<style scoped>

</style>
