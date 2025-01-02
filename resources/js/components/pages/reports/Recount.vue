<template>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Recounting</h1>
        <button @click.prevent="export2csv" type="button" class="btn btn-outline-success">Export as CSV</button>
    </div>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead class="sticky-top">
            <tr>
                <th>Product ID</th>
                <th>Description</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(record, key) in report" :key="key">
                <td>{{ record.item.identifier }}</td>
                <td>{{ record.item.description }}</td>
            </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
export default {
    name: "Recount",

    data() {
        return {
            report: []
        }
    },

    created() {
        this.fetchReport()
    },

    methods: {
        fetchReport() {
            axios.get('/api/auth/user/reports', {
                params: {type: 'ProductChecked'}
            }).then(res => {
                this.report = res.data = res.data.filter((value, index, self) =>
                        index === self.findIndex((t) => (
                            t.id === value.id
                        ))
                )
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
            a.setAttribute("download", "CheckedProducts.csv");
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    }
}
</script>

<style scoped>

</style>
