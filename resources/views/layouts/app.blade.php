<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen">
    <div id="app">
        <nav class="bg-white shadow-sm">
            <div class="container mx-auto px-4">
                <div class="flex justify-between items-center py-3">
                    <a class="text-lg font-semibold text-gray-800 no-underline" href="{{ url('/') }}">
                        {{ config('app.name', 'Laravel') }}
                    </a>

                    <div class="flex items-center gap-4">
                        @guest
                            @if (Route::has('login'))
                                <a class="text-sm text-gray-600 hover:text-gray-900 no-underline" href="{{ route('login') }}">{{ __('Login') }}</a>
                            @endif

                            @if (Route::has('register'))
                                <a class="text-sm text-gray-600 hover:text-gray-900 no-underline" href="{{ route('register') }}">{{ __('Register') }}</a>
                            @endif
                        @else
                            <span class="text-sm text-gray-700 font-medium">{{ Auth::user()->name }}</span>
                            <a class="text-sm text-gray-600 hover:text-gray-900 no-underline" href="{{ route('logout') }}"
                               onclick="event.preventDefault();
                                             document.getElementById('logout-form').submit();">
                                {{ __('Logout') }}
                            </a>

                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                                @csrf
                            </form>
                        @endguest
                    </div>
                </div>
            </div>
        </nav>

        <main class="py-6">
            @yield('content')
        </main>
    </div>
</body>
</html>
