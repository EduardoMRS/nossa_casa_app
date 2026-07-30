import inertia from '@inertiajs/vite';
import AutoImport from 'unplugin-auto-import/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
        AutoImport({
            imports: [
                {
                    // Diz ao Vite: toda vez que eu digitar "axios", faça o "import axios from 'axios'" automaticamente
                    'axios': [
                        ['default', 'axios'] 
                    ]
                }
            ],
            // Ele vai gerar esse arquivo para o TypeScript reconhecer que o axios existe globalmente
            dts: 'resources/js/auto-imports.d.ts', 
        }),
    ],
});
