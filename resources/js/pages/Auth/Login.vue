<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, Key, Lock, Mail, Shield, Sparkles } from 'lucide-vue-next';
import { ref } from 'vue';

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Master Login — Maniratn AI Central Hub" />

    <div class="min-h-screen bg-[#fcfbf7] flex flex-col justify-center items-center p-4 relative selection:bg-[#1c3633] selection:text-white">
        <!-- Subtle Background Decorative Gradients -->
        <div class="absolute inset-0 bg-[radial-gradient(#c08f34_1px,transparent_1px)] [background-size:24px_24px] opacity-15 pointer-events-none" />

        <div class="w-full max-w-md relative z-10 space-y-6">
            <!-- Brand Logo Header -->
            <div class="text-center space-y-2">
                <div class="inline-flex items-center justify-center w-14 h-14 bg-[#1c3633] border-2 border-[#c08f34] shadow-md mb-1">
                    <Sparkles class="w-7 h-7 text-[#c08f34]" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-[#1c3633] font-serif">
                    Maniratn <span class="text-[#c08f34]">AI Hub</span>
                </h1>
                <p class="text-xs text-surface-500 font-medium">
                    Central Intelligence & Voice Gateway Access
                </p>
            </div>

            <!-- Login Card -->
            <div class="bg-white border border-surface-200 shadow-xl overflow-hidden">
                <!-- Royal Top Accent Strip -->
                <div class="h-1.5 bg-gradient-to-r from-[#1c3633] via-[#c08f34] to-[#1c3633]" />

                <div class="p-7 space-y-5">
                    <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                        <div class="flex items-center gap-2">
                            <Shield class="w-4 h-4 text-[#c08f34]" />
                            <span class="text-xs font-bold uppercase tracking-wider text-[#1c3633]">Master Admin Login</span>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold bg-amber-50 border border-amber-200 text-amber-900">
                            Secured
                        </span>
                    </div>

                    <!-- Validation Error Alert -->
                    <div
                        v-if="form.errors.email || form.errors.password"
                        class="p-3 bg-red-50 border border-red-200 text-red-900 text-xs flex items-start gap-2 animate-in fade-in duration-200"
                    >
                        <span class="font-bold">Error:</span>
                        <span>{{ form.errors.email || form.errors.password }}</span>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4 text-xs">
                        <!-- Email Field -->
                        <div class="space-y-1.5">
                            <label for="email" class="block font-bold text-[#1c3633] uppercase tracking-wider">
                                Administrator Email
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-surface-400">
                                    <Mail class="w-4 h-4" />
                                </div>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autofocus
                                    placeholder="admin@maniratn.ai"
                                    class="w-full h-10 pl-9 pr-3.5 border border-surface-300 bg-surface-50 text-[#1c3633] font-medium outline-hidden focus:border-[#1c3633] focus:bg-white transition-colors"
                                />
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label for="password" class="block font-bold text-[#1c3633] uppercase tracking-wider">
                                    Master Password
                                </label>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-surface-400">
                                    <Lock class="w-4 h-4" />
                                </div>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    placeholder="••••••••••••"
                                    class="w-full h-10 pl-9 pr-10 border border-surface-300 bg-surface-50 text-[#1c3633] font-medium outline-hidden focus:border-[#1c3633] focus:bg-white transition-colors"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-surface-400 hover:text-surface-700 cursor-pointer"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" class="w-4 h-4" />
                                    <Eye v-else class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center justify-between pt-1">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input
                                    v-model="form.remember"
                                    type="checkbox"
                                    class="w-4 h-4 text-[#1c3633] accent-[#1c3633] cursor-pointer"
                                />
                                <span class="text-surface-700 font-medium">Remember on this device</span>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full h-11 bg-[#1c3633] hover:bg-[#254642] text-white font-bold flex items-center justify-center gap-2 cursor-pointer transition-all shadow-sm border border-[#c08f34]/40 disabled:opacity-50 mt-2"
                        >
                            <Key class="w-4 h-4 text-[#c08f34]" />
                            <span>{{ form.processing ? 'Verifying Access...' : 'Sign In to Central Hub' }}</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Footer Note -->
            <div class="text-center text-[11px] text-surface-400">
                <p>&copy; 2026 Maniratn AI Central Engine &bull; KaratSetu Ecosystem</p>
            </div>
        </div>
    </div>
</template>
