<script setup>
import AppLayout from '@/layout/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    roles: {
        type: Array,
        default: () => [],
    },
    roleOptions: {
        type: Array,
        default: () => [],
    },
    permissions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const showUserDialog = ref(false);
const showRoleDialog = ref(false);
const showPermissionDialog = ref(false);

const editingUser = ref(null);
const editingRole = ref(null);
const editingPermission = ref(null);

// 1. User Form
const userForm = useForm({
    id: null,
    name: '',
    email: '',
    password: '',
    role: 'AI Operator',
    permissions: [],
});

// 2. Role Form
const roleForm = useForm({
    id: null,
    name: '',
    permissions: [],
});

// 3. Permission Form
const permissionForm = useForm({
    id: null,
    name: '',
});

const selectedRole = computed(() => {
    return props.roles.find((r) => r.name === userForm.role) || null;
});

const inheritedPermissionSet = computed(() => {
    return new Set(selectedRole.value?.permissions || []);
});

const isInheritedPermission = (permissionKey) => {
    return inheritedPermissionSet.value.has(permissionKey);
};

// --- USER ACTIONS ---
const openUserDialog = () => {
    userForm.reset();
    userForm.clearErrors();
    userForm.id = null;
    userForm.role = props.roleOptions[0]?.value || 'AI Operator';
    userForm.permissions = [];
    editingUser.value = null;
    showUserDialog.value = true;
};

const editUser = (user) => {
    userForm.reset();
    userForm.clearErrors();
    userForm.id = user.id;
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.password = '';
    userForm.role = user.roles[0] || 'AI Operator';
    userForm.permissions = [...(user.permissions || [])];
    editingUser.value = user;
    showUserDialog.value = true;
};

const saveUser = () => {
    if (editingUser.value) {
        userForm.put(`/users/${editingUser.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showUserDialog.value = false;
                editingUser.value = null;
            },
        });
    } else {
        userForm.post('/users', {
            preserveScroll: true,
            onSuccess: () => {
                showUserDialog.value = false;
                editingUser.value = null;
            },
        });
    }
};

const deleteUser = (user) => {
    if (user.is_current_user) {
        alert('You cannot delete your own logged-in master account.');
        return;
    }
    if (confirm(`Are you sure you want to delete user "${user.name}"?`)) {
        router.delete(`/users/${user.id}`, { preserveScroll: true });
    }
};

// --- ROLE ACTIONS ---
const openRoleDialog = () => {
    roleForm.reset();
    roleForm.clearErrors();
    roleForm.id = null;
    roleForm.permissions = [];
    editingRole.value = null;
    showRoleDialog.value = true;
};

const editRole = (role) => {
    roleForm.reset();
    roleForm.clearErrors();
    roleForm.id = role.id;
    roleForm.name = role.name;
    roleForm.permissions = [...(role.permissions || [])];
    editingRole.value = role;
    showRoleDialog.value = true;
};

const saveRole = () => {
    if (editingRole.value) {
        roleForm.put(`/roles/${editingRole.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showRoleDialog.value = false;
                editingRole.value = null;
            },
        });
    } else {
        roleForm.post('/roles', {
            preserveScroll: true,
            onSuccess: () => {
                showRoleDialog.value = false;
                editingRole.value = null;
            },
        });
    }
};

const deleteRole = (role) => {
    if (role.name === 'Super Admin') {
        alert('System role Super Admin cannot be deleted.');
        return;
    }
    if (confirm(`Delete role "${role.name}"?`)) {
        router.delete(`/roles/${role.id}`, { preserveScroll: true });
    }
};

// --- PERMISSION ACTIONS ---
const openPermissionDialog = () => {
    permissionForm.reset();
    permissionForm.clearErrors();
    permissionForm.id = null;
    editingPermission.value = null;
    showPermissionDialog.value = true;
};

const editPermission = (permission) => {
    permissionForm.reset();
    permissionForm.clearErrors();
    permissionForm.id = permission.id;
    permissionForm.name = permission.value;
    editingPermission.value = permission;
    showPermissionDialog.value = true;
};

