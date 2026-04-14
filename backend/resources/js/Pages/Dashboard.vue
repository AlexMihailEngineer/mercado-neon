<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    pendingOrders: { type: Array, default: () => [] },
    activeShipments: { type: Array, default: () => [] }, // NEW: Pass shipments from controller
    searchResults: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '' }) },
    systemStats: { type: Object, default: () => ({}) }
});

const page = usePage();

// Make shipments reactive so Echo can update them in real-time
const activeLogistics = ref(props.activeShipments);

const searchQuery = ref(props.filters.search && props.filters.search !== '*' ? props.filters.search : '');

const performSearch = debounce((value) => {
    router.get(
        route('dashboard'), 
        { search: value }, 
        { preserveState: true, preserveScroll: true, replace: true }
    );
}, 300);

watch(searchQuery, (newValue) => {
    performSearch(newValue);
});

const generatePaymentLink = (orderId) => {
    router.post(route('checkout.initiate', orderId), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const draftInvoice = ref([]);

const customerForm = ref({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    shipping_county: '',
    shipping_city: '',
    shipping_address: '',
});

const addToInvoice = (product) => {
    const existing = draftInvoice.value.find(item => item.id === product.id);
    if (existing) {
        existing.quantity++;
    } else {
        draftInvoice.value.push({ ...product, quantity: 1 });
    }
};

const removeFromInvoice = (productId) => {
    draftInvoice.value = draftInvoice.value.filter(item => item.id !== productId);
};

const invoiceTotal = computed(() => {
    return draftInvoice.value.reduce((total, item) => {
        return total + (parseFloat(item.price) * item.quantity);
    }, 0).toFixed(2);
});

const generateDraftInvoice = () => {
    router.post(route('orders.store'), {
        ...customerForm.value,
        items: draftInvoice.value.map(item => ({ 
            id: item.id, 
            quantity: item.quantity 
        }))
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            draftInvoice.value = [];
            Object.keys(customerForm.value).forEach(key => customerForm.value[key] = '');
        }
    });
};

// HUD Styling helper for statuses
const getStatusTheme = (status) => {
    const themes = {
        'pending': 'text-neon-cyan border-neon-cyan/30 bg-neon-cyan/10 shadow-[0_0_8px_rgba(46,249,182,0.2)]',
        'in_transit': 'text-yellow-400 border-yellow-400/30 bg-yellow-400/10 shadow-[0_0_8px_rgba(250,204,21,0.2)]',
        'out_for_delivery': 'text-neon-purple border-neon-purple/30 bg-neon-purple/10 shadow-[0_0_8px_rgba(168,85,247,0.2)]',
        'delivered': 'text-neon-pink border-neon-pink/30 bg-neon-pink/10 shadow-[0_0_8px_rgba(255,79,216,0.2)]',
    };
    return themes[status?.toLowerCase()] || 'text-gray-400 border-gray-400/30 bg-gray-400/10';
};

// Initialize Laravel Echo Listeners
onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('shipments')
            .listen('.ShipmentStatusUpdated', (e) => {
                console.log('Echo Uplink Received:', e);

                // Find the shipment in our reactive array
                const index = activeLogistics.value.findIndex(s => s.id === e.order_id);

                if (index !== -1) {
                    // If delivered, remove from active logistics list
                    if (e.status?.toLowerCase() === 'delivered') {
                        activeLogistics.value.splice(index, 1);
                    } else {
                        // Update fields directly to trigger Vue reactivity
                        activeLogistics.value[index].logistics_status = e.status;
                        activeLogistics.value[index].awb_number = e.awb_number;
                    }
                } else {
                    // If the order isn't in our array yet (e.g., just paid), reload the data block silently
                    router.reload({ only: ['activeShipments', 'pendingOrders'] });
                }
            });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave('shipments');
    }
});
</script>

