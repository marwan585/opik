<!DOCTYPE html>
<html>
    <head>
        <title>{{ config('app.name', 'Cloud Server') }}</title>

        {{-- Cloud Server: aggressively prevent browser caching of HTML pages
            so users always get the latest bundle reference after updates. --}}
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate, max-age=0">
        <meta http-equiv="Pragma" content="no-cache">
        <meta http-equiv="Expires" content="0">

        @section('meta')
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <meta name="robots" content="noindex">
            <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
            <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
            <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
            <link rel="manifest" href="/favicons/manifest.json">
            <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#bc6e3c">
            <link rel="shortcut icon" href="/favicons/favicon.ico">
            <meta name="msapplication-config" content="/favicons/browserconfig.xml">
            <meta name="theme-color" content="#0e4688">
        @show

        @section('user-data')
            @if(!is_null(Auth::user()))
                <script>
                    window.PterodactylUser = {!! json_encode(Auth::user()->toVueObject()) !!};
                </script>
            @endif
            @if(!empty($siteConfiguration))
                <script>
                    window.SiteConfiguration = {!! json_encode($siteConfiguration) !!};
                </script>
            @endif
            @if(!empty($cloudServerPopup))
                <script>
                    window.CloudServerPopup = {!! json_encode($cloudServerPopup) !!};
                </script>
            @endif
        @show

        @yield('assets')

        @include('layouts.scripts')
    </head>
    <body class="{{ $css['body'] ?? 'bg-neutral-50' }}">
        @section('content')
            @yield('above-container')
            @yield('container')
            @yield('below-container')
        @show
        @section('scripts')
            {{-- Cloud Server: append cache-buster query string to bundle URL
                so mobile browsers (Chrome Android, Safari iOS) always fetch
                fresh asset instead of using stale cached version. The hash
                is read from manifest.json so it changes whenever a new
                bundle is built. --}}
            @php
                $bundleSrc = $asset->url('main.js');
                $cacheBust = '?v=' . substr(md5_file(public_path('assets/manifest.json')), 0, 8);
            @endphp
            <script src="{{ $bundleSrc . $cacheBust }}" crossorigin="anonymous"></script>
        @show
    </body>
</html>
