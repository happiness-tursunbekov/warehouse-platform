<template>
    <modal z-index="999999" v-model:show="modal" @close="$store.dispatch('setBarcode', '')" modal-title="What to do with this barcode?">
        Barcode: {{ this.barcode }}
    </modal>
    <modal z-index="999999" v-model:show="camModal" modal-title="Camera barcode reader">
        <div v-if="camBarcode" class="d-inline-block w-100">
            <p class="w-100 d-inline-block"><strong>{{ camBarcode }}</strong></p>
            <button @click.prevent="confirmCameraBarcode" class="btn btn-primary" type="button">Correct</button>
            <button @click.prevent="camBarcode = ''" class="btn btn-danger" type="button">Incorrect</button>
        </div>
        <div v-else class="scale-handle">
            <div class="position-relative" id="camScanner">
                <QuaggaScanner
                    :on-detected="handleCameraBarcode"
                    :reader-types="[
                        'code_128_reader',
                        'i2of5_reader',
                        'ean_reader',
                        'ean_8_reader',
                        'code_39_reader',
                        'code_39_vin_reader',
                        'codabar_reader',
                        'upc_reader',
                        'upc_e_reader',
                        '2of5_reader',
                        'code_93_reader'
                    ]"
                    :constraints="constraints"
                    :frequency="5"
                />
            </div>
        </div>
    </modal>

</template>

<script>
import Modal from "./Modal.vue";
import QuaggaScanner from "./QuaggaScanner.vue";
export default {
    name: "BarcodeHandler",
    components: {Modal, QuaggaScanner},

    props: {
        cameraModal: Boolean
    },

    data() {
        return {
            modal: false,
            camModal: false,
            constraints: {
                width: 200,
                height: 960
            },
            camBarcode: ''
        }
    },

    computed: {
        barcode() {
            return this.$store.getters.barcode
        }
    },

    watch: {
        'barcode' (val) {
            if (val && !this.$route.meta.handlesBarcode) {
                this.modal = true
            }
        },
        'camModal' (val) {
            this.$emit('update:cameraModal', val)
        },
        'cameraModal' (val) {
            this.camModal = val
        }
    },

    created() {
        // Handling barcode reader
        this.barcodeReaderHandler()
    },

    methods: {
        barcodeReaderHandler() {
            let timer = null
            let barcode = ''
            document.addEventListener("keydown", e => {
                const actEl = document.activeElement;
                if (!(['INPUT', 'TEXTAREA'].includes(actEl.tagName) || actEl.getAttribute('contenteditable'))) {
                    if (/^[a-zA-Z0-9!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]$/.test(e.key)) {
                        if (!barcode) {
                            timer = setTimeout(() => {
                                barcode = ''
                            }, 1000)
                        }
                        barcode += e.key;
                    } else if (e.key === 'Enter' && barcode.length > 2) {
                        this.$store.dispatch('setBarcode', barcode)
                        if (!this.$route.meta.handlesBarcode) {
                            this.modal = true
                        }
                        barcode = ''
                        clearTimeout(timer)
                        timer = null
                    }
                }
            });
        },
        handleCameraBarcode(data) {
            if (data.codeResult.code[0] === '0') {
                data.codeResult.code = data.codeResult.code.slice(1, data.codeResult.code.length)
            }
            this.camBarcode = data.codeResult.code;
        },

        confirmCameraBarcode() {
            this.$store.dispatch('setBarcode', this.camBarcode)
            this.camModal = false
            this.camBarcode = ''
        }
    }
}
</script>

<style scoped>
#camScanner {
    transform: scale(0.3);
    transform-origin: 0 0 0;
}

.scale-handle {
    width: 288px;
    height: 60px;
    display:inline-block;
}
</style>
