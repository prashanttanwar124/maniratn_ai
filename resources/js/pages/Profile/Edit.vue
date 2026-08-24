<script setup>
import AppLayout from '@/layout/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import InputText from 'primevue/inputtext';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

// 1. Profile Information Form
const profileForm = useForm({
    name: props.user.name,
    email: props.user.email,
});

const submitProfile = () => {
    profileForm.put('/profile', {
        preserveScroll: true,
    });
};

// 2. Change Password Form
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const showCurrentPass = ref(false);
const showNewPass = ref(false);

const submitPassword = () => {
    passwordForm.put('/profile/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
        },
    });
};

const getRoleBadgeClasses = (role) => {
    switch (role) {
        case 'Super Admin':
            return 'bg-amber-50 text-[#9b6f1e] border-[#e8dfcf]';
        case 'AI Operator':
            return 'bg-emerald-50 text-emerald-800 border-emerald-200';
        default:
            return 'bg-surface-100 text-surface-700 border-surface-200';
    }
};

const getInitials = (name) => {
    return (name || 'User')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase())
        .join('');
};
</script>

<template>
    <Head title="My Profile & Password - Maniratn AI" />

    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- 🔔 Flash Alert Messages -->
            <div v-if="flashSuccess" class="p-4 bg-emerald-50 border border-emerald-300 text-emerald-900 text-xs flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2 font-medium">
                    <i class="pi pi-check-circle text-emerald-600 text-sm"></i>
                    <span>{{ flashSuccess }}</span>
                </div>
            </div>

            <div v-if="flashError" class="p-4 bg-red-50 border border-red-300 text-red-900 text-xs flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2 font-medium">
                    <i class="pi pi-exclamation-triangle text-red-600 text-sm"></i>
                    <span>{{ flashError }}</span>
                </div>
            </div>

            <!-- 🏛️ 1. Hero Header Card -->
            <div class="border border-surface-200 bg-white p-5 shadow-xs flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center bg-[#1c3633] text-white border border-[#c08f34]/40 shadow-xs text-base font-bold">
                        {{ getInitials(user.name) }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold tracking-tight text-[#1c3633]">{{ user.name }}</h1>
                            <span :class="['inline-flex items-center gap-1 px-2.5 py-0.5 text-[10px] font-bold border', getRoleBadgeClasses(user.role)]">
                                <i v-if="user.role === 'Super Admin'" class="pi pi-crown text-[9px]"></i>
                                {{ user.role }}
                            </span>
                        </div>
                        <p class="text-xs text-surface-500 font-medium font-mono mt-0.5">
                            {{ user.email }} • Registered: {{ user.created_at }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- 🔒 2. Two Section Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Card A: Account Details -->
                <div class="bg-white border border-surface-200 shadow-xs flex flex-col">
                    <div class="px-5 py-4 border-b border-surface-200 bg-surface-50 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-user text-[#c08f34]"></i>
                            <h2 class="text-sm font-bold text-[#1c3633] uppercase tracking-wider">Profile Information</h2>
                        </div>
                    </div>

                    <form @submit.prevent="submitProfile" class="p-5 space-y-4 flex-1 flex flex-col justify-between">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-surface-700 uppercase tracking-wider mb-1.5">
                                    Display Name <span class="text-red-500">*</span>
                                </label>
                                <InputText
                                    v-model="profileForm.name"
                                    class="w-full text-xs"
                                    placeholder="Your Name"
                                    required
                                />
                                <small v-if="profileForm.errors.name" class="text-red-500 text-[11px] block mt-1">
                                    {{ profileForm.errors.name }}
                                </small>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-surface-700 uppercase tracking-wider mb-1.5">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <InputText
                                    v-model="profileForm.email"
                                    type="email"
                                    class="w-full text-xs"
                                    placeholder="admin@maniratn.ai"
                                    required
                                />
                                <small v-if="profileForm.errors.email" class="text-red-500 text-[11px] block mt-1">
                                    {{ profileForm.errors.email }}
                                </small>
                            </div>

                            <div class="p-3 bg-surface-50 border border-surface-200 text-xs text-surface-600 space-y-1">
                                <p class="font-bold text-[#1c3633]">System Access Level</p>
                                <p class="text-[11px]">Your account role is managed by the system administrator.</p>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-surface-100 flex justify-end">
                            <button
                                type="submit"
                                :disabled="profileForm.processing || !profileForm.isDirty"
                                class="px-5 py-2.5 bg-[#1c3633] hover:bg-[#264a46] text-white text-xs font-bold transition-all shadow-xs cursor-pointer border border-[#c08f34]/40 disabled:opacity-50"
                            >
                                <i class="pi pi-check text-xs mr-1 text-[#c08f34]"></i>
                                Save Profile Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Card B: Change Password -->
                <div class="bg-white border border-surface-200 shadow-xs flex flex-col">
                    <div class="px-5 py-4 border-b border-surface-200 bg-surface-50 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-lock text-[#c08f34]"></i>
                            <h2 class="text-sm font-bold text-[#1c3633] uppercase tracking-wider">Change Password</h2>
                        </div>
                    </div>

                    <form @submit.prevent="submitPassword" class="p-5 space-y-4 flex-1 flex flex-col justify-between">
                        <div class="space-y-4">
                            <!-- Current Password -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold text-surface-700 uppercase tracking-wider">
                                        Current Password <span class="text-red-500">*</span>
                                    </label>
                                    <button
                                        type="button"
                                        @click="showCurrentPass = !showCurrentPass"
                                        class="text-[11px] text-[#c08f34] hover:underline cursor-pointer"
                                    >
                                        {{ showCurrentPass ? 'Hide' : 'Show' }}
                                    </button>
                                </div>
                                <input
                                    v-model="passwordForm.current_password"
                                    :type="showCurrentPass ? 'text' : 'password'"
                                    class="w-full h-10 px-3 border border-surface-300 bg-white text-xs text-surface-800 focus:outline-none focus:border-[#c08f34]"
                                    placeholder="Enter your current password"
                                    required
                                />
                                <small v-if="passwordForm.errors.current_password" class="text-red-500 text-[11px] block mt-1">
                                    {{ passwordForm.errors.current_password }}
                                </small>
                            </div>

                            <!-- New Password -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold text-surface-700 uppercase tracking-wider">
                                        New Password <span class="text-red-500">*</span>
                                    </label>
                                    <button
                                        type="button"
                                        @click="showNewPass = !showNewPass"
                                        class="text-[11px] text-[#c08f34] hover:underline cursor-pointer"
                                    >
                                        {{ showNewPass ? 'Hide' : 'Show' }}
                                    </button>
                                </div>
                                <input
                                    v-model="passwordForm.password"
                                    :type="showNewPass ? 'text' : 'password'"
                                    class="w-full h-10 px-3 border border-surface-300 bg-white text-xs text-surface-800 focus:outline-none focus:border-[#c08f34]"
                                    placeholder="Minimum 6 characters"
                                    required
                                />
                                <small v-if="passwordForm.errors.password" class="text-red-500 text-[11px] block mt-1">
                                    {{ passwordForm.errors.password }}
                                </small>
                            </div>

                            <!-- Confirm New Password -->
                            <div>
                                <label class="block text-xs font-bold text-surface-700 uppercase tracking-wider mb-1.5">
                                    Confirm New Password <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="passwordForm.password_confirmation"
                                    :type="showNewPass ? 'text' : 'password'"
                                    class="w-full h-10 px-3 border border-surface-300 bg-white text-xs text-surface-800 focus:outline-none focus:border-[#c08f34]"
                                    placeholder="Re-type new password"
                                    required
                                />
                                <small v-if="passwordForm.errors.password_confirmation" class="text-red-500 text-[11px] block mt-1">
                                    {{ passwordForm.errors.password_confirmation }}
                                </small>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-surface-100 flex justify-end">
                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="px-5 py-2.5 bg-[#1c3633] hover:bg-[#264a46] text-white text-xs font-bold transition-all shadow-xs cursor-pointer border border-[#c08f34]/40 disabled:opacity-50"
                            >
                                <i class="pi pi-shield text-xs mr-1 text-[#c08f34]"></i>
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
