<!-- Cart Header -->
<div class="bg-gray-800 text-white p-4">
    <div class="flex items-center justify-between mb-2">
        <h2 class="text-xl font-bold">Commande</h2>
        <button @click="clearCart()" class="text-gray-400 hover:text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
        </button>
    </div>
    <p class="text-gray-400 text-sm" x-text="'Articles: ' + cart.length"></p>
</div>

<!-- Cart Items -->
<div class="flex-1 overflow-y-auto px-4 py-3 space-y-2">
    <template x-for="(item, index) in cart" :key="index">
        <div class="bg-white rounded-lg p-3 shadow-sm">
            <div class="flex justify-between items-start mb-2">
                <p class="font-semibold text-gray-800 flex-1 text-sm" x-text="item.name"></p>
                <button @click="removeFromCart(index)" class="text-red-500 hover:text-red-700 ml-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button @click="updateQuantity(index, -1)" class="w-8 h-8 bg-gray-200 hover:bg-gray-300 rounded flex items-center justify-center font-bold active:scale-95">-</button>
                    <span class="w-12 text-center font-bold" x-text="item.quantity"></span>
                    <button @click="updateQuantity(index, 1)" class="w-8 h-8 bg-gray-200 hover:bg-gray-300 rounded flex items-center justify-center font-bold active:scale-95">+</button>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500" x-text="item.price.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' F CFA'"></p>
                    <p class="font-bold text-gray-800" x-text="(item.price * item.quantity).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' F CFA'"></p>
                </div>
            </div>
        </div>
    </template>
    
    <div x-show="cart.length === 0" class="text-center py-12 sm:py-20 text-gray-400">
        <svg class="w-16 sm:w-20 h-16 sm:h-20 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
        </svg>
        <p class="font-medium">Panier vide</p>
        <p class="text-sm mt-1">Sélectionnez des produits</p>
    </div>
</div>

<!-- Cart Footer -->
<div x-show="cart.length > 0" class="border-t border-gray-200 p-4 space-y-3 bg-white">
    <!-- Subtotal -->
    <div class="flex justify-between text-base sm:text-lg">
        <span class="text-gray-600">Sous-total</span>
        <span class="font-semibold" x-text="getTotal().toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' F CFA'"></span>
    </div>

    <!-- Total -->
    <div class="flex justify-between items-center py-3 border-t-2 border-gray-300">
        <span class="text-xl sm:text-2xl font-bold">TOTAL</span>
        <span class="text-2xl sm:text-3xl font-bold text-emerald-600" x-text="getTotal().toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' F CFA'"></span>
    </div>

    <!-- Payment Methods -->
    <div class="grid grid-cols-3 gap-2">
        <button 
            @click="paymentMethod = 'cash'"
            :class="paymentMethod === 'cash' ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-700'"
            class="py-2 sm:py-3 rounded-lg font-semibold transition text-xs sm:text-sm"
        >
            💵 Espèces
        </button>
        <button 
            @click="paymentMethod = 'card'"
            :class="paymentMethod === 'card' ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-700'"
            class="py-2 sm:py-3 rounded-lg font-semibold transition text-xs sm:text-sm"
        >
            💳 Carte
        </button>
        <button 
            @click="paymentMethod = 'transfer'"
            :class="paymentMethod === 'transfer' ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-700'"
            class="py-2 sm:py-3 rounded-lg font-semibold transition text-xs sm:text-sm"
        >
            🏦 Vir.
        </button>
    </div>

    <!-- Cash Input -->
    <div x-show="paymentMethod === 'cash'">
        <input 
            type="number" 
            x-model="amountReceived"
            step="0.01"
            placeholder="Montant reçu"
            class="w-full px-3 sm:px-4 py-2 sm:py-3 border-2 border-gray-300 rounded-lg text-base sm:text-lg font-semibold focus:border-emerald-500 focus:outline-none"
        />
        <div x-show="amountReceived > 0" class="mt-2 bg-emerald-50 p-3 rounded-lg">
            <div class="flex justify-between items-center">
                <span class="font-medium text-gray-700 text-sm sm:text-base">Rendu monnaie:</span>
                <span class="text-xl sm:text-2xl font-bold text-emerald-600" x-text="(amountReceived - getTotal()).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' F CFA'"></span>
            </div>
        </div>
    </div>

    <!-- Checkout Button -->
    <button 
        @click="checkout()"
        class="w-full py-4 sm:py-5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-lg sm:text-xl shadow-lg transition transform active:scale-95"
    >
        VALIDER LA VENTE
    </button>
</div>
