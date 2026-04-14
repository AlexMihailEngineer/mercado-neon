<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    order: Object
});
</script>

<template>
    <Head title="Transaction Complete" />

    <AppLayout>
        <div class="min-h-[70vh] flex items-center justify-center">
            <div class="max-w-md w-full p-8 bg-black/60 border border-neon-cyan/50 shadow-[0_0_30px_rgba(46,249,182,0.15)] rounded-sm backdrop-blur-xl relative overflow-hidden">
                
                <div class="absolute inset-0 opacity-10 pointer-events-none" 
                     style="background-image: radial-gradient(#2ef9b6 0.5px, transparent 0.5px); background-size: 10px 10px;">
                </div>

                <div class="relative z-10 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-neon-cyan/10 border border-neon-cyan mb-6 animate-pulse">
                        <svg class="w-8 h-8 text-neon-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>

                    <h1 class="text-neon-cyan font-mono text-xl tracking-[0.4em] uppercase mb-2">
                        PAYMENT_VERIFIED
                    </h1>
                    
                    <div class="h-px w-full bg-gradient-to-r from-transparent via-neon-cyan/50 to-transparent mb-6"></div>

                    <div class="space-y-4 font-mono text-sm">
                        <div class="flex justify-between text-gray-400">
                            <span>INVOICE_ID:</span>
                            <span class="text-white">{{ order.invoice_number }}</span>
                        </div>
                        <div class="flex justify-between text-gray-400">
                            <span>AMOUNT_PAID:</span>
                            <span class="text-white">{{ order.total_amount_ron }} RON</span>
                        </div>
                        <div class="flex justify-between text-gray-400">
                            <span>STATUS:</span>
                            <span class="text-neon-cyan bg-neon-cyan/10 px-2 border border-neon-cyan/30 text-[10px]">SUCCESS_SETTLED</span>
                        </div>
                    </div>

                    <div class="mt-8 p-4 bg-neon-cyan/5 border border-neon-cyan/20 rounded-sm">
                        <p class="text-[10px] text-neon-cyan/70 font-mono text-left leading-relaxed">
                            > LOG: Payment signals received from STRIPE_GLOBAL...<br>
                            > LOG: Database updated for MN-{{ order.id }}...<br>
                            > LOG: Initializing Day 5 protocols (AWB_NUMBER)...
                        </p>
                    </div>

                    <div class="mt-8">
                        <Link 
                            :href="route('dashboard')"
                            class="block w-full py-3 bg-neon-cyan/20 hover:bg-neon-cyan/40 text-neon-cyan border border-neon-cyan transition-all duration-300 font-mono text-xs uppercase tracking-widest hover:shadow-[0_0_15px_rgba(46,249,182,0.4)]"
                        >
                            Return to Command Center
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
