<template>
    <modal v-model:show="modal">
        <div class="scale-handle">
            <div class="position-relative" id="txtScanner">
                <camera
                    @snapshot="snapshop"
                    :resolution="{ width: 500, height: 2000 }"
                />
            </div>
        </div>
        {{ text }}
    </modal>
</template>

<script>
import Camera from "./camera/Camera.vue";
import Tesseract from 'tesseract.js';
import Modal from "./Modal.vue";

export default {
    name: "TextReader",
    components: {Modal, Camera},

    props: {
        cameraModal: Boolean
    },

    data() {
        return {
            text: '',

            modal: false
        }
    },

    watch: {
        'modal' (val) {
            this.$emit('update:cameraModal', val)
        },

        'cameraModal' (val) {
            this.modal = val
        }
    },

    methods: {
        snapshop(blob) {
            Tesseract.recognize(
                blob,
                'eng', // Language code
                { logger: (m) => console.log(m) } // Optional logger
            )
                .then(({ data: { text } }) => {
                    this.text = text
                })
                .catch((error) => {
                    console.error(error);
                });
        }
    }
}
</script>

<style scoped>
#txtScanner {
    transform: scale(0.3);
    transform-origin: 0 0 0;
    position: absolute;
}

.scale-handle {
    width: 288px;
    height: 70px;
    position: relative;
}
</style>
