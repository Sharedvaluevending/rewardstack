<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#8B5CF6">
        <link rel="manifest" href="/manifest.webmanifest">

        <!-- PWA / iOS install metadata -->
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Revenue QR">

        <title inertia>{{ config('app.name', 'Revenue QR') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://rsms.me/">
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css">

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="/pwa/icons/icon-192.png">
        <link rel="shortcut icon" type="image/png" href="/pwa/icons/icon-192.png">
        <link rel="apple-touch-icon" href="/pwa/icons/icon-512.png">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js'])
        @inertiaHead

        <!-- Loading Screen Styles -->
        <style>
            #app-loader {
                position: fixed;
                inset: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #111827 0%, #1f2937 50%, #111827 100%);
                z-index: 9999;
                transition: opacity 0.3s ease-out;
            }
            #app-loader.fade-out {
                opacity: 0;
                pointer-events: none;
            }
            .loader-logo {
                width: 80px;
                height: 80px;
                margin-bottom: 24px;
                animation: pulse 2s ease-in-out infinite;
            }
            .loader-title {
                font-family: 'Inter', system-ui, sans-serif;
                font-size: 28px;
                font-weight: 700;
                background: linear-gradient(135deg, #8B5CF6 0%, #EC4899 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                margin-bottom: 16px;
            }
            .loader-spinner {
                width: 32px;
                height: 32px;
                border: 3px solid rgba(139, 92, 246, 0.2);
                border-top-color: #8B5CF6;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            @keyframes pulse {
                0%, 100% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.05); opacity: 0.8; }
            }
        </style>
    </head>
    <body class="font-sans antialiased bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 min-h-screen text-white">
        <!-- Loading Screen -->
        <div id="app-loader">
            <svg class="loader-logo" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="loaderGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#8B5CF6"/>
                        <stop offset="100%" style="stop-color:#EC4899"/>
                    </linearGradient>
                </defs>
                <rect width="512" height="512" rx="96" fill="url(#loaderGrad)"/>
                <g fill="white">
                    <rect x="64" y="64" width="112" height="112" rx="16"/>
                    <rect x="80" y="80" width="80" height="80" rx="8" fill="url(#loaderGrad)"/>
                    <rect x="104" y="104" width="32" height="32" rx="4" fill="white"/>
                    <rect x="336" y="64" width="112" height="112" rx="16"/>
                    <rect x="352" y="80" width="80" height="80" rx="8" fill="url(#loaderGrad)"/>
                    <rect x="376" y="104" width="32" height="32" rx="4" fill="white"/>
                    <rect x="64" y="336" width="112" height="112" rx="16"/>
                    <rect x="80" y="352" width="80" height="80" rx="8" fill="url(#loaderGrad)"/>
                    <rect x="104" y="376" width="32" height="32" rx="4" fill="white"/>
                    <rect x="200" y="200" width="112" height="112" rx="16"/>
                </g>
            </svg>
            <div class="loader-title">Revenue QR</div>
            <div class="loader-spinner"></div>
        </div>

        @inertia

        <script>
            // Hide loader when app is ready
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    const loader = document.getElementById('app-loader');
                    if (loader) {
                        loader.classList.add('fade-out');
                        setTimeout(function() {
                            loader.remove();
                        }, 300);
                    }
                }, 100);
            });
        </script>
    </body>
</html>
