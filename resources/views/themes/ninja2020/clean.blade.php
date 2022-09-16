<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <!-- Error: {{ session('error') }} -->

        @if (config('services.analytics.tracking_id'))
            <script async src="https://www.googletagmanager.com/gtag/js?id=UA-122229484-1"></script>
            <script>
                window.dataLayer = window.dataLayer || [];

                function gtag() {
                    dataLayer.push(arguments);
                }

                gtag('js', new Date());
                gtag('config', '{{ config('services.analytics.tracking_id') }}', {'anonymize_ip': true});

                function trackEvent(category, action) {
                    ga('send', 'event', category, action, this.src);
                }
            </script>
            <script>
                Vue.config.devtools = true;
            </script>
        @else
            <script>
                function gtag() {
                }
            </script>
        @endif

        <!-- Title -->
        @auth()
            <title>@yield('meta_title', '') — {{ auth()->guard('contact')->user()->user->account->isPaid() ? auth()->guard('contact')->user()->company->present()->name() : 'Invoice Ninja' }}</title>
        @endauth

        @guest
            <title>@yield('meta_title', '') — {{ config('app.name') }}</title>
        @endguest

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="@yield('meta_description')"/>

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Scripts -->
        <script src="{{ mix('js/app.js') }}" defer></script>
        <script src="{{ asset('vendor/alpinejs@2.8.2/alpine.js') }}" defer></script>

        <!-- Fonts -->
        <link rel="dns-prefetch" href="https://fonts.gstatic.com">
        <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet" type="text/css" defer>

        <!-- Styles -->
        <link href="{{ mix('css/app.css') }}" rel="stylesheet">
        {{-- <link href="{{ mix('favicon.png') }}" rel="shortcut icon" type="image/png"> --}}

        <link rel="canonical" href="{{ config('ninja.app_url') }}/{{ request()->path() }}"/>

        {{-- Feel free to push anything to header using @push('header') --}}
        @stack('head')

        @livewireStyles

        <link rel="stylesheet" type="text/css" href="{{ asset('vendor/cookieconsent@3/cookieconsent.min.css') }}" />
    </head>

    <body class="antialiased">
        @if(session()->has('message'))
            <div class="py-1 text-sm text-center text-white bg-primary disposable-alert">
                {{ session('message') }}
            </div>
        @endif

        @yield('body')

        @livewireScripts

        <script src="{{ asset('vendor/cookieconsent@3/cookieconsent.min.js') }}" data-cfasync="false"></script>
        <script>
            window.addEventListener("load", function(){
                if (! window.cookieconsent) {
                    return;
                }
                window.cookieconsent.initialise({
                    "palette": {
                        "popup": {
                            "background": "#000"
                        },
                        "button": {
                            "background": "#f1d600"
                        },
                    },
                    "content": {
                        "href": "{{ config('ninja.privacy_policy_url.hosted') }}",
                        "message": "This website uses cookies to ensure you get the best experience on our website.",
                        "dismiss": "Got it!",
                        "link": "Learn more",
                    }
                })}
            );
        </script>
    </body>

    <footer>
        @yield('footer')
        @stack('footer')
    </footer>

</html>
