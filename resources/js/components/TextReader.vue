<template>
    <modal v-model:show="modal" modal-title="Camera Text Reader" z-index="999999">
        <div class="scale-handle">
            <div class="position-relative" id="txtScanner">
                <label for="customRange2" class="form-label">Resolution: {{ range }}</label> <button @click="range=500" class="btn btn-light btn-sm">Set Default</button>
                <input v-model="range" type="range" class="form-range" step="100" min="500" max="3000" id="customRange2">
                <camera
                    @snapshot="snapshop"
                    :resolution="{ width: range, height: range }"
                >
                    <div v-if="selected.length > 0" class="card position-absolute w-100" style="bottom: 0">
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
        <div style="max-width: 288px">
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

            modal: false,

            range: 500
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
            this.$store.dispatch('setLoading', true)
            Tesseract.recognize(
                blob,
                'eng'
            )
                .then(({ data: { text } }) => {
                    this.words = text.replace(/[&#,+()$~%'":*?<>{}|]/g, ' ')
                        .replace(/-+/g, "-")
                        .replace(/_+/g, "_")
                        .replace(/\n/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim('-')
                        .split(' ')
                    this.$store.dispatch('setLoading', false)
                })
                .catch((error) => {
                    console.error(error);
                    this.$store.dispatch('setLoading', false)
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
