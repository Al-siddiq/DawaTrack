#!/bin/sh
set -eu
mkdir -p public/assets/vendor/vue public/assets/vendor/dexie public/assets/js
cp node_modules/vue/dist/vue.global.prod.js public/assets/vendor/vue/vue.global.prod.js
cp node_modules/dexie/dist/dexie.min.js public/assets/vendor/dexie/dexie.min.js
rm -rf public/assets/js/shop public/assets/js/admin public/assets/js/auth public/assets/js/shared public/assets/js/api public/assets/js/components
cp -R resources/js/shop resources/js/admin resources/js/auth resources/js/shared resources/js/api resources/js/components public/assets/js/
