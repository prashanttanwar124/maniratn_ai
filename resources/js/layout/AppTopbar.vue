<script setup>
import { useLayout } from '@/layout/composables/layout';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';

const { toggleMenu, toggleDarkMode, isDarkTheme } = useLayout();
const page = usePage();
const user = computed(() => page.props.auth?.user);

const isUserMenuOpen = ref(false);
const userMenuRef = ref(null);

const initials = computed(() => {
    const name = user.value?.name || 'Admin';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
});

const toggleUserMenu = () => {
    isUserMenuOpen.value = !isUserMenuOpen.value;
};

const closeUserMenu = (event) => {
    if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
        isUserMenuOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeUserMenu);
});

onUnmounted(() => {
    document.removeEventListener('click', closeUserMenu);
});
</script>

<template>
    <div class="layout-topbar bg-white border-b border-surface-200 shadow-xs h-16 px-4 md:px-6 flex items-center justify-between">
        <!-- 1. LEFT: Brand & Navigation Toggle -->
        <div class="layout-topbar-start flex items-center gap-3">
            <button
                type="button"
                class="inline-flex items-center justify-center h-9 w-9 border border-surface-200 bg-surface-50 hover:bg-surface-100 text-surface-700 hover:text-[#1c3633] transition-all cursor-pointer"
                @click="toggleMenu"
                aria-label="Toggle navigation"
                title="Toggle Sidebar"
            >
                <i class="pi pi-bars text-sm"></i>
            </button>

            <Link href="/" class="layout-topbar-brand flex items-center gap-2.5 group">
                <div class="flex h-9 w-9 items-center justify-center bg-[#1c3633] text-white border border-[#c08f34]/40 shadow-xs">
                    <i class="pi pi-sparkles text-sm text-[#c08f34]"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-[1.2rem] font-bold leading-none tracking-tight text-[#1c3633]">
                        Maniratn <span class="text-[#c08f34]">AI</span>
                    </span>
                    <span class="mt-0.5 text-[9px] font-bold uppercase tracking-[0.16em] text-surface-400 leading-none hidden sm:block">
                        Central Hub & Voice Copilot
                    </span>
                </div>
            </Link>
        </div>

        <!-- 2. CENTER: Live AI Engine Status Pill -->
        <div class="hidden lg:flex items-center gap-2 px-3.5 py-1.5 bg-[#1c3633]/5 border border-[#1c3633]/15 text-xs text-[#1c3633]">
            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span class="font-bold text-[11px] uppercase tracking-wider text-[#1c3633]">Engine: Gemini Flash-Lite</span>
            <span class="text-surface-300">|</span>
            <span class="text-[11px] text-surface-600 font-medium">1.0s Latency</span>
            <span class="text-surface-300">|</span>
            <span class="text-[11px] text-[#9b6f1e] font-semibold">HD Studio Voice Ready</span>
        </div>

        <!-- 3. RIGHT: ERP Link & Profile -->
        <div class="layout-topbar-actions flex items-center gap-2.5">
            <!-- Launch Showroom ERP Instance Button -->
            <a
                href="http://127.0.0.1:8000"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 h-9 px-3.5 bg-[#1c3633] hover:bg-[#254642] text-white text-xs font-semibold transition-all shadow-xs cursor-pointer border border-[#c08f34]/30"
                title="Launch KaratSetu Jewellery ERP"
            >
                <i class="pi pi-external-link text-xs text-[#c08f34]"></i>
                <span class="hidden sm:inline font-bold">Open Showroom ERP</span>
                <span class="sm:hidden font-bold">ERP</span>
            </a>

            <!-- System Uptime Badge -->
            <div class="hidden md:inline-flex items-center gap-1.5 px-2.5 h-9 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                <span class="text-[11px]">API 99.99% Live</span>
            </div>

            <div class="h-5 w-px bg-surface-200 hidden sm:block"></div>

            <!-- User Profile Avatar & Dropdown -->
            <div ref="userMenuRef" class="relative">
                <button
                    type="button"
                    @click.stop="toggleUserMenu"
                    class="flex items-center gap-2 px-2 h-9 border border-transparent hover:border-surface-200 hover:bg-surface-50 transition-all cursor-pointer group"
                >
                    <div class="h-7 w-7 bg-[#1c3633] text-white flex items-center justify-center text-[10px] font-bold shadow-xs">
                        {{ initials }}
                    </div>
                    <div class="hidden xl:flex flex-col text-left">
                        <span class="text-xs font-bold text-[#1c3633] leading-none">{{ user?.name || 'Master Admin' }}</span>
                        <span class="text-[10px] text-surface-400 font-medium leading-tight mt-0.5">Central Hub Master</span>
                    </div>
                    <i class="pi pi-chevron-down text-[10px] text-surface-400 group-hover:text-surface-700 transition-transform" :class="isUserMenuOpen ? 'rotate-180' : ''"></i>
                </button>

                <!-- Dropdown Menu -->
                <div
                    v-if="isUserMenuOpen"
                    class="absolute right-0 mt-2 w-56 bg-white border border-surface-200 shadow-xl z-50 py-1.5 divide-y divide-surface-100 animate-in fade-in zoom-in-95 duration-100"
                >
                    <div class="px-3.5 py-2.5">
                        <p class="text-xs font-bold text-[#1c3633]">{{ user?.name || 'Master Administrator' }}</p>
                        <p class="text-[11px] text-surface-400 truncate">{{ user?.email || 'admin@maniratn.ai' }}</p>
                    </div>

                    <div class="py-1">
                        <Link
                            href="/profile"
                            class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-surface-700 hover:bg-surface-50 mx-1"
                            @click="isUserMenuOpen = false"
                        >
                            <i class="pi pi-shield text-xs text-[#c08f34]"></i>
                            <span>My Profile & Password</span>
                        </Link>
                        <Link
                            href="/users"
                            class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-surface-700 hover:bg-surface-50 mx-1"
                            @click="isUserMenuOpen = false"
                        >
                            <i class="pi pi-users text-xs text-[#c08f34]"></i>
                            <span>User & Role Access</span>
                        </Link>
                    </div>

                    <div class="py-1">
                        <a
                            href="http://127.0.0.1:8000"
                            target="_blank"
                            class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-surface-700 hover:bg-surface-50 mx-1"
                        >
                            <i class="pi pi-box text-xs text-[#c08f34]"></i>
                            <span>Jewellery Showroom ERP</span>
                        </a>
                    </div>

                    <div class="py-1">
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="w-full flex items-center gap-2.5 px-3.5 py-2 text-xs text-red-700 hover:bg-red-50 text-left cursor-pointer transition-colors"
                        >
                            <i class="pi pi-power-off text-xs text-red-600"></i>
                            <span class="font-bold">Log Out of Central Hub</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
