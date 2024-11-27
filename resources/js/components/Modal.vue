<script>
export default {
    name: "Modal",

    props: {
        show: Boolean,
        modalTitle: String,
        zIndex: [Number,String]
    },

    methods: {
        close() {
            this.$emit('update:show', false)
            this.$emit('close')
        }
    }
}
</script>

<template>
    <div v-if="show" class="modal  d-block p-4 py-md-5" :style="{background: 'rgba(0,0,0,0.5)', zIndex: zIndex}" tabindex="-1" role="dialog" id="modalSheet">
        <div class="modal-dialog" role="document">
            <div class="modal-content rounded-4 shadow pb-2">
                <div class="modal-header border-bottom-0">
                    <h1 v-if="modalTitle" class="modal-title fs-5"><svg class="bi"><use xlink:href="#qr-code-scan"/></svg> {{ modalTitle }}</h1>
                    <button @click="close" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-0">
                    <slot/>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.modal {
    text-align: center;
}
.modal-dialog { /* Width */
    text-align: left; /* you'll likely want this */
    max-width: 100%;
    width: auto !important;
    display: inline-block;
}
</style>
