import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
    ],
    server: {
        host: "0.0.0.0", // Listen on all container interfaces
        port: 5173,
        strictPort: true,
        hmr: {
            host: "localhost", // The browser on your desktop looks for 'localhost'
        },
        watch: {
            usePolling: true, // Often necessary for Docker volume changes to register
        },
    },
});
