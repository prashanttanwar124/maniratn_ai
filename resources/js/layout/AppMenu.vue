<script setup>
import { ref } from 'vue';
import AppMenuItem from './AppMenuItem.vue';

const model = ref([
    {
        label: 'Central Intelligence Hub',
        items: [
            { label: 'API Keys & Tenants', icon: 'pi pi-fw pi-key', to: '/' },
        ],
    },
    {
        label: 'Connected Showroom Portals',
        items: [
            { label: 'Open Jewellery ERP', icon: 'pi pi-fw pi-external-link', url: 'http://127.0.0.1:8000', target: '_blank' },
            { label: 'Store AI Settings', icon: 'pi pi-fw pi-sliders-h', url: 'http://127.0.0.1:8000/settings/business-profile', target: '_blank' },
        ],
    },
]);
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item.label || i">
            <app-menu-item v-if="!item.separator" :item="item" :index="i" />
            <li v-if="item.separator" class="menu-separator" />
        </template>
    </ul>
</template>
