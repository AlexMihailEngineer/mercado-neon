<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="System Dashboard" />

    <AppLayout>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="p-6 bg-neon-surface/50 border border-neon-cyan/30 rounded-sm shadow-neon-border backdrop-blur-md">
                <h3 class="text-neon-cyan font-mono text-xs tracking-[0.3em] mb-4">SCAVENGER_ENGINE_V1</h3>
                <div class="flex items-end justify-between">
                    <span class="text-4xl font-bold text-white">98.2<span class="text-lg text-neon-cyan/60">%</span></span>
                    <span class="text-[10px] text-neon-cyan animate-pulse">MATCH_ACCURACY</span>
                </div>
            </div>

            <div class="p-6 bg-neon-surface/50 border border-neon-purple/30 rounded-sm shadow-neon-border backdrop-blur-md">
                <h3 class="text-neon-purple font-mono text-xs tracking-[0.3em] mb-4">SAMEDAY_ACTIVE_AWB</h3>
                <div class="flex items-end justify-between">
                    <span class="text-4xl font-bold text-white">12</span>
                    <span class="text-[10px] text-neon-purple">PENDING_PICKUP</span>
                </div>
            </div>

            <div class="p-6 bg-neon-surface/50 border border-neon-pink/30 rounded-sm shadow-neon-border backdrop-blur-md">
                <h3 class="text-neon-pink font-mono text-xs tracking-[0.3em] mb-4">RO_E-FACTURA_SYNC</h3>
                <div class="flex items-end justify-between">
                    <span class="text-4xl font-bold text-white">OK</span>
                    <span class="text-[10px] text-neon-pink font-mono">ANAF_UBL_2.1</span>
                </div>
            </div>
        </div>

        <div class="mt-8 p-4 bg-black/40 border border-neon-purple/10 font-mono text-xs text-neon-purple/60 rounded">
            <p>> Initializing local RAG pipeline...</p>
            <p>> Connecting to Ollama @ 127.0.0.1:11434...</p>
            <p>> Found 4,209 unmapped vendor SKUs.</p>
        </div>
    </AppLayout>
</template>