<template>
    <modal v-model:show="modal" modal-title="Camera Text Reader" z-index="999999">
        <div class="scale-handle">
            <div class="position-relative" id="txtScanner">
                <camera
                    @snapshot="snapshop"
                    :resolution="{ width: 1000, height: 1000 }"
                >
                    <div v-if="selected.length > 0" class="card position-absolute" style="bottom: 0">
                        <div class="card-body">
                            <strong>Selected: </strong>
                            <span v-for="(word, key) in selected" :key="key" class="ms-1 border-bottom">{{ word }} <i @click.prevent="selected.splice(key, 1)" class="bi-x-circle" style="cursor: pointer"></i></span>
                        </div>
                        <div class="card-footer">
                            <button @click="processText" type="button" class="btn btn-sm btn-success">Go!</button>
                        </div>
                    </div>
                </camera>
            </div>
        </div>
        <div>
            <div v-for="(word, key) in words" :key="key" @click.prevent="!selected.find(w => w === word) ? selected.push(word) : null" :class="{ 'bg-success text-light': selected.find(w => w === word) }" class="card p-1 m-1 d-inline-block border-bottom" style="cursor: pointer" title="select">{{ word }}</div>
        </div>
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
            words: [],

            selected: [],

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
                'eng'
            )
                .then(({ data: { text } }) => {
                    this.words = text.replace(/[&#,+()$~%'":*?<>{}]/g, '').replace(/\n/g, ' ').trim().split(' ')
                })
                .catch((error) => {
                    console.error(error);
                });
        },

        processText() {
            this.$store.dispatch('setReaderText', this.selected.join(' '))
            this.modal = false
            this.selected = []
            this.words = []
        }
    }
}
</script>

<style scoped>
.scale-handle {
    width: 288px;
    position: relative;
}
</style>
