const mix = require('laravel-mix');
const fs = require('fs');
/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/js/app.js', 'public/sn_client_resources/js')
.postCss('resources/css/app.css', 'public/sn_client_resources/css', [
    require('tailwindcss'),
    require('autoprefixer'),
]);
mix.then(() => {
    if (fs.existsSync('public/mix-manifest.json')) {
        fs.unlinkSync('public/mix-manifest.json');
    }
});