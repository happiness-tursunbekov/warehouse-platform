<template>
    <modal v-model:show="modal" :modal-title="modalTitle">
        <form @submit.prevent="$emit('upload', files);files=[]">
            <slot/>
            <div class="mb-3">
                <label for="upload-file-modal" class="form-label">File</label>
                <input :accept="accept.join(',')" v-on:change="handleFiles" :multiple="multiple" type="file" class="form-control" id="upload-file-modal" :required="!files.length">
                <div class="position-relative mt-3">
                    <span ref="pasteDisplay" class="form-control text-center" style="height: 100px">Paste file here</span>
                    <input ref="pasteInput" @input="$event.target.value = ''" class="position-absolute" style="left:0;right:0;top:0;bottom:0;opacity:0.03" type="text" @drop="paste" @dragenter="onDragover" @mouseleave="onDragleave" @dragleave="onDragleave" />
                </div>
                <ul v-if="files.length > 0" class="list-group mt-3">
                    <li v-for="(file, key) in files" :key="key" class="list-group-item d-flex justify-content-between">
                        <img v-if="file.type.includes('image')" :src="URL.createObjectURL(file)" alt="img" style="max-height: 50px"/>
                        <span v-else>{{ file.name }}</span>
                        <button @click.prevent="files.splice(key, 1)" type="button" class="btn btn-sm btn-link" title="Remove"><i class="bi-trash"></i></button>
                    </li>
                </ul>
            </div>
            <div class="mb-3">
                <button :disabled="files.length === 0" type="submit" class="btn btn-success">Upload</button>
            </div>
        </form>
    </modal>
</template>

<script>
import Modal from "./Modal.vue";

export default {
    name: "FileUploadModal",
    components: {Modal},

    props: {
        show: Boolean,
        accept: Array,
        modalTitle: String,
        multiple: Boolean
    },

    data() {
        return {
            modal: this.show,
            files: [],
            clipboardItems: [],
            URL: URL
        }
    },

    watch: {
        'show' (val) {
            this.modal = val
        },

        'modal' (val) {
            this.$emit('update:show', val)
            if (!val) {
                this.files = []
            }
        }
    },

    created() {
        window.onpaste = e => {
            if (this.show) {
                this.paste(e)
            }
        }
    },

    methods: {
        checkFileType(typeStr) {
            const accept = this.accept.join(',')

            const type = typeStr.split('/')

            return type === '0' || accept.includes(type[0]) || accept.includes(type[1])
        },

        onDragleave() {
            this.$refs.pasteDisplay.innerText = 'Paste file here'
            this.$refs.pasteInput.setAttribute('type', 'text')
        },

        onDragover(e) {
            this.$refs.pasteInput.setAttribute('type', 'file')
            if (this.accept && this.accept.length > 0) {
                if (this.checkFileType(e.dataTransfer.items[0].type)) {
                    this.$refs.pasteDisplay.innerText = 'Drop here'
                } else {
                    this.$refs.pasteDisplay.innerText = 'Can\'t accept this type of file'
                }
            } else {
                this.$refs.pasteDisplay.innerText = 'Can\'t accept this type of file'
            }
        },

        handleFiles(e) {
            for (let i = 0; i < e.target.files.length; i++) {
                if (this.checkFileType(e.target.files[i].type)) {
                    this.files.push(e.target.files[i])
                } else {
                    this.$snotify.error('Can\'t accept this type of file')
                }
            }
        },

        paste(e) {
            let files;
            if (e.dataTransfer) {
                files = e.dataTransfer.files
            } else if (e.clipboardData) {
                files = e.clipboardData.files
                const url = e.clipboardData.getData('text/plain')
                if (url.startsWith('https://')) {
                    fetch(url).then(res => {
                        res.blob().then(blob => {
                            const file = new File([blob], 'file.' + blob.type.split('/')[1].replace('jpeg', 'jpg'), { type: blob.type })
                            this.files.push(file)
                        })
                    })
                    return false
                }
            } else {
                files = []
            }

            for (let i = 0; i < files.length; i++) {
                const file = files[i]
                if (this.checkFileType(file.type)) {
                    this.files.push(file)
                } else {
                    this.$snotify.error('Can\'t accept this type of file')
                }
            }

            e.preventDefault()
            this.$refs.pasteInput.setAttribute('type', 'text')
            this.$refs.pasteDisplay.innerText = 'Paste file here'
        }
    }
}
</script>

<style scoped>

</style>
