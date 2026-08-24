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
    build: {
        chunkSizeWarningLimit: 600,
        rolldownOptions: {
            output: {
                minify: {
                    compress: {
                        dropConsole: true,
                        dropDebugger: true,
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
