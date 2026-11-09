import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                'petrol': {
                    DEFAULT: '#005f6b',
                    light: '#008b9a',
                    dark: '#004a54',
                },
                'saffron': {
                    DEFAULT: '#f4c430',
                    light: '#f7dc7a',
                    dark: '#d4a017',
                },
            },
            fontFamily: {
                sans: ['\"DM Sans\"', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
