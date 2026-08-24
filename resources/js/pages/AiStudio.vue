<script setup lang="ts">
import axios from 'axios';
import AppLayout from '@/layout/AppLayout.vue';
import Select from 'primevue/select';
import {
    Activity,
    Bot,
    Check,
    Coins,
    Copy,
    Database,
    Eye,
    EyeOff,
    Key,
    Layers,
    Mic,
    MicOff,
    Plus,
    RefreshCw,
    Send,
    Server,
    Shield,
    Sparkles,
    Trash2,
    TrendingUp,
    Volume2,
    VolumeX,
    Wallet,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';

interface ApiKeyItem {
    id: number;
    business_name: string;
    contact_email: string | null;
    contact_phone: string | null;
    key: string;
    type: 'live' | 'test';
    plan: string;
    is_active: boolean;
    voice_enabled: boolean;
    query_count: number;
    last_used_at: string | null;
    created_at: string;
}

interface StatsData {
    total_queries: number;
    active_tenants: number;
    total_tenants: number;
    voice_tenants: number;
}

const props = defineProps<{
    appName: string;
    apiKeys: ApiKeyItem[];
    stats: StatsData;
    samplePrompts: string[];
}>();

// Navigation Tabs
const activeTab = ref<'tokens' | 'playground' | 'docs'>('tokens');

// Options for PrimeVue Selects
const typeOptions = [
    { label: 'Live Production (mn_live_...)', value: 'live' },
    { label: 'Sandbox Testing (mn_test_...)', value: 'test' },
];

const planOptions = [
    { label: 'Growth Plan', value: 'growth' },
    { label: 'Enterprise Plan', value: 'enterprise' },
    { label: 'Starter Plan', value: 'starter' },
];

// API Keys State
const keyList = ref<ApiKeyItem[]>(props.apiKeys || []);
const isCreateModalOpen = ref(false);
const isSubmittingKey = ref(false);
const copiedKeyId = ref<number | null>(null);
const revealedKeys = ref<Record<number, boolean>>({});

const shopTokenOptions = computed(() => [
    { label: 'No Token (Dev Mode)', value: '' },
    ...keyList.value.map((k) => ({
        label: `${k.business_name} (${k.type.toUpperCase()})`,
        value: k.key,
    })),
]);

const newKeyForm = ref({
    business_name: '',
    contact_email: '',
    contact_phone: '',
    type: 'live' as 'live' | 'test',
    plan: 'growth',
    voice_enabled: true,
});

// Playground State
interface Message {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    actions?: any[];
    audio?: string | null;
    timestamp: string;
}

const messages = ref<Message[]>([
    {
        id: '1',
        role: 'assistant',
        content: 'Namaste! Main Central Maniratn AI Hub testing console hoon. Aap yahan tool calling, live speech synthesis aur ERP tools test kar sakte hain.',
        timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    },
]);

const inputPrompt = ref('');
const isLoading = ref(false);
const isListening = ref(false);
const isSpeaking = ref(false);
const autoVoiceOutput = ref(true);
const selectedVoice = ref('Aoede');
const selectedApiKey = ref('');
const messageContainer = ref<HTMLElement | null>(null);

const voiceOptions = [
    { label: 'Aoede (Warm Female)', value: 'Aoede' },
    { label: 'Kore (Calm Female)', value: 'Kore' },
    { label: 'Puck (Natural Male)', value: 'Puck' },
    { label: 'Fenrir (Deep Male)', value: 'Fenrir' },
];

let recognition: any = null;
let currentAudio: HTMLAudioElement | null = null;

// Speech Recognition Initialization
const initSpeech = () => {
    if (typeof window !== 'undefined') {
        const SpeechRecognition = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition;
        if (SpeechRecognition) {
            recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.lang = 'hi-IN';

            recognition.onstart = () => { isListening.value = true; };
            recognition.onresult = (event: any) => {
                inputPrompt.value = event.results[0][0].transcript;
                isListening.value = false;
                sendMessage();
            };
            recognition.onerror = () => { isListening.value = false; };
            recognition.onend = () => { isListening.value = false; };
        }
    }
};

const toggleListening = () => {
    if (!recognition) {
        alert('Aapke browser me speech recognition support nahi hai. Google Chrome use karein.');
        return;
    }
    if (isListening.value) {
        recognition.stop();
        isListening.value = false;
    } else {
        stopAudio();
        try { recognition.start(); } catch (e) { console.error(e); }
    }
};

const playAudio = (audioDataUri: string) => {
    if (!audioDataUri) return;
    stopAudio();
    currentAudio = new Audio(audioDataUri);
    isSpeaking.value = true;
    currentAudio.onended = () => { isSpeaking.value = false; currentAudio = null; };
    currentAudio.onerror = () => { isSpeaking.value = false; currentAudio = null; };
    currentAudio.play().catch(() => { isSpeaking.value = false; });
};

const stopAudio = () => {
    if (currentAudio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
        currentAudio = null;
    }
    isSpeaking.value = false;
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messageContainer.value) {
            messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
        }
    });
};

