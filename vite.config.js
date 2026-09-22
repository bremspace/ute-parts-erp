import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        VitePWA({
            registerType: 'autoUpdate',
            // 'script-defer' => plugin generates registerSW.js artifact (AC verifikasi).
            // Laravel tidak punya HTML build-time, jadi tidak ada <script> ter-inject;
            // registrasi runtime tetap SATU: import virtual:pwa-register di resources/js/app.js.
            injectRegister: 'script-defer',
            includeAssets: ['favicon.ico', 'robots.txt', 'icons/icon.svg'],
            manifest: {
                name: 'Ute Parts',
                short_name: 'Ute Parts',
                description: 'ERP, POS & Marketplace untuk sparepart & servis HP',
                theme_color: '#5B4FE9',
                background_color: '#070A14',
                display: 'standalone',
                orientation: 'portrait-primary',
                scope: '/',
                start_url: '/app/dashboard',
                lang: 'id',
                icons: [
                    {
                        src: '/icons/icon.svg',
                        sizes: 'any',
                        type: 'image/svg+xml',
                        purpose: 'any maskable'
                    },
                    {
                        src: '/icons/icon-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any maskable'
                    },
                    {
                        src: '/icons/icon-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any maskable'
                    }
                ],
                shortcuts: [
                    {
                        name: 'Kasir POS',
                        short_name: 'POS',
                        description: 'Buka kasir POS',
                        url: '/app/pos',
                        icons: [{ src: '/icons/icon-192x192.png', sizes: '192x192' }]
                    },
                    {
                        name: 'Gudang & Stok',
                        short_name: 'WMS',
                        description: 'Kelola stok dan gudang',
                        url: '/app/wms',
                        icons: [{ src: '/icons/icon-192x192.png', sizes: '192x192' }]
                    },
                    {
                        name: 'Servis HP',
                        short_name: 'Servis',
                        description: 'Kelola tiket servis HP',
                        url: '/app/servis',
                        icons: [{ src: '/icons/icon-192x192.png', sizes: '192x192' }]
                    }
                ]
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
                // Offline fallback: pastikan /offline.html ter-precache & dipakai utk navigasi (T-31)
                navigateFallback: '/offline.html',
                navigateFallbackDenylist: [/^\/api\//, /^\/build\//],
                additionalManifestEntries: [{ url: '/offline.html', revision: null }],
                runtimeCaching: [
                    {
                        // Aset CSS/JS (non-precache): stale-while-revalidate sesuai AC T-31
                        urlPattern: /\.(?:css|js|mjs)$/i,
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'assets-cache',
                            expiration: {
                                maxEntries: 50,
                                maxAgeSeconds: 60 * 60 * 24 * 30, // 30 days
                                purgeOnQuotaError: true,
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                    {
                        urlPattern: /^https:\/\/fonts\.googleapis\.com\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'google-fonts-cache',
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 60 * 60 * 24 * 365 // 1 year
                            },
                            cacheableResponse: {
                                statuses: [0, 200]
                            }
                        }
                    },
                    {
                        urlPattern: /^https:\/\/fonts\.gstatic\.com\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'gstatic-fonts-cache',
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 60 * 60 * 24 * 365
                            },
                            cacheableResponse: {
                                statuses: [0, 200]
                            }
                        }
                    },
                    {
                        urlPattern: /\/api\/.*/i,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'api-cache',
                            expiration: {
                                maxEntries: 100,
                                maxAgeSeconds: 60 * 5 // 5 minutes
                            },
                            networkTimeoutSeconds: 10,
                            cacheableResponse: {
                                statuses: [0, 200]
                            }
                        }
                    },
                    {
                        urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp)$/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'images-cache',
                            expiration: {
                                maxEntries: 100,
                                maxAgeSeconds: 60 * 60 * 24 * 30 // 30 days
                            },
                            cacheableResponse: {
                                statuses: [0, 200]
                            }
                        }
                    }
                ],
                skipWaiting: true,
                clientsClaim: true
            }
        })
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
