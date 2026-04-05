<script setup>
import { Link } from '@inertiajs/vue3';
// Do NOT import AppLayout here
</script>

<template>
    <div class="min-h-screen flex flex-col dark font-sans bg-neon-bg">
        <nav class="sticky top-0 z-50 border-b border-neon-purple/20 bg-neon-surface/80 backdrop-blur-xl">
            <div class="px-6 py-3 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-neon-cyan shadow-[0_0_15px_rgba(46,249,182,0.4)] flex items-center justify-center rounded-sm">
                        <span class="text-neon-bg font-bold font-mono text-lg">M</span>
                    </div>
                    <span class="text-xl font-bold tracking-tighter text-white">
                        MERCADO<span class="text-neon-cyan">NEON</span>
                    </span>
                </div>
            </div>
        </nav>

        <div class="flex flex-1 overflow-hidden">
            <aside class="w-64 border-r border-neon-purple/10 bg-neon-surface/40 backdrop-blur-md hidden md:block">
                <div class="p-6 space-y-4">
                    <p class="text-[10px] font-bold text-neon-purple/60 tracking-[0.2em]">CORE_MODULES</p>
                    <nav class="space-y-1">
                        <Link href="/dashboard" class="flex items-center px-4 py-2 text-sm font-mono text-neon-cyan bg-neon-cyan/5 border-l-2 border-neon-cyan shadow-[inset_0_0_10px_rgba(46,249,182,0.1)]">
                            > DASHBOARD
                        </Link>
                    </nav>
                </div>
            </aside>

            <main class="flex-1 p-8 overflow-y-auto">
                <slot /> </main>
        </div>
    </div>
</template>