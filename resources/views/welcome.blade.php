<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Inventory SaaS') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .gradient-text {
            background: linear-gradient(135deg, #60A5FA 0%, #A78BFA 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="bg-slate-900 text-white min-h-screen flex flex-col relative overflow-x-hidden">
    
    <!-- Background Gradients -->
    <div class="fixed inset-0 z-0 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-600/20 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-purple-600/20 rounded-full blur-[120px]"></div>
    </div>

    <!-- Navigation -->
    <nav class="relative z-10 w-full px-6 py-6 flex justify-between items-center max-w-7xl mx-auto">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
            </div>
            <span class="text-xl font-bold tracking-tight">Inventory<span class="text-blue-400">SaaS</span></span>
        </div>
        <div class="flex items-center gap-4">
            @auth
                <a href="{{ url('/admin') }}" class="px-5 py-2.5 rounded-full bg-white/10 hover:bg-white/20 transition font-medium border border-white/10">Dashboard</a>
            @else
                <a href="{{ url('/admin') }}" class="px-5 py-2.5 rounded-full bg-white/10 hover:bg-white/20 transition font-medium border border-white/10">Log in</a>
            @endauth
        </div>
    </nav>

    <!-- Hero Section -->
    <main class="relative z-10 flex-grow flex flex-col items-center justify-center px-6 text-center max-w-5xl mx-auto mt-10 lg:mt-0">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300 text-sm font-medium mb-8 animate-fade-in-up">
            <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
            New: Point of Sale System Live
        </div>
        
        <h1 class="text-5xl lg:text-7xl font-bold mb-6 leading-tight tracking-tight">
            Manage your inventory <br>
            <span class="gradient-text">with absolute precision</span>
        </h1>
        
        <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto leading-relaxed">
            A powerful, multi-store inventory management system designed for modern businesses. 
            Track stock, manage sales, and grow your business with our all-in-one platform.
        </p>
        
        <div class="flex flex-col sm:flex-row gap-4 w-full justify-center">
            @auth
                <a href="{{ url('/admin') }}" class="px-8 py-4 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-500 hover:to-purple-500 transition font-bold text-lg shadow-lg shadow-blue-900/20 flex items-center justify-center gap-2">
                    Go to Dashboard
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                </a>
            @else
                <a href="{{ url('/admin') }}" class="px-8 py-4 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-500 hover:to-purple-500 transition font-bold text-lg shadow-lg shadow-blue-900/20 flex items-center justify-center gap-2">
                    Get Started
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                </a>
            @endauth
        </div>

        <!-- Features Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-20 w-full text-left">
            <div class="glass p-6 rounded-2xl hover:bg-white/10 transition duration-300">
                <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center mb-4 text-blue-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                </div>
                <h3 class="text-xl font-bold mb-2">Real-time Tracking</h3>
                <p class="text-slate-400">Monitor stock levels across multiple stores instantly. Never run out of best-sellers again.</p>
            </div>
            <div class="glass p-6 rounded-2xl hover:bg-white/10 transition duration-300">
                <div class="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center mb-4 text-purple-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <h3 class="text-xl font-bold mb-2">Point of Sale</h3>
                <p class="text-slate-400">Streamlined checkout process with barcode support, receipt generation, and customer management.</p>
            </div>
            <div class="glass p-6 rounded-2xl hover:bg-white/10 transition duration-300">
                <div class="w-12 h-12 rounded-xl bg-green-500/20 flex items-center justify-center mb-4 text-green-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
                <h3 class="text-xl font-bold mb-2">Advanced Analytics</h3>
                <p class="text-slate-400">Gain insights into sales trends, profit margins, and employee performance with detailed reports.</p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="relative z-10 w-full py-8 text-center text-slate-500 text-sm mt-20 border-t border-white/5">
        <p>&copy; {{ date('Y') }} Inventory SaaS. All rights reserved.</p>
    </footer>

</body>
</html>
