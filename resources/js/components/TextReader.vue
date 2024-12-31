<template>
    <modal v-model:show="modal">
        <camera
            @snapshot="snapshop"
        />
        {{ text }}
    </modal>
</template>

<script>
import Camera from "./camera/Camera.vue";
import OCRAD from 'ocradjs-browser.js';
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
            this.text = OCRAD(blob)
        }
    }
}
</script>

<style scoped>

</style>
