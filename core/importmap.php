<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    /*
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
     */
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    'vue' => [
        'version' => '3.5.12',
        'package_specifier' => 'vue/dist/vue.esm-bundler.js',
    ],
    '@vue/runtime-dom' => [
        'version' => '3.5.12',
    ],
    '@vue/compiler-dom' => [
        'version' => '3.5.12',
    ],
    '@vue/shared' => [
        'version' => '3.5.12',
    ],
    '@vue/runtime-core' => [
        'version' => '3.5.12',
    ],
    '@vue/compiler-core' => [
        'version' => '3.5.12',
    ],
    '@vue/reactivity' => [
        'version' => '3.5.12',
    ],
    '@symfony/ux-vue' => [
        'path' => './vendor/symfony/ux-vue/assets/dist/loader.js',
    ],
];
