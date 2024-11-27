<template>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Scan</h1>
    </div>

    <form @submit.prevent="go" class="form">
        <div class="form-group">
            <label for="exampleFormControlInput1" class="form-label"><i class="bi-upc-scan"></i> Barcode (Enter manually if the barcode scanner does not read)</label>
            <input v-model="barcode" type="text" class="form-control" id="exampleFormControlInput1" placeholder="Barcode" required>
        </div>
        <button class="btn btn-primary mt-2" type="submit">Go</button>
    </form>


</template>

<script>
import Modal from "../Modal.vue";
import QuaggaScanner from "../QuaggaScanner.vue";
export default {
    components: {Modal, QuaggaScanner},
    data() {
        return {
            barcode: ''
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
        }
    },

    methods: {
        go() {
            this.$store.dispatch('setBarcode', this.barcode)
        }
    }
}
</script>

<style scoped>

</style>
