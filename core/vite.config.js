import { defineConfig } from "vite";
import symfonyPlugin from "vite-plugin-symfony";
import vuePlugin from "@vitejs/plugin-vue";
import inject from '@rollup/plugin-inject';
import commonjs from '@rollup/plugin-commonjs';
import { fileURLToPath } from 'url';

/* if you're using React */
// import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        commonjs(),
        /*
        inject({
            $: 'jquery',
            'window.$': 'jquery',
            jQuery: 'jquery',
            'window.jQuery': 'jquery',
            exclude: ['*.css', '*.scss'],
            include: ['./assets/js/**.js']
        }),
         */
        /* react(), // if you're using React */
        vuePlugin(),
        symfonyPlugin({
            stimulus: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                api: 'legacy'
            }
        }
    },
    build: {
        alias: [
            {
                find: /jquery/,
                replacement: fileURLToPath(new URL('./assets/js/_jquery.js', import.meta.url))
            }
        ],
        //target: "ES2022",
        rollupOptions: {
            input: {
                base: "./assets/js/site.js",
                site: "./assets/js/main.js",
                main: "./assets/sass/application.scss",
                comments: "./assets/js/lsdoc/comments.js",
                commentcss: "./assets/sass/comments.scss",
                credentialcss: "./assets/sass/credential.scss",
                credential: "./assets/js/credential.js",
                //swaggercss: "swagger-ui-dist/swagger-ui.css",
                app: "./assets/app.js",
            },
            /*
            output: {
                interop: "auto",
            }
             */
        },
        optimizeDeps: {
        },
        commonjsOptions: {
            include: [
                /*
                'node_modules/jquery/dist/jquery.js',
                'assets/js/_jquery.js',
                'node_modules/select2/dist/js/select2.full.js',
                'jquery',
                'jquery-comments',
                'node_modules/jquery-comments/node_modules/jquery/dist/jquery.js',
                'node_modules/datatables.net/node_modules/jquery/dist/jquery.js',
                'datatables.net',
                'node_modules/datatables.net-bs/node_modules/jquery/dist/jquery.js',
                'node_modules/datatables.net-fixedheader/node_modules/jquery/dist/jquery.js',
                'node_modules/datatables.net-scroller/node_modules/jquery/dist/jquery.js',
                'node_modules/datatables.net-select/node_modules/jquery/dist/jquery.js',
                'node_modules/select2/dist/js/select2.full.js',
                'node_modules/markdown-it/index.js',
                 */
            ],
        }
    },
});
