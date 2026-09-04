import inertia from '@inertiajs/vite';
import AutoImport from 'unplugin-auto-import/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig, type Plugin } from 'vite';
import path from 'path';

const productionMarker = '.production';
const forbiddenProductionModules = [
    /(^|[/@])laravel[-/]boost([/@]|$)/i,
    /(^|[/@])laravel[-/]mcp([/@]|$)/i,
    /(^|[/@])mcp([/@]|$)/i,
    /@modelcontextprotocol/i,
];
const forbiddenProductionOutput = [
    '/_boost/',
    'boost.browser-logs',
    'browser-logger-active',
    'MCP server detected',
    '@modelcontextprotocol',
    'laravel-boost',
    'laravel/mcp',
];

function productionSecurityGuard(): Plugin {
    let isBuild = false;

    return {
        name: 'production-security-guard',
        enforce: 'post',
        configResolved(config) {
            isBuild = config.command === 'build';
        },
        configureServer() {
            rmSync(resolve('public/build', productionMarker), { force: true });
        },
        resolveId(source) {
            if (
                isBuild &&
                forbiddenProductionModules.some((pattern) =>
                    pattern.test(source),
                )
            ) {
                this.error(
                    `Development-only module cannot be included in a production build: ${source}`,
                );
            }

            return null;
        },
        generateBundle(_, bundle) {
            if (!isBuild) {
                return;
            }

            for (const output of Object.values(bundle)) {
                const contents =
                    output.type === 'chunk'
                        ? output.code
                        : output.source.toString();
                const forbiddenMarker = forbiddenProductionOutput.find(
                    (marker) => contents.includes(marker),
                );

                if (forbiddenMarker) {
                    this.error(
                        `Development-only marker found in production asset ${output.fileName}: ${forbiddenMarker}`,
                    );
                }
            }

            this.emitFile({
                type: 'asset',
                fileName: productionMarker,
                source: 'production\n',
            });
        },
    };
}

export default defineConfig({ 
    resolve: {
        alias: {
            '@/*': path.resolve(import.meta.dirname, '../resources/js/*'),
            '@shared': path.resolve(import.meta.dirname, '../resources/js/shared'),
        },
    }, 
    define: {
        __VUE_PROD_DEVTOOLS__: process.env.VITE_APP_ENV === 'local' ? true : false,
    },
    server: {
        host: process.env.VITE_HOST ? process.env.VITE_HOST : '0.0.0.0',
        port: process.env.VITE_APP_PORT ? parseInt(process.env.VITE_APP_PORT) : 5173,
        cors: true,
        hmr: {
            host: process.env.VITE_HMR_HOST ? process.env.VITE_HMR_HOST : 'localhost',
        },
        watch: {
            usePolling: process.env.VITE_USE_POLLING === 'true',
            ignored: ['**/storage/**', '**/vendor/**', '**/node_modules/**', '**/.git/**'],
        },
    },
    build: {
        chunkSizeWarningLimit: 600,
        rolldownOptions: {
            output: {
                minify: {
                    compress: {
                        dropConsole: false,
                        dropDebugger: false,
                    },
                },
            },
        },
    },
    plugins: [
        productionSecurityGuard(),
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
                    axios: [['default', 'axios']],
                },
            ],
            dts: 'resources/js/auto-imports.d.ts',
        }),
    ],
});
