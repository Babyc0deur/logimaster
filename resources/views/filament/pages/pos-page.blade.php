<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point de Vente - {{ filament()->getTenant()->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
    <style>
        html, body { 
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden; 
        }
        .filament-main { 
            padding: 0 !important;
            height: 100vh !important;
        }
        .fi-page { 
            padding: 0 !important;
            height: 100vh !important;
        }
        .product-tile { 
            aspect-ratio: 1;
            background-size: cover;
            background-position: center;
            position: relative;
            cursor: pointer;
            transition: transform 0.15s;
        }
        .product-tile:active { transform: scale(0.95); }
        @media (hover: hover) {
            .product-tile:hover { transform: scale(1.05); }
        }
        .product-tile::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 60%);
        }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* Mobile drawer */
        .cart-drawer {
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
        }
        .cart-drawer.open {
            transform: translateX(0);
        }
    </style>
</head>
<body class="bg-gray-100 h-screen overflow-hidden">
    <div class="h-screen flex flex-col" x-data="posSystem()">
        <!-- Top Bar -->
        <div class="bg-emerald-600 text-white px-3 sm:px-6 py-3 flex items-center justify-between shadow-lg">
            <div class="flex items-center gap-2 sm:gap-4">
                <h1 class="text-lg sm:text-2xl font-bold">{{ filament()->getTenant()->name }}</h1>
                <span class="hidden sm:inline text-emerald-200">|</span>
                <span class="hidden sm:inline text-emerald-100" x-text="currentTime"></span>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Mobile Cart Toggle -->
                <button @click="cartOpen = !cartOpen" class="lg:hidden px-3 py-2 bg-emerald-700 hover:bg-emerald-800 rounded-lg transition relative">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span x-show="cart.length > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center" x-text="cart.length"></span>
                </button>
                <span class="text-emerald-100 text-sm sm:text-base">{{ auth()->user()->name }}</span>
                <a href="{{ filament()->getUrl() }}" class="hidden sm:block px-4 py-2 bg-emerald-700 hover:bg-emerald-800 rounded-lg transition">
                    Retour
                </a>
            </div>
        </div>

        <div class="flex-1 flex overflow-hidden relative">
            <!-- Left: Products -->
            <div class="flex-1 flex flex-col bg-white">
                <!-- Categories -->
                <div class="border-b border-gray-200 px-2 sm:px-4 py-3">
                    <div class="flex gap-2 overflow-x-auto scrollbar-hide">
                        <button 
                            @click="selectedCategory = null" 
                            :class="selectedCategory === null ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="px-3 sm:px-6 py-2 sm:py-2.5 rounded-lg font-semibold whitespace-nowrap transition text-sm sm:text-base"
                        >
                            Tous
                        </button>
                        @foreach($this->getCategories() as $category)
                        <button 
                            @click="selectedCategory = {{ $category->id }}" 
                            :class="selectedCategory === {{ $category->id }} ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="px-3 sm:px-6 py-2 sm:py-2.5 rounded-lg font-semibold whitespace-nowrap transition text-sm sm:text-base"
                        >
                            {{ $category->name }}
                        </button>
                        @endforeach
                    </div>
                </div>

                <!-- Search -->
                <div class="px-2 sm:px-4 py-3 border-b border-gray-200">
                    <input 
                        type="text" 
                        x-model="search"
                        placeholder="🔍 Rechercher..."
                        class="w-full px-3 sm:px-4 py-2 sm:py-3 border-2 border-gray-300 rounded-lg text-base sm:text-lg focus:border-emerald-500 focus:outline-none"
                    />
                </div>

                <!-- Products Grid -->
                <div class="flex-1 overflow-y-auto p-2 sm:p-4">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3">
                        @foreach($this->getProducts() as $product)
                        <div 
                            @click="addToCart({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->price }}, {{ $product->stock_quantity }})"
                            class="product-tile rounded-xl shadow-md overflow-hidden"
                            style="background-image: url('{{ $product->image ? asset($product->image) : '' }}'); background-color: {{ $product->image ? 'transparent' : '#10b981' }};"
                        >
                            @if(!$product->image)
                            <div class="absolute inset-0 flex items-center justify-center">
                                <svg class="w-12 sm:w-16 h-12 sm:h-16 text-white opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            @endif
                            <div class="absolute bottom-0 left-0 right-0 p-2 sm:p-3 z-10">
                                <p class="text-white font-bold text-xs sm:text-sm leading-tight mb-1">{{ $product->name }}</p>
                                <p class="text-emerald-300 font-bold text-base sm:text-lg">{{ number_format($product->price, 0, ',', ' ') }} F CFA</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Right: Cart (Desktop) -->
            <div class="hidden lg:flex w-[420px] bg-gray-50 flex-col shadow-2xl">
                <div x-ref="cartContent">
                    @include('filament.pages.partials.pos-cart')
                </div>
            </div>

            <!-- Mobile Cart Drawer -->
            <div 
                class="lg:hidden fixed inset-y-0 right-0 w-full sm:w-96 bg-gray-50 shadow-2xl z-50 cart-drawer flex flex-col"
                :class="{ 'open': cartOpen }"
            >
                <!-- Close Button -->
                <div class="bg-gray-800 text-white p-4 flex items-center justify-between">
                    <h2 class="text-xl font-bold">Panier</h2>
                    <button @click="cartOpen = false" class="text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="flex-1 overflow-y-auto">
                    @include('filament.pages.partials.pos-cart')
                </div>
            </div>

            <!-- Mobile Overlay -->
            <div 
                x-show="cartOpen" 
                @click="cartOpen = false"
                class="lg:hidden fixed inset-0 bg-black bg-opacity-50 z-40"
                x-transition:enter="transition-opacity ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
            ></div>
        </div>
    </div>

    @livewireScripts
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        function posSystem() {
            return {
                cart: [],
                search: '',
                selectedCategory: null,
                paymentMethod: 'cash',
                amountReceived: 0,
                currentTime: '',
                cartOpen: false,

                init() {
                    this.updateTime();
                    setInterval(() => this.updateTime(), 1000);
                },

                updateTime() {
                    const now = new Date();
                    this.currentTime = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                },

                addToCart(id, name, price, stock) {
                    const existingItem = this.cart.find(item => item.id === id);
                    if (existingItem) {
                        if (existingItem.quantity < stock) {
                            existingItem.quantity++;
                        } else {
                            alert('Stock insuffisant');
                        }
                    } else {
                        this.cart.push({ id, name, price, quantity: 1, stock });
                    }
                    // Open cart on mobile when adding item
                    if (window.innerWidth < 1024) {
                        this.cartOpen = true;
                    }
                },

                removeFromCart(index) {
                    this.cart.splice(index, 1);
                },

                updateQuantity(index, change) {
                    const item = this.cart[index];
                    const newQty = item.quantity + change;
                    if (newQty > 0 && newQty <= item.stock) {
                        item.quantity = newQty;
                    } else if (newQty <= 0) {
                        this.removeFromCart(index);
                    }
                },

                clearCart() {
                    if (confirm('Vider le panier ?')) {
                        this.cart = [];
                        this.amountReceived = 0;
                    }
                },

                getTotal() {
                    return this.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                },

                async checkout() {
                    if (this.cart.length === 0) return;
                    
                    if (this.paymentMethod === 'cash' && this.amountReceived < this.getTotal()) {
                        alert('Montant reçu insuffisant');
                        return;
                    }

                    @this.call('checkoutFromAlpine', {
                        cart: this.cart,
                        paymentMethod: this.paymentMethod,
                        amountReceived: this.amountReceived
                    }).then(() => {
                        alert('Vente enregistrée avec succès!');
                        this.cart = [];
                        this.amountReceived = 0;
                        this.cartOpen = false;
                    }).catch(() => {
                        alert('Erreur lors de l\'enregistrement de la vente');
                    });
                }
            }
        }
    </script>
</body>
</html>
