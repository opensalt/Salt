import { defineConfig } from "vite";
import symfonyPlugin from "vite-plugin-symfony";
import inject from '@rollup/plugin-inject';
import commonjs from '@rollup/plugin-commonjs';

/* if you're using React */
// import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        commonjs(),
        /*
        inject({
            $: 'jquery',
            jQuery: 'jquery',
            exclude: ['*.css', '*.scss'],
            include: ['./assets/js/**.js']
        }),
         */
        /* react(), // if you're using React */
        symfonyPlugin(),
    ],
    build: {
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
