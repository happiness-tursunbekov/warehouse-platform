<template>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Settings</h1>
    </div>
    <div class="row">
        <div class="col-6">
            <div class="form-check form-switch">
                <input v-model="account.reportMode" @change="confirm" class="form-check-input" type="checkbox" id="flexSwitchCheckChecked">
                <label class="form-check-label" for="flexSwitchCheckChecked">Report Mode</label>
            </div>
            <span v-if="account.reportMode" class="text-danger">Once you turn off the report mode, all your reports will be removed. Do not forget to download them before turning it off :)</span>
            <span v-else class="text-success">Once you turn on the report mode, all your actions will be saved temporarily while report mode is turned off :)</span>
        </div>
    </div>
</template>

<script>
export default {
    name: "Settings",

    data() {
        return {
            account: this.$store.getters.user
        }
    },

    methods: {
        updateAccount() {
            axios.put('/api/auth/user', {
                reportMode: this.account.reportMode
            }).then(res => {
                this.$store.dispatch('setUser', res.data)
            })
        },

        confirm() {
            if (!this.account.reportMode) {
                this.account.reportMode = true
                this.$snotify.confirm('Have you downloaded all reports?', 'Confirm', {
                    timeout: false,
                    showProgressBar: false,
                    closeOnClick: false,
                    backdrop: 0.5,
                    buttons: [
                        {
                            text: 'Yes',
                            action: (toast) => {
                                this.account.reportMode = false
                                this.updateAccount()
                                this.$snotify.remove(toast.id);
                            }
                        },
                        {
                            text: 'No',
                            action: (toast) => {
                                this.$snotify.remove(toast.id)
                            }
                        }
                    ]
                })
            } else {
                this.updateAccount()
            }
        }
    }
}
</script>

<style scoped>

</style>
