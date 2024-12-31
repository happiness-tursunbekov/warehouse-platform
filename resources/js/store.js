import { createStore } from 'vuex'

const recalculateTotal = cart => {
    let total = {
        price: 0,
        qty: 0
    }
    for (let i= 0; i < cart.items.length; i++) {
        total.price += cart.items[i].product.cost * cart.items[i].qty
        total.qty += cart.items[i].qty
    }

    total.price = Math.round((total.price + Number.EPSILON) * 100) / 100

    cart.total = total

    if (cart.items.length > 0) {
        localStorage.setItem('cart', JSON.stringify(cart))
    } else {
        localStorage.removeItem('cart');
    }

    return total
}

// Create a new store instance.
const store = createStore({
    state () {
        let cart = null
        const cartData = localStorage.getItem('cart')

        try {
            cart = JSON.parse(cartData)
        } catch (e) {

        }

        return {
            loading: false,
            barcode: '',
            cameraBarcodeReaderModal: false,
            textReaderModal: false,
            cart: cart || {
                items: [],
                total: {
                    price: 0,
                    qty: 0
                }
            },
            user: null
        }
    },
    mutations: {
        SET_LOADING (state, val) {
            state.loading = val
        },

        SET_BARCODE (state, val) {
            state.barcode = val
        },

        SET_BARCODE_MODAL (state, val) {
            state.cameraBarcodeReaderModal = val
        },

        SET_TEXT_MODAL (state, val) {
            state.textReaderModal = val
        },

        ADD_ITEM_TO_CART (state, val) {
            const itemIndex = state.cart.items.findIndex(item => item.product.id === val.product.id)
            if (itemIndex > -1) {
                if (state.cart.items[itemIndex].qty + val.qty > state.cart.items[itemIndex].product.onHand) {
                    state.cart.items[itemIndex].qty = state.cart.items[itemIndex].product.onHand
                } else {
                    state.cart.items[itemIndex].qty += val.qty
                }
            } else {
                if (val.qty > val.product.onHand)
                    val.qty = val.product.onHand
                state.cart.items.push(val)
            }
            state.cart.total = recalculateTotal(state.cart)
        },

        REMOVE_ITEM_FROM_CART(state, val) {
            state.cart.items = state.cart.items.filter(item => item.product.id !== val)
            state.cart.total = recalculateTotal(state.cart)
        },

        RESET_CART(state) {
            state.cart.items = []
            state.cart.total = recalculateTotal(state.cart)
        },

        SET_USER(state, val) {
            state.user = val
        }
    },

    actions: {
        setLoading({ commit }, val) {
            commit('SET_LOADING', val)
        },

        cameraBarcodeReaderModal({ commit }, val) {
            commit('SET_BARCODE_MODAL', val)
        },

        textReaderModal({ commit }, val) {
            commit('SET_TEXT_MODAL', val)
        },

        setBarcode({ commit, state }, val) {
            if (state.barcode === val) {
                commit('SET_BARCODE', '')
                setTimeout(() => {
                    commit('SET_BARCODE', val)
                })
            } else commit('SET_BARCODE', val)
        },

        addItemToCart({ commit }, val) {
            commit('ADD_ITEM_TO_CART', val)
        },

        removeItemFromCart({ commit }, val) {
            commit('REMOVE_ITEM_FROM_CART', val)
        },

        resetCart({ commit }) {
            commit('RESET_CART')
        },

        setUser({ commit }, val) {
            commit('SET_USER', val)
        }
    },

    getters: {
        loading(state) {
            return state.loading
        },

        barcode(state) {
            return state.barcode
        },

        cameraBarcodeReaderModal(state) {
            return state.cameraBarcodeReaderModal
        },

        textReaderModal(state) {
            return state.textReaderModal
        },

        cart(state) {
            return state.cart
        },

        user(state) {
            return state.user
        }
    }
})

export default store;
