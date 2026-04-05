import forms from "@tailwindcss/forms";
import flowbitePlugin from "flowbite/plugin";

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class", // Essential for the dark cyberpunk theme
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.vue",
        "./node_modules/flowbite-vue/**/*.{js,jsx,ts,tsx,vue}",
        "./node_modules/flowbite/**/*.js",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: [
                    "Inter",
                    "ui-sans-serif",
                    "system-ui",
                    "sans-serif",
                    "Apple Color Emoji",
                    "Segoe UI Emoji",
                ],
                mono: [
                    "Fira Code",
                    "ui-monospace",
                    "SFMono-Regular",
                    "Menlo",
                    "Monaco",
                    "Consolas",
                    "Liberation Mono",
                    "Courier New",
                    "monospace",
                ],
            },
            colors: {
                // Cyberpunk "Holo Noir" Palette
                neon: {
                    bg: "#0A0F1E", // Deep Indigo-Black
                    surface: "#111827", // Panel background
                    cyan: "#2EF9B6", // Primary accent
                    pink: "#FF4FD8", // Warning/Active accent
                    purple: "#B8B8FF", // Secondary text/borders
                },
            },
            boxShadow: {
                "neon-glow": "0 0 15px rgba(46, 249, 182, 0.4)",
                "neon-border": "inset 0 0 10px rgba(184, 184, 255, 0.1)",
            },
        },
    },

    plugins: [forms, flowbitePlugin],
};