const sendMessage = async (customText?: string) => {
    const textToSend = customText || inputPrompt.value.trim();
    if (!textToSend || isLoading.value) return;

    stopAudio();

    const userMessage: Message = {
        id: Date.now().toString(),
        role: 'user',
        content: textToSend,
        timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    };

    messages.value.push(userMessage);
    inputPrompt.value = '';
    isLoading.value = true;
    scrollToBottom();

    const historyPayload = messages.value.slice(-6, -1).map((m) => ({
        role: m.role === 'user' ? 'user' : 'assistant',
        content: m.content,
    }));

    try {
        const headers: Record<string, string> = { Accept: 'application/json' };
        if (selectedApiKey.value) {
            headers['Authorization'] = 'Bearer ' + selectedApiKey.value;
        }

        const response = await axios.post('/api/ai/chat', {
            message: textToSend,
            history: historyPayload,
            voice: selectedVoice.value,
            include_audio: autoVoiceOutput.value,
        }, { headers });

        const replyText = response.data.reply || 'Action processed.';
        const actions = response.data.actions || [];
        const audioUri = response.data.audio || null;

        messages.value.push({
            id: (Date.now() + 1).toString(),
            role: 'assistant',
            content: replyText,
            actions: actions,
            audio: audioUri,
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        });

        scrollToBottom();

        if (autoVoiceOutput.value && audioUri) {
            playAudio(audioUri);
        }
    } catch (error: any) {
        messages.value.push({
            id: (Date.now() + 1).toString(),
            role: 'assistant',
            content: 'Error: ' + (error.response?.data?.error || error.message),
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
};

// API Key Management Handlers
const openCreateModal = () => {
    newKeyForm.value = {
        business_name: '',
        contact_email: '',
        contact_phone: '',
        type: 'live',
        plan: 'growth',
        voice_enabled: true,
    };
    isCreateModalOpen.value = true;
};

const submitCreateKey = async () => {
    if (!newKeyForm.value.business_name.trim()) {
        alert('Please enter business name');
        return;
    }
    isSubmittingKey.value = true;
    try {
        const res = await axios.post('/api/keys', newKeyForm.value);
        if (res.data.success && res.data.key) {
            keyList.value.unshift(res.data.key);
            isCreateModalOpen.value = false;
        }
    } catch (err: any) {
        alert('Failed to generate key: ' + (err.response?.data?.message || err.message));
    } finally {
        isSubmittingKey.value = false;
    }
};

const toggleKeyStatus = async (item: ApiKeyItem) => {
    try {
        const res = await axios.post(`/api/keys/${item.id}/toggle`);
        if (res.data.success) {
            item.is_active = res.data.is_active;
        }
    } catch (err) {
        console.error('Failed to toggle status', err);
    }
};

const deleteApiKey = async (item: ApiKeyItem) => {
    if (!confirm(`Are you sure you want to revoke and delete token for "${item.business_name}"?`)) {
        return;
    }
    try {
        const res = await axios.delete(`/api/keys/${item.id}`);
        if (res.data.success) {
            keyList.value = keyList.value.filter((k) => k.id !== item.id);
        }
    } catch (err) {
        console.error('Failed to delete key', err);
    }
};

const copyToken = (item: ApiKeyItem) => {
    navigator.clipboard.writeText(item.key);
    copiedKeyId.value = item.id;
    setTimeout(() => {
        if (copiedKeyId.value === item.id) {
            copiedKeyId.value = null;
        }
    }, 2000);
};

const toggleRevealKey = (id: number) => {
    revealedKeys.value[id] = !revealedKeys.value[id];
};

const formatKeyDisplay = (item: ApiKeyItem) => {
    if (revealedKeys.value[item.id]) {
        return item.key;
    }
    const parts = item.key.split('_');
    if (parts.length >= 3) {
        return `${parts[0]}_${parts[1]}_••••••••••••••••`;
    }
    return item.key.substring(0, 10) + '••••••••••••';
};

onMounted(() => {
    initSpeech();
    if (keyList.value.length > 0) {
        selectedApiKey.value = keyList.value[0].key;
    }
});
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- 💎 Page Title & Navigation Bar (Clean Enterprise Luxury Card) -->
            <div class="flex flex-wrap items-center justify-between gap-4 bg-white p-5 border border-surface-200 shadow-xs">
                <div>
                    <div class="flex items-center gap-2.5">
                        <div class="h-8 w-8 bg-[#1c3633] text-white flex items-center justify-center border border-[#c08f34]/30 shadow-xs">
                            <Sparkles class="w-4 h-4 text-[#c08f34]" />
                        </div>
                        <h1 class="text-xl font-bold tracking-tight text-[#1c3633]">
                            Maniratn <span class="text-[#c08f34]">AI Hub</span>
                        </h1>
                        <span class="px-2 py-0.5 bg-[#1c3633]/5 text-[#1c3633] border border-[#1c3633]/20 font-bold text-[10px] uppercase tracking-wider">
                            Central Brain
                        </span>
                    </div>
                    <p class="text-xs text-surface-500 mt-1">
                        Multi-Tenant Voice AI & Tool Orchestration Engine for KaratSetu Jewellery ERPs
                    </p>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex items-center bg-surface-100 p-1 border border-surface-200">
                    <button
                        type="button"
                        :class="[
                            'px-4 py-2 text-xs font-bold flex items-center gap-2 transition-all cursor-pointer border',
                            activeTab === 'tokens'
                                ? 'bg-white text-[#1c3633] border-surface-300 shadow-xs'
                                : 'bg-transparent text-surface-600 border-transparent hover:text-[#1c3633]',
                        ]"
                        @click="activeTab = 'tokens'"
                    >
                        <Key class="w-3.5 h-3.5 text-[#c08f34]" />
                        <span>Store API Tokens</span>
                        <span class="px-1.5 py-0.2 text-[10px] bg-[#1c3633]/10 text-[#1c3633] font-mono font-bold">
                            {{ keyList.length }}
                        </span>
                    </button>

                    <button
                        type="button"
                        :class="[
                            'px-4 py-2 text-xs font-bold flex items-center gap-2 transition-all cursor-pointer border',
                            activeTab === 'playground'
                                ? 'bg-white text-[#1c3633] border-surface-300 shadow-xs'
                                : 'bg-transparent text-surface-600 border-transparent hover:text-[#1c3633]',
                        ]"
                        @click="activeTab = 'playground'"
                    >
                        <Bot class="w-3.5 h-3.5 text-[#c08f34]" />
                        <span>Voice Studio Playground</span>
                    </button>

                    <button
                        type="button"
                        :class="[
                            'px-4 py-2 text-xs font-bold flex items-center gap-2 transition-all cursor-pointer border',
                            activeTab === 'docs'
                                ? 'bg-white text-[#1c3633] border-surface-300 shadow-xs'
                                : 'bg-transparent text-surface-600 border-transparent hover:text-[#1c3633]',
                        ]"
                        @click="activeTab = 'docs'"
                    >
                        <Layers class="w-3.5 h-3.5 text-[#c08f34]" />
                        <span>Integration Guide</span>
                    </button>
                </div>
            </div>

            <!-- 📊 Quick Stats Metrics Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-4 bg-white border border-surface-200 shadow-xs flex items-center gap-3.5 border-l-4 border-l-[#1c3633]">
                    <div class="w-10 h-10 bg-[#1c3633] text-[#c08f34] flex items-center justify-center shrink-0">
                        <Activity class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-[10.5px] font-bold text-surface-500 uppercase tracking-wider">Total Queries Handled</p>
                        <p class="text-lg font-bold text-[#1c3633]">
                            {{ (stats?.total_queries || 0).toLocaleString() }}
                        </p>
                    </div>
                </div>

                <div class="p-4 bg-white border border-surface-200 shadow-xs flex items-center gap-3.5 border-l-4 border-l-emerald-600">
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-800 flex items-center justify-center shrink-0 border border-emerald-200">
                        <Shield class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-[10.5px] font-bold text-surface-500 uppercase tracking-wider">Connected Showrooms</p>
                        <p class="text-lg font-bold text-emerald-800">
                            {{ stats?.active_tenants || keyList.filter(k => k.is_active).length }} Active Stores
                        </p>
                    </div>
                </div>

                <div class="p-4 bg-white border border-surface-200 shadow-xs flex items-center gap-3.5 border-l-4 border-l-[#c08f34]">
                    <div class="w-10 h-10 bg-amber-50 text-amber-900 flex items-center justify-center shrink-0 border border-amber-200">
                        <Volume2 class="w-5 h-5 text-[#c08f34]" />
                    </div>
                    <div>
                        <p class="text-[10.5px] font-bold text-surface-500 uppercase tracking-wider">Studio HD Voice</p>
                        <p class="text-lg font-bold text-[#9b6f1e]">
                            {{ keyList.filter(k => k.voice_enabled).length }} Stores Enabled
                        </p>
                    </div>
                </div>

                <div class="p-4 bg-white border border-surface-200 shadow-xs flex items-center gap-3.5 border-l-4 border-l-[#1c3633]">
                    <div class="w-10 h-10 bg-[#1c3633] text-[#c08f34] flex items-center justify-center shrink-0">
                        <Zap class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-[10.5px] font-bold text-surface-500 uppercase tracking-wider">Response Speed</p>
                        <p class="text-lg font-bold text-[#1c3633]">~1.0s (Flash-Lite)</p>
                    </div>
                </div>
            </div>

            <!-- TAB 1: API KEYS & STORE TOKENS MANAGER -->
            <div v-if="activeTab === 'tokens'" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4 bg-white p-4 border border-surface-200 shadow-xs">
                    <div>
                        <h2 class="text-sm font-bold text-[#1c3633] uppercase tracking-wider">Showroom API Subscriptions</h2>
                        <p class="text-xs text-surface-500 mt-0.5">
                            Generate and manage live/test keys for each jewellery showroom instance connecting to Central AI Hub.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="px-4 h-9 bg-[#1c3633] hover:bg-[#254642] text-white text-xs font-bold flex items-center gap-2 transition-all shadow-xs cursor-pointer border border-[#c08f34]/30"
                        @click="openCreateModal"
                    >
                        <Plus class="w-4 h-4 text-[#c08f34]" />
                        <span>Issue New Shop Token</span>
                    </button>
                </div>

                <!-- API Keys Table -->
                <div class="bg-white border border-surface-200 shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-surface-50 text-surface-700 border-b border-surface-200 uppercase tracking-wider text-[10.5px]">
                                    <th class="py-3 px-5 font-bold min-w-[280px]">Showroom / Store Name</th>
                                    <th class="py-3 px-4 font-bold text-center w-36 whitespace-nowrap">Environment</th>
                                    <th class="py-3 px-4 font-bold text-left min-w-[280px]">Secret API Token</th>
                                    <th class="py-3 px-4 font-bold text-center w-40 whitespace-nowrap">Voice Status</th>
                                    <th class="py-3 px-4 font-bold text-center w-24 whitespace-nowrap">Queries</th>
                                    <th class="py-3 px-4 font-bold text-center w-28 whitespace-nowrap">Status</th>
                                    <th class="py-3 px-4 font-bold text-right w-16 whitespace-nowrap">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100">
                                <tr
                                    v-for="item in keyList"
                                    :key="item.id"
                                    class="hover:bg-surface-50/80 transition-colors"
                                >
                                    <!-- Store Info -->
                                    <td class="py-3.5 px-5 font-medium">
                                        <div class="font-bold text-sm text-[#1c3633]">{{ item.business_name }}</div>
                                        <div class="text-[11px] text-surface-500 flex flex-wrap items-center gap-2 mt-1">
                                            <span v-if="item.contact_phone">{{ item.contact_phone }}</span>
                                            <span v-if="item.contact_email" class="text-surface-400">· {{ item.contact_email }}</span>
                                            <span class="px-2 py-0.5 bg-[#1c3633]/5 text-[#1c3633] font-mono text-[9.5px] font-bold uppercase border border-[#1c3633]/20 tracking-wider">
                                                {{ item.plan }} Tier
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Type Pill -->
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <span
                                            v-if="item.type === 'live'"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 border border-emerald-300 text-emerald-800 text-[10px] font-bold uppercase tracking-wider whitespace-nowrap"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Live Production
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 border border-amber-300 text-amber-900 text-[10px] font-bold uppercase tracking-wider whitespace-nowrap"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                            Sandbox Test
                                        </span>
                                    </td>

                                    <!-- Secret Key Box -->
                                    <td class="py-3.5 px-4">
                                        <div class="inline-flex items-center gap-2 bg-surface-50 border border-surface-300 px-3 py-1.5 font-mono text-xs shadow-2xs whitespace-nowrap">
                                            <span class="text-surface-800 select-all font-semibold font-mono">
                                                {{ formatKeyDisplay(item) }}
                                            </span>
                                            <button
                                                type="button"
                                                class="text-surface-400 hover:text-surface-800 transition-colors cursor-pointer p-0.5"
                                                title="Reveal/Hide Token"
                                                @click="toggleRevealKey(item.id)"
                                            >
                                                <EyeOff v-if="revealedKeys[item.id]" class="w-3.5 h-3.5" />
                                                <Eye v-else class="w-3.5 h-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                :class="[
                                                    'transition-colors cursor-pointer p-0.5 flex items-center gap-1 font-sans text-[10.5px] font-bold',
                                                    copiedKeyId === item.id ? 'text-emerald-700 font-bold' : 'text-[#c08f34] hover:text-[#9b6f1e]',
                                                ]"
                                                :title="copiedKeyId === item.id ? 'Copied!' : 'Copy to Clipboard'"
                                                @click="copyToken(item)"
                                            >
                                                <Check v-if="copiedKeyId === item.id" class="w-3.5 h-3.5 text-emerald-700" />
                                                <Copy v-else class="w-3.5 h-3.5" />
                                                <span>{{ copiedKeyId === item.id ? 'Copied!' : 'Copy' }}</span>
                                            </button>
                                        </div>
                                    </td>

                                    <!-- Voice Status -->
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <span
                                            v-if="item.voice_enabled"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 border border-amber-300 text-amber-900 text-[11px] font-semibold whitespace-nowrap"
                                        >
                                            <Volume2 class="w-3.5 h-3.5 text-[#c08f34]" />
                                            HD Studio Voice
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-surface-100 border border-surface-200 text-surface-500 text-[11px] whitespace-nowrap"
                                        >
                                            <VolumeX class="w-3.5 h-3.5 text-surface-400" />
                                            Text Only
                                        </span>
                                    </td>

                                    <!-- Queries Count -->
                                    <td class="py-3.5 px-4 text-center font-bold text-sm text-[#1c3633] whitespace-nowrap">
                                        {{ item.query_count.toLocaleString() }}
                                    </td>

                                    <!-- Status Toggle -->
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <button
                                            type="button"
                                            :class="[
                                                'px-3.5 py-1 text-[10px] font-bold uppercase tracking-wider transition-all cursor-pointer shadow-2xs border',
                                                item.is_active
                                                    ? 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-700'
                                                    : 'bg-red-600 hover:bg-red-700 text-white border-red-700',
                                            ]"
                                            :title="item.is_active ? 'Click to Suspend' : 'Click to Activate'"
                                            @click="toggleKeyStatus(item)"
                                        >
                                            {{ item.is_active ? 'Active' : 'Suspended' }}
                                        </button>
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <button
                                            type="button"
                                            class="p-1.5 text-surface-400 hover:text-red-700 hover:bg-red-50 transition-colors cursor-pointer border border-transparent hover:border-red-200"
                                            title="Delete & Revoke Key"
                                            @click="deleteApiKey(item)"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="keyList.length === 0">
                                    <td colspan="7" class="py-8 text-center text-surface-500">
                                        Koi API key nahi hai. Naya showroom token generate karne ke liye <strong>"Issue New Shop Token"</strong> par click karein.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Integration Helper Card -->
                <div class="p-4 bg-amber-50/70 border border-amber-200 text-xs text-amber-950 space-y-1.5">
                    <div class="flex items-center gap-2 font-bold text-amber-900">
                        <Sparkles class="w-4 h-4 text-[#c08f34]" />
                        <span>KaratSetu ERP me AI Token Kaise Connect Karein:</span>
                    </div>
                    <p class="text-surface-700 leading-relaxed">
                        1. Upar table me se Store ka <strong>Live Secret Token</strong> copy karein.<br />
                        2. Maniratn ERP me jayein: <strong>Settings &rarr; Store & GST Profile &rarr; Karat AI Voice Assistant Configuration</strong>.<br />
                        3. <strong>Central AI Hub URL</strong> me <code>http://127.0.0.1:8001</code> aur <strong>Shop AI Secret Key</strong> me token paste karke Save karein!
                    </p>
                </div>
            </div>

            <!-- TAB 2: VOICE STUDIO & PLAYGROUND -->
            <div v-else-if="activeTab === 'playground'" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4 bg-white p-4 border border-surface-200 shadow-xs">
                    <div>
                        <h2 class="text-sm font-bold text-[#1c3633] uppercase tracking-wider">AI Studio Voice Playground</h2>
                        <p class="text-xs text-surface-500 mt-0.5">
                            Test Gemini Flash-Lite Tool Calling and Studio HD Voice Synthesis in real time.
                        </p>
                    </div>

                    <!-- Voice Persona Selector -->
                    <div class="flex items-center gap-2.5">
                        <Select
                            v-model="selectedApiKey"
                            :options="shopTokenOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Store Token"
                            class="h-9 text-xs min-w-[200px]"
                        />

                        <Select
                            v-model="selectedVoice"
                            :options="voiceOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select HD Voice"
                            class="h-9 text-xs min-w-[240px]"
                        />
                    </div>
                </div>

                <!-- Chat Box Screen -->
                <div class="bg-white border border-surface-200 shadow-xs flex flex-col h-[520px] overflow-hidden">
                    <!-- Message Log -->
                    <div ref="messageContainer" class="flex-1 min-h-0 overflow-y-auto p-5 space-y-4 bg-[#fcfbf7]">
                        <div
                            v-for="msg in messages"
                            :key="msg.id"
                            :class="['flex gap-3 max-w-full', msg.role === 'user' ? 'ml-auto flex-row-reverse max-w-[80%]' : 'mr-auto max-w-[90%]']"
                        >
                            <!-- Avatar -->
                            <div
                                :class="[
                                    'w-8 h-8 flex items-center justify-center shrink-0 text-xs font-bold mt-0.5 border',
                                    msg.role === 'user'
                                        ? 'bg-[#1c3633] text-white border-[#1c3633]'
                                        : 'bg-white border-[#c08f34] text-[#c08f34]',
                                ]"
                            >
                                <Sparkles v-if="msg.role === 'assistant'" class="w-4 h-4 text-[#c08f34]" />
                                <span v-else>You</span>
                            </div>

                            <!-- Bubble -->
                            <div class="space-y-2 flex-1 min-w-0">
                                <div
                                    :class="[
                                        'p-4 text-sm leading-relaxed border relative shadow-xs',
                                        msg.role === 'user'
                                            ? 'bg-[#1c3633] border-[#1c3633] text-white'
                                            : 'bg-white border-surface-200 text-[#1c3633]',
                                    ]"
                                >
                                    <p class="whitespace-pre-wrap text-[13.5px] leading-relaxed">{{ msg.content }}</p>

                                    <div class="flex items-center justify-between mt-3 pt-2 border-t" :class="msg.role === 'user' ? 'border-white/10' : 'border-surface-100'">
                                        <button
                                            v-if="msg.role === 'assistant' && msg.audio"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 hover:bg-amber-100 text-[#9b6f1e] text-xs font-semibold border border-amber-200 cursor-pointer transition-colors"
                                            @click="playAudio(msg.audio)"
                                        >
                                            <Volume2 class="w-3.5 h-3.5 text-[#c08f34]" />
                                            <span>Play HD Voice</span>
                                        </button>
                                        <span v-else />

                                        <span :class="['text-xs font-mono', msg.role === 'user' ? 'text-white/60' : 'text-surface-400']">
                                            {{ msg.timestamp }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Actions Dump -->
                                <div v-if="msg.actions && msg.actions.length > 0" class="p-3 bg-white border border-[#c08f34]/40 font-mono text-xs space-y-1">
                                    <div class="text-[#c08f34] font-bold text-[11px] uppercase">Detected Tool Action:</div>
                                    <pre class="overflow-x-auto text-[11px] text-surface-800 bg-surface-50 p-2 border border-surface-200">{{ JSON.stringify(msg.actions, null, 2) }}</pre>
                                </div>
                            </div>
                        </div>

                        <!-- Loading Wave -->
                        <div v-if="isLoading" class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-white border border-[#c08f34] flex items-center justify-center shrink-0">
                                <Sparkles class="w-4 h-4 text-[#c08f34] animate-spin" />
                            </div>
                            <div class="px-4 py-3 bg-white border border-surface-200 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-[#c08f34] animate-bounce" />
                                <span class="w-2 h-2 rounded-full bg-[#c08f34] animate-bounce [animation-delay:0.2s]" />
                                <span class="w-2 h-2 rounded-full bg-[#c08f34] animate-bounce [animation-delay:0.4s]" />
                                <span class="text-xs text-surface-600 font-medium ml-1">
                                    Processing Gemini Tool & HD Voice...
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Input Bar -->
                    <div class="p-4 bg-white border-t border-surface-200 space-y-3">
                        <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
                            <button
                                v-for="(p, idx) in samplePrompts"
                                :key="idx"
                                type="button"
                                class="shrink-0 px-3 py-1.5 text-xs font-semibold bg-surface-50 hover:bg-surface-100 border border-surface-200 text-[#1c3633] transition-all cursor-pointer"
                                @click="sendMessage(p)"
                            >
                                {{ p }}
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                :class="[
                                    'w-11 h-11 flex items-center justify-center shrink-0 transition-all cursor-pointer border',
                                    isListening
                                        ? 'bg-red-600 text-white border-red-700 animate-pulse'
                                        : 'bg-[#1c3633] hover:bg-[#254642] text-[#c08f34] border-[#1c3633]',
                                ]"
                                :title="isListening ? 'Stop Listening' : 'Click to Speak'"
                                @click="toggleListening"
                            >
                                <MicOff v-if="isListening" class="w-4.5 h-4.5" />
                                <Mic v-else class="w-4.5 h-4.5" />
                            </button>

                            <input
                                v-model="inputPrompt"
                                type="text"
                                placeholder="Speak or type command to test AI Hub..."
                                class="flex-1 h-11 px-3.5 text-sm bg-surface-50 border border-surface-200 text-[#1c3633] placeholder:text-surface-400 font-medium outline-hidden focus:border-[#1c3633]"
                                @keydown.enter="sendMessage()"
                            />

                            <button
                                type="button"
                                :disabled="!inputPrompt.trim() || isLoading"
                                class="w-11 h-11 flex items-center justify-center bg-[#1c3633] hover:bg-[#254642] text-[#c08f34] disabled:opacity-30 transition-all cursor-pointer shrink-0 border border-[#1c3633]"
                                @click="sendMessage()"
                            >
                                <Send class="w-4.5 h-4.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: DOCUMENTATION & API USAGE -->
            <div v-else-if="activeTab === 'docs'" class="space-y-4">
                <div class="bg-white p-4 border border-surface-200 shadow-xs">
                    <h2 class="text-sm font-bold text-[#1c3633] uppercase tracking-wider">Central Hub Architecture & API Integration</h2>
                    <p class="text-xs text-surface-500 mt-0.5">
                        Technical reference for connecting client ERP instances to the central brain.
                    </p>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <!-- cURL Sample Card -->
                    <div class="p-5 bg-white border border-surface-200 space-y-3 shadow-xs">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-2">
                            <span class="font-bold text-xs uppercase tracking-wider text-[#1c3633]">HTTP Request Spec</span>
                            <span class="px-2 py-0.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-[10px] font-mono font-bold">POST /api/ai/chat</span>
                        </div>
                        <pre class="p-3 bg-[#142826] text-emerald-300 font-mono text-xs overflow-x-auto leading-relaxed">
curl -X POST http://127.0.0.1:8001/api/ai/chat \
  -H "Authorization: Bearer mn_live_d8f4..." \
  -H "Content-Type: application/json" \
  -d '{
    "message": "14.5g 22k gold chain add kar do",
    "voice": "Aoede",
    "include_audio": true
  }'</pre>
                    </div>

                    <!-- Architecture Overview -->
                    <div class="p-5 bg-white border border-surface-200 space-y-3 shadow-xs">
                        <span class="font-bold text-xs uppercase tracking-wider text-[#1c3633] block border-b border-surface-100 pb-2">
                            Hub & Spoke Architecture Flow
                        </span>
                        <ul class="text-xs text-surface-700 space-y-2 leading-relaxed list-disc list-inside">
                            <li><strong>Tenant Isolation:</strong> Har showroom ka apna alag secret token hota hai (<code>mn_live_...</code>).</li>
                            <li><strong>Security:</strong> Invalid ya suspended keys par 401/403 error throw hota hai.</li>
                            <li><strong>Cost Control:</strong> 7-Day HD Audio caching ensures repeat bhav inquiries cost ₹0.</li>
                            <li><strong>Local DB Safety:</strong> Asali data creation client ERP par execute hoti hai, Central Hub me zero customer data store hota hai.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- 📝 Generate New Token Modal (Royal Enterprise Dialog) -->
        <Teleport to="body">
            <div
                v-if="isCreateModalOpen"
                class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/60 backdrop-blur-md p-4 animate-in fade-in duration-200"
                @click.self="isCreateModalOpen = false"
            >
                <div class="w-full max-w-lg bg-white border border-[#c08f34]/50 shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200">
                    <!-- Royal Modal Header Banner -->
                    <div class="bg-[#1c3633] p-5 border-b border-[#c08f34]/30 text-white flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-white/10 border border-[#c08f34]/50 flex items-center justify-center text-[#c08f34]">
                                <Key class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white uppercase tracking-wider font-serif">Issue Showroom API Key</h3>
                                <p class="text-[11px] text-white/70">Connect a jewellery store instance to Central AI Hub</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="text-white/60 hover:text-white cursor-pointer transition-colors p-1"
                            @click="isCreateModalOpen = false"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-4 text-xs bg-white">
                        <div>
                            <label class="block font-bold text-[#1c3633] uppercase tracking-wider mb-1.5">
                                Showroom / Store Name <span class="text-[#c08f34]">*</span>
                            </label>
                            <input
                                v-model="newKeyForm.business_name"
                                type="text"
                                placeholder="e.g. Maniratn Jewellers (Jaipur Branch)"
                                class="w-full h-10 px-3.5 border border-surface-300 bg-surface-50 text-[#1c3633] font-medium outline-hidden focus:border-[#1c3633] focus:bg-white transition-colors"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-[#1c3633] uppercase tracking-wider mb-1.5">Environment Type</label>
                                <Select
                                    v-model="newKeyForm.type"
                                    :options="typeOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Select Environment"
                                    class="w-full text-xs"
                                />
                            </div>

                            <div>
                                <label class="block font-bold text-[#1c3633] uppercase tracking-wider mb-1.5">Subscription Tier</label>
                                <Select
                                    v-model="newKeyForm.plan"
                                    :options="planOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Select Plan"
                                    class="w-full text-xs"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-[#1c3633] uppercase tracking-wider mb-1.5">Contact Phone</label>
                                <input
                                    v-model="newKeyForm.contact_phone"
                                    type="text"
                                    placeholder="+91 98765 43210"
                                    class="w-full h-10 px-3.5 border border-surface-300 bg-surface-50 text-[#1c3633] font-medium outline-hidden focus:border-[#1c3633] focus:bg-white"
                                />
                            </div>

                            <div>
                                <label class="block font-bold text-[#1c3633] uppercase tracking-wider mb-1.5">Contact Email</label>
                                <input
                                    v-model="newKeyForm.contact_email"
                                    type="email"
                                    placeholder="store@maniratn.com"
                                    class="w-full h-10 px-3.5 border border-surface-300 bg-surface-50 text-[#1c3633] font-medium outline-hidden focus:border-[#1c3633] focus:bg-white"
                                />
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5 pt-3 border-t border-surface-200">
                            <input
                                type="checkbox"
                                id="modal_voice_toggle"
                                v-model="newKeyForm.voice_enabled"
                                class="w-4 h-4 text-[#1c3633] accent-[#1c3633] cursor-pointer"
                            />
                            <label for="modal_voice_toggle" class="font-bold text-[#1c3633] cursor-pointer">
                                Enable Gemini Studio HD Voice for this Showroom
                            </label>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="p-4 bg-surface-50 border-t border-surface-200 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            class="px-4 h-9 border border-surface-300 text-xs font-bold text-surface-700 hover:bg-surface-100 cursor-pointer transition-colors"
                            @click="isCreateModalOpen = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            :disabled="isSubmittingKey"
                            class="px-5 h-9 bg-[#1c3633] hover:bg-[#254642] text-white text-xs font-bold flex items-center gap-2 cursor-pointer transition-all shadow-xs border border-[#c08f34]/30"
                            @click="submitCreateKey"
                        >
                            <Sparkles class="w-4 h-4 text-[#c08f34]" />
                            <span>{{ isSubmittingKey ? 'Generating Token...' : 'Create & Issue Token' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
