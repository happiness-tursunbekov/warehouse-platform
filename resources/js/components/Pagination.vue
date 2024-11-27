<script>
export default {
    name: "Pagination",

    props: {
        meta: Object
    }
}
</script>

<template>
    <div v-if="meta.total > 0" class="d-inline-block text-nowrap">
        <button @click.prevent="$emit('change', meta.currentPage-1)" :disabled="meta.currentPage === 1" type="button" class="btn btn-light btn-sm"><</button>
        {{ meta.currentPage === 1 ? 1 : meta.perPage * (meta.currentPage - 1) }} - {{ meta.totalPages > meta.currentPage ? meta.perPage * meta.currentPage : meta.total }} of {{ meta.total }}
        <button @click.prevent="$emit('change', meta.currentPage+1)" :disabled="meta.currentPage === meta.totalPages" type="button" class="btn btn-light btn-sm">></button>
    </div>
</template>

<style scoped>

</style>