<template>
    <Head title="System Dashboard" />

    <AppLayout>
        <div class="mb-10 group mt-4">
            <div class="relative">
                <div class="absolute -top-3 left-4 px-2 bg-black text-[10px] font-mono text-neon-cyan/80 tracking-tighter z-10 border border-neon-cyan/30 rounded-sm">
                    SCAVENGER_PROTOCOL_v0.4 // QUERY_STRING
                </div>
                
                <div class="relative flex items-center">
                    <span class="absolute left-4 text-neon-cyan animate-pulse font-mono">></span>
                    <input 
                        v-model="searchQuery"
                        type="text" 
                        placeholder="SCANNING_FOR_VENDORS_AND_SKUS..."
                        class="w-full bg-black/40 border border-neon-cyan/30 rounded-sm py-4 pl-10 pr-4 text-neon-cyan font-mono text-lg focus:ring-1 focus:ring-neon-cyan focus:border-neon-cyan shadow-[inset_0_0_15px_rgba(46,249,182,0.05)] placeholder:text-neon-cyan/20 transition-all duration-300 group-hover:border-neon-cyan/60"
                    >
                    
                    <div class="absolute right-4 hidden md:flex items-center gap-4">
                        <span class="text-[10px] font-mono text-neon-cyan/40">ENC_MODE: TYPESENSE_FAST</span>
                    </div>
                </div>
            </div>
        </div>

                <div v-if="searchResults.length > 0" class="mb-10">
            <h3 class="text-neon-cyan font-mono text-xs tracking-[0.3em] mb-4 flex items-center">
                <span class="w-2 h-2 bg-neon-cyan mr-2 shadow-[0_0_8px_#2EF9B6]"></span>
                LIVE_ASSETS_DETECTED
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div v-for="product in searchResults" :key="product.id" class="p-4 bg-black/40 border border-neon-cyan/20 hover:border-neon-cyan/60 transition-colors rounded-sm flex flex-col justify-between group">
                    <div>
                        <div class="text-[10px] text-neon-cyan/50 font-mono mb-1">{{ product.category || 'SYS_UNMAPPED' }}</div>
                        <h4 class="text-white text-sm font-bold truncate">{{ product.title }}</h4>
                        <p class="text-xs text-gray-400 mt-2 line-clamp-2">{{ product.description }}</p>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-neon-cyan font-mono text-sm">{{ product.price }} RON</span>
                        <button 
                            @click="addToInvoice(product)"
                            class="text-[10px] text-black bg-neon-cyan hover:bg-white px-2 py-1 font-bold uppercase transition-colors">
                            + Add to Invoice
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="p-6 bg-neon-surface/50 border border-neon-cyan/30 rounded-sm shadow-neon-border backdrop-blur-md">
                <h3 class="text-neon-cyan font-mono text-xs tracking-[0.3em] mb-4">SCAVENGER_ENGINE_V1</h3>
                <div class="flex items-end justify-between">
                    <span class="text-4xl font-bold text-white">{{ systemStats.match_accuracy || '98.2' }}<span class="text-lg text-neon-cyan/60">%</span></span>
                    <span class="text-[10px] text-neon-cyan animate-pulse">MATCH_ACCURACY</span>
                </div>
            </div>

            <div class="p-6 bg-neon-surface/50 border border-neon-purple/30 rounded-sm shadow-neon-border backdrop-blur-md">
                <h3 class="text-neon-purple font-mono text-xs tracking-[0.3em] mb-4">SAMEDAY_ACTIVE_AWB</h3>
                <div class="flex items-end justify-between">
                    <span class="text-4xl font-bold text-white">{{ systemStats.active_awbs || '12' }}</span>
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

        <div v-if="draftInvoice.length > 0" class="mb-10 p-6 bg-black/60 border border-neon-magenta/30 shadow-[0_0_15px_rgba(255,79,216,0.1)] rounded-sm">
            <div class="flex justify-between items-end mb-6">
                <h3 class="text-neon-magenta font-mono text-xs tracking-[0.3em]">++ ACTIVE_INVOICE_DRAFT</h3>
                <span class="text-white font-mono text-xl">{{ invoiceTotal }} <span class="text-xs text-neon-magenta">RON</span></span>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <div class="space-y-2">
                    <div class="text-[10px] text-neon-magenta/50 font-mono mb-2">ORD_MANIFEST_ITEMS</div>
                    <div v-for="item in draftInvoice" :key="item.id" class="flex items-center justify-between bg-black/40 border border-neon-magenta/20 p-3">
                        <div class="flex flex-col">
                            <span class="text-white text-sm font-bold">{{ item.title }}</span>
                            <span class="text-[10px] text-neon-magenta/70 font-mono">{{ item.price }} RON x {{ item.quantity }}</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-neon-magenta font-mono font-bold">{{ (item.price * item.quantity).toFixed(2) }} RON</span>
                            <button @click="removeFromInvoice(item.id)" class="text-red-500 hover:text-red-400 font-mono text-xs">
                                [X]
                            </button>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="text-[10px] text-neon-magenta/50 font-mono mb-2">SHIPPING_METADATA_REQUIRED</div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <input v-model="customerForm.customer_name" type="text" placeholder="FULL_NAME" class="bg-black/40 border border-neon-magenta/30 text-white font-mono text-xs p-3 focus:ring-1 focus:ring-neon-magenta rounded-sm">
                        <input v-model="customerForm.customer_phone" type="text" placeholder="PHONE (e.g. 07XXXXXXXX)" class="bg-black/40 border border-neon-magenta/30 text-white font-mono text-xs p-3 focus:ring-1 focus:ring-neon-magenta rounded-sm">
                    </div>

                    <input v-model="customerForm.customer_email" type="email" placeholder="EMAIL_ADDRESS" class="w-full bg-black/40 border border-neon-magenta/30 text-white font-mono text-xs p-3 focus:ring-1 focus:ring-neon-magenta rounded-sm">

                    <div class="grid grid-cols-2 gap-4">
                        <input v-model="customerForm.shipping_county" type="text" placeholder="COUNTY (Județ)" class="bg-black/40 border border-neon-magenta/30 text-white font-mono text-xs p-3 focus:ring-1 focus:ring-neon-magenta rounded-sm">
                        <input v-model="customerForm.shipping_city" type="text" placeholder="CITY (Localitate)" class="bg-black/40 border border-neon-magenta/30 text-white font-mono text-xs p-3 focus:ring-1 focus:ring-neon-magenta rounded-sm">
                    </div>

                    <textarea v-model="customerForm.shipping_address" placeholder="STREET_ADDRESS_DETAILED" rows="2" class="w-full bg-black/40 border border-neon-magenta/30 text-white font-mono text-xs p-3 focus:ring-1 focus:ring-neon-magenta rounded-sm"></textarea>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-neon-magenta/20">
                <button 
                    @click="generateDraftInvoice"
                    class="px-8 py-4 bg-neon-magenta/20 hover:bg-neon-magenta/40 text-neon-magenta border border-neon-magenta transition-all duration-200 text-xs uppercase tracking-wider font-bold shadow-[0_0_15px_rgba(255,79,216,0.1)]"
                >
                    Initialize Official Transaction
                </button>
            </div>
        </div>

        <div v-if="activeLogistics.length > 0" class="mb-10">
            <div class="flex justify-between items-end mb-4 border-b border-white/10 pb-2">
                <h3 class="text-neon-cyan font-mono text-xs tracking-[0.3em] flex items-center">
                    <span class="w-2 h-2 bg-neon-cyan mr-2 animate-ping shadow-[0_0_8px_#2EF9B6]"></span>
                    ACTIVE_LOGISTICS_UPLINK // FAN_COURIER
                </h3>
                <span class="text-[10px] font-mono text-neon-cyan/50 animate-pulse">LISTENING_FOR_WEBHOOKS...</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div 
                    v-for="shipment in activeLogistics" 
                    :key="shipment.id" 
                    class="p-4 bg-black/40 border border-white/10 rounded-sm flex flex-col justify-between group transition-all duration-300 hover:border-white/30"
                >
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="text-[10px] text-gray-500 font-mono mb-1">AWB_NUMBER</div>
                            <h4 class="text-white text-sm font-bold font-mono tracking-wider">{{ shipment.awb_number || 'PENDING_GENERATION' }}</h4>
                        </div>
                        <div 
                            class="px-2 py-1 text-[10px] font-mono uppercase tracking-wider border rounded-sm transition-colors duration-500"
                            :class="getStatusTheme(shipment.logistics_status)"
                        >
                            {{ shipment.logistics_status?.replace('_', ' ') || 'PROCESSING' }}
                        </div>
                    </div>
                    
                    <div class="flex items-end justify-between border-t border-white/5 pt-3">
                        <div class="flex flex-col">
                            <span class="text-[10px] text-gray-500 font-mono">DESTINATION</span>
                            <span class="text-xs text-gray-300 truncate max-w-[150px]">{{ shipment.shipping_city }}, {{ shipment.shipping_county }}</span>
                        </div>
                        <span class="text-white font-mono text-xs">INV: {{ shipment.invoice_number }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="pendingOrders.length > 0" class="mb-10">
            <h3 class="text-neon-purple font-mono text-xs tracking-[0.3em] mb-4 flex items-center">
                <span class="w-2 h-2 bg-neon-purple rounded-full mr-2 animate-pulse"></span>
                AWAITING_PAYMENT_INITIATION
            </h3>

            <div class="bg-black/40 border border-neon-purple/20 rounded-sm overflow-hidden">
                <table class="w-full text-left font-mono text-sm">
                    <thead class="bg-neon-purple/10 text-neon-purple/70 text-xs">
                        <tr>
                            <th class="p-4 font-normal">INVOICE_ID</th>
                            <th class="p-4 font-normal">AMOUNT (RON)</th>
                            <th class="p-4 font-normal">STATUS</th>
                            <th class="p-4 font-normal text-right">ACTION</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <tr v-if="pendingOrders.length === 0">
                            <td colspan="4" class="p-4 text-center text-neon-purple/50 italic">
                                No pending transactions found.
                            </td>
                        </tr>
                        <tr v-for="order in pendingOrders" :key="order.id" class="border-t border-neon-purple/10 hover:bg-neon-purple/5 transition-colors">
                            <td class="p-4 text-white">{{ order.invoice_number }}</td>
                            <td class="p-4">{{ order.total_amount_ron }}</td>
                            <td class="p-4">
                                <span class="px-2 py-1 bg-yellow-500/20 text-yellow-400 text-[10px] rounded border border-yellow-500/30">
                                    {{ (order.payment_status || order.status).toUpperCase() }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <button 
                                    @click="generatePaymentLink(order.id)"
                                    class="px-4 py-2 bg-neon-purple/20 hover:bg-neon-purple/40 text-neon-purple border border-neon-purple transition-all duration-200 text-xs uppercase tracking-wider hover:shadow-[0_0_10px_rgba(168,85,247,0.5)]"
                                >
                                    Generate Link
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>