const savePermission = () => {
    if (editingPermission.value) {
        permissionForm.put(`/permissions/${editingPermission.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showPermissionDialog.value = false;
                editingPermission.value = null;
            },
        });
    } else {
        permissionForm.post('/permissions', {
            preserveScroll: true,
            onSuccess: () => {
                showPermissionDialog.value = false;
                editingPermission.value = null;
            },
        });
    }
};

const deletePermission = (permission) => {
    if (confirm(`Delete permission "${permission.value}"?`)) {
        router.delete(`/permissions/${permission.id}`, { preserveScroll: true });
    }
};

const getRoleTagSeverity = (role) => {
    switch (role) {
        case 'Super Admin':
            return 'warn';
        case 'AI Operator':
            return 'success';
        default:
            return 'secondary';
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Users & Role Management - Maniratn AI" />

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

            <!-- 🏛️ 1. Hero Header Banner matching ERP -->
            <div class="border-b border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Users & Roles</h1>
                            <Tag value="Master Hub Security" severity="secondary" />
                        </div>

                        <p class="mt-1 text-sm text-surface-500">
                            Create system users with roles and attach direct permissions whenever an operator needs custom access.
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <Button label="New Permission" icon="pi pi-key" outlined class="!w-auto shrink-0 whitespace-nowrap" @click="openPermissionDialog" />
                        <Button label="New Role" icon="pi pi-shield" outlined class="!w-auto shrink-0 whitespace-nowrap" @click="openRoleDialog" />
                        <Button label="New User" icon="pi pi-plus" class="!w-auto shrink-0 whitespace-nowrap" @click="openUserDialog" />
                    </div>
                </div>
            </div>

            <!-- 📊 2. Main 3-Column Grid matching ERP -->
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <!-- Left 2 Columns: User Accounts Table -->
                <div class="card overflow-hidden !p-0 border border-surface-200 xl:col-span-2 bg-white">
                    <div class="border-b border-surface-200 bg-white px-5 py-4">
                        <h3 class="text-base font-semibold text-surface-900">User Accounts</h3>
                        <p class="mt-1 text-sm text-surface-500">Manage who can access the Central AI Hub and what they can do.</p>
                    </div>

                    <div class="bg-white p-4">
                        <DataTable :value="users" stripedRows rowHover tableStyle="min-width: 50rem">
                            <template #empty>
                                <div class="py-12 text-center text-surface-500">No users found</div>
                            </template>

                            <Column field="name" header="Name">
                                <template #body="{ data }">
                                    <div class="font-medium text-surface-900 flex items-center gap-2">
                                        <span>{{ data.name }}</span>
                                        <Tag v-if="data.is_current_user" value="You" severity="contrast" class="text-[10px]" />
                                    </div>
                                </template>
                            </Column>

                            <Column field="email" header="Email" />

                            <Column header="Role" style="width: 160px">
                                <template #body="{ data }">
                                    <div class="flex flex-wrap gap-1">
                                        <Tag v-for="role in data.roles" :key="role" :value="role" :severity="getRoleTagSeverity(role)" />
                                    </div>
                                </template>
                            </Column>

                            <Column header="Direct Permissions">
                                <template #body="{ data }">
                                    <div v-if="data.permissions.length" class="flex flex-wrap gap-1.5">
                                        <Tag v-for="perm in data.permissions" :key="perm" :value="perm" severity="secondary" />
                                    </div>
                                    <span v-else class="text-xs text-surface-400">No direct permissions</span>
                                </template>
                            </Column>

                            <Column field="created_at" header="Created" style="width: 150px" />

                            <Column header="" style="width: 110px">
                                <template #body="{ data }">
                                    <div class="flex justify-end gap-1">
                                        <Button icon="pi pi-pencil" text size="small" @click="editUser(data)" />
                                        <Button
                                            v-if="!data.is_current_user"
                                            icon="pi pi-trash"
                                            text
                                            severity="danger"
                                            size="small"
                                            @click="deleteUser(data)"
                                        />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>

                <!-- Right 1 Column: System Roles -->
                <div class="card overflow-hidden !p-0 border border-surface-200 bg-white">
                    <div class="border-b border-surface-200 bg-white px-5 py-4">
                        <h3 class="text-base font-semibold text-surface-900">System Roles</h3>
                        <p class="mt-1 text-sm text-surface-500">Roles stored in the database with assigned permissions.</p>
                    </div>

                    <div class="space-y-4 bg-white p-4">
                        <div v-for="role in roles" :key="role.id" class="rounded border border-surface-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-surface-900">{{ role.label }}</div>
                                    <div class="mt-1 text-xs text-surface-500">{{ role.users_count }} user{{ role.users_count === 1 ? '' : 's' }} assigned</div>
                                </div>
                                <Tag :value="role.name" :severity="getRoleTagSeverity(role.name)" />
                            </div>

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <Tag v-for="permission in role.permissions" :key="permission" :value="permission" severity="secondary" />
                                <span v-if="!role.permissions.length" class="text-xs text-surface-400">No permissions assigned</span>
                            </div>

                            <div class="mt-4 flex justify-end gap-2 border-t border-surface-100 pt-2">
                                <Button label="Edit" icon="pi pi-pencil" text size="small" @click="editRole(role)" />
                                <Button
                                    v-if="role.name !== 'Super Admin'"
                                    label="Delete"
                                    icon="pi pi-trash"
                                    text
                                    severity="danger"
                                    size="small"
                                    @click="deleteRole(role)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🔑 3. Bottom Full-Width Table: Available Permissions -->
            <div class="card overflow-hidden !p-0 border border-surface-200 bg-white">
                <div class="border-b border-surface-200 bg-white px-5 py-4">
                    <h3 class="text-base font-semibold text-surface-900">Available Permissions</h3>
                    <p class="mt-1 text-sm text-surface-500">
                        Permissions used across Central Hub API tokens, playground, and system administration.
                    </p>
                </div>

                <div class="bg-white p-4">
                    <DataTable :value="permissions" stripedRows rowHover tableStyle="min-width: 42rem">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No permissions found</div>
                        </template>

                        <Column field="label" header="Permission Label" />
                        <Column field="value" header="Key Name" />
                        <Column header="Usage" style="width: 220px">
                            <template #body="{ data }">
                                <span class="text-xs text-surface-600 font-medium">
                                    {{ data.roles_count }} roles • {{ data.users_count }} users
                                </span>
                            </template>
                        </Column>
                        <Column header="" style="width: 140px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-1">
                                    <Button icon="pi pi-pencil" text size="small" @click="editPermission(data)" />
                                    <Button icon="pi pi-trash" text severity="danger" size="small" @click="deletePermission(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- 👑 DIALOG 1: Create / Edit User -->
        <Dialog v-model:visible="showUserDialog" :header="editingUser ? 'Edit User' : 'Create User'" modal class="w-full max-w-2xl">
            <div class="grid gap-4 pt-2 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-surface-700">Full Name <span class="text-red-500">*</span></label>
                    <InputText v-model="userForm.name" class="w-full" placeholder="Enter full name" />
                    <small v-if="userForm.errors.name" class="mt-1 block text-xs text-red-500">{{ userForm.errors.name }}</small>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-surface-700">Email Address <span class="text-red-500">*</span></label>
                    <InputText v-model="userForm.email" type="email" class="w-full" placeholder="Enter email address" />
                    <small v-if="userForm.errors.email" class="mt-1 block text-xs text-red-500">{{ userForm.errors.email }}</small>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-surface-700">
                        {{ editingUser ? 'Password (Leave blank to keep existing)' : 'Password' }}
                        <span v-if="!editingUser" class="text-red-500">*</span>
                    </label>
                    <Password v-model="userForm.password" toggleMask fluid :feedback="false" placeholder="Set a secure password" />
                    <small v-if="userForm.errors.password" class="mt-1 block text-xs text-red-500">{{ userForm.errors.password }}</small>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-surface-700">Access Role <span class="text-red-500">*</span></label>
                    <Select v-model="userForm.role" :options="roleOptions" optionLabel="label" optionValue="value" class="w-full" />
                    <small v-if="userForm.errors.role" class="mt-1 block text-xs text-red-500">{{ userForm.errors.role }}</small>
                    <small v-else-if="selectedRole" class="mt-1 block text-xs text-surface-500">
                        This role already inherits {{ selectedRole.permissions.length }} system permission{{ selectedRole.permissions.length === 1 ? '' : 's' }}.
                    </small>
                </div>

                <!-- Direct Permissions -->
                <div class="md:col-span-2 rounded border border-surface-200 bg-surface-50 p-4">
                    <p class="text-sm font-semibold text-surface-900">Direct Permissions (Optional Override)</p>
                    <p class="mt-0.5 text-xs text-surface-500">Grant specific permissions beyond what the selected role provides.</p>

                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="perm in permissions" :key="perm.id" class="flex items-center gap-2">
                            <Checkbox
                                v-model="userForm.permissions"
                                :value="perm.value"
                                :inputId="'perm_' + perm.id"
                                :disabled="isInheritedPermission(perm.value)"
                            />
                            <label :for="'perm_' + perm.id" class="text-xs text-surface-700 cursor-pointer select-none">
                                {{ perm.label }}
                                <span v-if="isInheritedPermission(perm.value)" class="text-[10px] text-surface-400 font-italic">(from role)</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-surface-200">
                    <Button label="Cancel" text severity="secondary" @click="showUserDialog = false" />
                    <Button
                        :label="editingUser ? 'Update User' : 'Create User'"
                        icon="pi pi-check"
                        :loading="userForm.processing"
                        @click="saveUser"
                    />
                </div>
            </template>
        </Dialog>

        <!-- 🛡️ DIALOG 2: Create / Edit Role -->
        <Dialog v-model:visible="showRoleDialog" :header="editingRole ? 'Edit Role' : 'Create Role'" modal class="w-full max-w-lg">
            <div class="space-y-4 pt-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-surface-700">Role Name <span class="text-red-500">*</span></label>
                    <InputText v-model="roleForm.name" class="w-full" placeholder="e.g. Store Auditor" />
                    <small v-if="roleForm.errors.name" class="mt-1 block text-xs text-red-500">{{ roleForm.errors.name }}</small>
                </div>

                <div class="rounded border border-surface-200 bg-surface-50 p-4">
                    <p class="text-sm font-semibold text-surface-900">Attach Permissions</p>
                    <div class="mt-3 grid grid-cols-1 gap-2.5">
                        <div v-for="perm in permissions" :key="perm.id" class="flex items-center gap-2">
                            <Checkbox
                                v-model="roleForm.permissions"
                                :value="perm.value"
                                :inputId="'role_perm_' + perm.id"
                            />
                            <label :for="'role_perm_' + perm.id" class="text-xs text-surface-700 cursor-pointer select-none">
                                {{ perm.label }} (<code>{{ perm.value }}</code>)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-surface-200">
                    <Button label="Cancel" text severity="secondary" @click="showRoleDialog = false" />
                    <Button
                        :label="editingRole ? 'Update Role' : 'Create Role'"
                        icon="pi pi-check"
                        :loading="roleForm.processing"
                        @click="saveRole"
                    />
                </div>
            </template>
        </Dialog>

        <!-- 🔑 DIALOG 3: Create / Edit Permission -->
        <Dialog v-model:visible="showPermissionDialog" :header="editingPermission ? 'Edit Permission' : 'Create Permission'" modal class="w-full max-w-md">
            <div class="space-y-4 pt-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-surface-700">Permission Key <span class="text-red-500">*</span></label>
                    <InputText v-model="permissionForm.name" class="w-full" placeholder="e.g. export_reports" />
                    <small class="text-xs text-surface-400 block mt-1">Use lowercase with underscores (e.g. view_keys)</small>
                    <small v-if="permissionForm.errors.name" class="mt-1 block text-xs text-red-500">{{ permissionForm.errors.name }}</small>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-surface-200">
                    <Button label="Cancel" text severity="secondary" @click="showPermissionDialog = false" />
                    <Button
                        :label="editingPermission ? 'Update Permission' : 'Create Permission'"
                        icon="pi pi-check"
                        :loading="permissionForm.processing"
                        @click="savePermission"
                    />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
