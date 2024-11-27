<template>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Link barcode to a product</h1>
    </div>
    <div class="col-6">
        <form @submit.prevent="link">
            <div class="mb-3">
                <label for="exampleFormControlInput1" class="form-label"><i class="bi-upc-scan"></i> Barcode (Enter manually if the barcode scanner does not read)</label>
                <input v-model="barcode" type="text" class="form-control" id="exampleFormControlInput1" placeholder="Barcode" required>
            </div>
            <div class="mb-3">
                <button type="submit" class="btn btn-success">Link</button>
            </div>
        </form>
    </div>
    <barcode-link-modal @handled="onHandled" v-model:show="modal" :barcode="barcode"/>
</template>

<script>
import BarcodeLinkModal from "../../BarcodeLinkModal.vue";

export default {
    name: "LinkBarcode",
    components: {BarcodeLinkModal},

    data() {
        return {
            modal: false,
            items: [],
            barcode: ''
        }
    },

    computed: {
        readerBarcode() {
            return this.$store.getters.barcode
        }
    },

    watch: {
        'readerBarcode' (val) {
            this.barcode = val
            this.link()
        }
    },

    methods: {
        link() {
            this.modal = true
        },

        onHandled() {
            this.modal = false
        }
    }
}
</script>

<style scoped>

</style>
