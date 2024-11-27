<template>
    <main class="d-flex align-items-center py-4 bg-body-tertiary h-100 position-absolute w-100">
        <div class="form-signin w-100 m-auto">
            <form @submit.prevent="signIn">
                <h1 class="h3 mb-3 fw-normal">Please sign in</h1>

                <div class="form-floating">
                    <input v-model="loginForm.email" name="email" type="email" class="form-control" id="floatingInput" placeholder="name@example.com" required>
                    <label for="floatingInput">Email address</label>
                </div>
                <div class="form-floating">
                    <input v-model="loginForm.password" name="password" type="password" class="form-control" id="floatingPassword" placeholder="Password" required>
                    <label for="floatingPassword">Password</label>
                </div>

                <div class="form-check text-start my-3">
                    <input class="form-check-input" type="checkbox" value="remember-me" id="flexCheckDefault">
                    <label class="form-check-label" for="flexCheckDefault">
                        Remember me
                    </label>
                </div>
                <button class="btn btn-primary w-100 py-2" type="submit">Sign in</button>
                <p class="mt-5 mb-3 text-body-secondary">© Binyod Store 2024</p>
            </form>
        </div>
    </main>
</template>

<script>
export default {
    name: "Login",

    data() {
        return {
            loginForm: {
                email: '',
                password: '',
                deviceName: navigator.userAgent
            }
        }
    },

    methods: {
        signIn() {
            axios.post('/api/auth/login', this.loginForm).then(res => {

                const token = `Bearer ${res.data}`

                localStorage.setItem('token', token);
                axios.defaults.headers['Authorization'] = token
                axios.get('/api/auth/user').then(res1 => {
                    this.$store.dispatch('setUser', res1.data)
                    this.$router.push({
                        name: "homepage"
                    })
                })
            })
        }
    }
}
</script>

<style scoped>
.form-signin {
    max-width: 330px;
    padding: 1rem;
}

.form-signin .form-floating:focus-within {
    z-index: 2;
}

.form-signin input[type="email"] {
    margin-bottom: -1px;
    border-bottom-right-radius: 0;
    border-bottom-left-radius: 0;
}

.form-signin input[type="password"] {
    margin-bottom: 10px;
    border-top-left-radius: 0;
    border-top-right-radius: 0;
}

</style>
