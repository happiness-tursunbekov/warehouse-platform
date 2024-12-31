<template>
    <modal v-model:show="modal">
        <camera
            @snapshot="snapshop"
            :resolution="{ width: 2400, height: 3000 }"
            :constraints="{ width: 240, height: 300 }"
        />
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

</style>
