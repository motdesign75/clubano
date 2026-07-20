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
                primary: '#2954A3',         // Clubano-Blau
                secondary: '#1E3F7F',       // Dunkleres Blau
                highlight: '#F5F8FF',       // Tabellenheader-Hintergrund
                light: '#FAFBFF',           // Zeilen-Hintergrund
                hover: '#EFF3FF',           // Hover-Zeile
                success: '#D1FAE5',         // Grün für positive Aktionen
                danger: '#FEE2E2',          // Rot für Warnungen
                info: '#DBEAFE',            // Hellblau für Hinweise
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                xl: '1rem',
                '2xl': '1.5rem',
            },
            boxShadow: {
                soft: '0 4px 12px rgba(0, 0, 0, 0.04)',
            },
        },
    },

    plugins: [forms],
};
