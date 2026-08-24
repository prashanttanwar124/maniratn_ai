<script setup>
import AppLayout from '@/layout/AppLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    roles: {
        type: Array,
        default: () => ['Super Admin', 'AI Operator', 'Viewer'],
    },
    stats: {
        type: Object,
        default: () => ({
            total_users: 1,
            super_admins: 1,
            operators: 0,
            viewers: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({ search: '', role: '' }),
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const searchQuery = ref(props.filters.search || '');
const roleFilter = ref(props.filters.role || '');

const roleSelectOptions = computed(() => [
    { label: 'All Roles', value: '' },
    ...props.roles.map((r) => ({ label: r, value: r })),
]);

const formRoleOptions = computed(() =>
    props.roles.map((r) => ({ label: r, value: r }))
);

const isModalOpen = ref(false);
const isEditing = ref(false);
const editingUserId = ref(null);
const showPassword = ref(false);

const userForm = useForm({
    name: '',
    email: '',
    role: 'AI Operator',
    password: '',
});

const openCreateModal = () => {
    isEditing.value = false;
    editingUserId.value = null;
    showPassword.value = false;
    userForm.reset();
    userForm.clearErrors();
    userForm.role = 'AI Operator';
    isModalOpen.value = true;
};

const openEditModal = (user) => {
    isEditing.value = true;
    editingUserId.value = user.id;
    showPassword.value = false;
    userForm.clearErrors();
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.role = user.role;
    userForm.password = '';
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    userForm.reset();
    userForm.clearErrors();
};

const submitUser = () => {
    if (isEditing.value) {
        userForm.put(`/users/${editingUserId.value}`, {
            onSuccess: () => closeModal(),
        });
    } else {
        userForm.post('/users', {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteUser = (user) => {
    if (user.is_current_user) {
        alert('You cannot delete your own logged-in master account.');
        return;
    }
    if (confirm(`Are you sure you want to delete user "${user.name}"? This action cannot be undone.`)) {
        router.delete(`/users/${user.id}`);
    }
};

const handleFilter = () => {
    router.get(
        '/users',
        {
            search: searchQuery.value || undefined,
            role: roleFilter.value || undefined,
        },
        { preserveState: true, replace: true }
    );
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
    <Head title="User & Role Access - Maniratn AI" />

    <AppLayout>
        <div class="space-y-6">
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
            <div class="border border-surface-200 bg-white p-5 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center bg-[#1c3633] text-white border border-[#c08f34]/40 shadow-xs">
                            <i class="pi pi-users text-sm text-[#c08f34]"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold tracking-tight text-[#1c3633]">User & Role Access</h1>
                            <p class="text-xs text-surface-500 font-medium">
                                Manage Master Administrators, Staff Operators, and System Role Permissions.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#1c3633] hover:bg-[#264a46] text-white text-xs font-bold transition-all shadow-xs cursor-pointer border border-[#c08f34]/40"
                    >
                        <i class="pi pi-user-plus text-xs text-[#c08f34]"></i>
                        <span>Add New User</span>
                    </button>
                </div>
            </div>

            <!-- 📊 2. Quick Stat Counters -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white border border-surface-200 p-4 shadow-xs">
                    <p class="text-[11px] font-bold text-surface-400 uppercase tracking-wider">Total Accounts</p>
                    <p class="text-2xl font-bold text-[#1c3633] mt-1">{{ stats.total_users }}</p>
                </div>

                <div class="bg-white border border-amber-200/80 p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-bold text-[#9b6f1e] uppercase tracking-wider">Super Admins</p>
                        <i class="pi pi-crown text-xs text-[#c08f34]"></i>
                    </div>
                    <p class="text-2xl font-bold text-[#9b6f1e] mt-1">{{ stats.super_admins }}</p>
                </div>

                <div class="bg-white border border-emerald-200/80 p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-bold text-emerald-800 uppercase tracking-wider">AI Operators</p>
                        <i class="pi pi-bolt text-xs text-emerald-600"></i>
                    </div>
                    <p class="text-2xl font-bold text-emerald-800 mt-1">{{ stats.operators }}</p>
                </div>

                <div class="bg-white border border-surface-200 p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-bold text-surface-500 uppercase tracking-wider">Viewers</p>
                        <i class="pi pi-eye text-xs text-surface-400"></i>
                    </div>
                    <p class="text-2xl font-bold text-surface-700 mt-1">{{ stats.viewers }}</p>
                </div>
            </div>

            <!-- 🔍 3. Search & Filter Bar -->
            <div class="bg-white border border-surface-200 p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
                <div class="flex-1 w-full flex flex-col sm:flex-row items-center gap-3">
                    <div class="relative w-full sm:w-80">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-xs"></i>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by name or email..."
                            class="w-full h-10 pl-9 pr-3 border border-surface-300 bg-white text-xs text-surface-800 focus:outline-none focus:border-[#c08f34]"
                            @keyup.enter="handleFilter"
                        />
                    </div>

                    <div class="w-full sm:w-52">
                        <Select
                            v-model="roleFilter"
                            :options="roleSelectOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Filter by Role"
                            class="w-full text-xs"
                            @change="handleFilter"
                        />
                    </div>

                    <button
                        type="button"
                        @click="handleFilter"
                        class="px-4 h-10 bg-surface-100 hover:bg-surface-200 text-surface-800 text-xs font-semibold border border-surface-300 cursor-pointer"
                    >
                        Filter
                    </button>
                </div>
            </div>

            <!-- 📋 4. Users Table -->
            <div class="bg-white border border-surface-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-surface-50 border-b border-surface-200 text-surface-600 font-bold uppercase tracking-wider text-[10px]">
                                <th class="py-3.5 px-4">User</th>
                                <th class="py-3.5 px-4">Email Address</th>
                                <th class="py-3.5 px-4">Assigned Role</th>
                                <th class="py-3.5 px-4">Registered Date</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100">
                            <tr v-if="users.length === 0">
                                <td colspan="5" class="py-12 text-center text-surface-400 font-medium">
                                    <i class="pi pi-users text-2xl mb-2 text-surface-300 block"></i>
                                    No user accounts match your search criteria.
                                </td>
                            </tr>

                            <tr
                                v-for="user in users"
                                :key="user.id"
                                class="hover:bg-surface-50/70 transition-colors"
                            >
                                <!-- Avatar & Name -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 bg-[#1c3633] text-white flex items-center justify-center text-xs font-bold border border-[#c08f34]/30 shrink-0">
                                            {{ getInitials(user.name) }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-[#1c3633] flex items-center gap-1.5">
                                                <span>{{ user.name }}</span>
                                                <span v-if="user.is_current_user" class="text-[9px] px-1.5 py-0.2 bg-emerald-100 text-emerald-800 border border-emerald-200 font-semibold">
                                                    You
                                                </span>
                                            </p>
                                            <p class="text-[10px] text-surface-400">ID: #{{ user.id }}</p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Email -->
                                <td class="py-3 px-4 font-mono text-surface-700">
                                    {{ user.email }}
                                </td>

                                <!-- Role Badge -->
                                <td class="py-3 px-4">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold border',
                                            getRoleBadgeClasses(user.role),
                                        ]"
                                    >
                                        <i v-if="user.role === 'Super Admin'" class="pi pi-crown text-[10px]"></i>
                                        <i v-else-if="user.role === 'AI Operator'" class="pi pi-bolt text-[10px]"></i>
                                        <i v-else class="pi pi-eye text-[10px]"></i>
                                        <span>{{ user.role }}</span>
                                    </span>
                                </td>

                                <!-- Registered Date -->
                                <td class="py-3 px-4 text-surface-500 font-medium">
                                    {{ user.created_at }}
                                </td>

                                <!-- Actions -->
                                <td class="py-3 px-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="openEditModal(user)"
                                            class="h-7 px-2.5 border border-surface-300 bg-white hover:bg-surface-100 text-[#1c3633] text-xs font-semibold cursor-pointer transition-colors"
                                            title="Edit User"
                                        >
                                            <i class="pi pi-pencil text-[10px] mr-1"></i> Edit
                                        </button>

                                        <button
                                            v-if="!user.is_current_user"
                                            type="button"
                                            @click="deleteUser(user)"
                                            class="h-7 px-2.5 border border-red-200 bg-white hover:bg-red-50 text-red-600 text-xs font-semibold cursor-pointer transition-colors"
                                            title="Delete User"
                                        >
                                            <i class="pi pi-trash text-[10px] mr-1"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 👑 5. Create / Edit User Dialog (PrimeVue Standard Modal matching ERP) -->
        <Dialog
            v-model:visible="isModalOpen"
            :header="isEditing ? 'Edit User Account & Role' : 'Create New System User'"
            modal
            :style="{ width: '32rem' }"
            :breakpoints="{ '640px': '95vw' }"
            @hide="closeModal"
        >
            <form @submit.prevent="submitUser" class="space-y-4 pt-2">
                <div>
                    <label class="block text-xs font-bold text-surface-700 uppercase tracking-wider mb-1.5">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        v-model="userForm.name"
                        class="w-full text-xs"
                        placeholder="e.g. Rahul Sharma"
                        required
                    />
                    <small v-if="userForm.errors.name" class="text-red-500 text-[11px] block mt-1">
                        {{ userForm.errors.name }}
                    </small>
                </div>

                <div>
                    <label class="block text-xs font-bold text-surface-700 uppercase tracking-wider mb-1.5">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        v-model="userForm.email"
                        type="email"
                        class="w-full text-xs"
                        placeholder="rahul@maniratnjewellers.com"
                        required
                    />
                    <small v-if="userForm.errors.email" class="text-red-500 text-[11px] block mt-1">
                        {{ userForm.errors.email }}
                    </small>
                </div>

                <div>
                    <label class="block text-xs font-bold text-surface-700 uppercase tracking-wider mb-1.5">
                        Assigned System Role <span class="text-red-500">*</span>
                    </label>
                    <Select
                        v-model="userForm.role"
                        :options="formRoleOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full text-xs"
                        required
                    />
                    <small class="text-surface-400 text-[10px] block mt-1">
                        Super Admin has full privileges; AI Operator can test playground; Viewer is read-only.
                    </small>
                    <small v-if="userForm.errors.role" class="text-red-500 text-[11px] block mt-1">
                        {{ userForm.errors.role }}
                    </small>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-surface-700 uppercase tracking-wider">
                            {{ isEditing ? 'New Password (Optional)' : 'Password' }}
                            <span v-if="!isEditing" class="text-red-500">*</span>
                        </label>
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="text-[11px] text-[#c08f34] hover:underline cursor-pointer"
                        >
                            {{ showPassword ? 'Hide' : 'Show' }}
                        </button>
                    </div>
                    <InputText
                        v-model="userForm.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="w-full text-xs"
                        :placeholder="isEditing ? 'Leave blank to keep existing password' : 'Minimum 6 characters'"
                        :required="!isEditing"
                    />
                    <small v-if="userForm.errors.password" class="text-red-500 text-[11px] block mt-1">
                        {{ userForm.errors.password }}
                    </small>
                </div>
            </form>

            <template #footer>
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-surface-200">
                    <Button label="Cancel" text severity="secondary" size="small" @click="closeModal" />
                    <Button
                        :label="isEditing ? 'Update User' : 'Save New User'"
                        icon="pi pi-check"
                        size="small"
                        :loading="userForm.processing"
                        @click="submitUser"
                    />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
