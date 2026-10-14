// [Font Size Switcher 2026] Fluid root-scaling tanpa merusak layout
// Pilihan: 'sm' (15px), 'md' (16px - Default), 'lg' (17.25px), 'xl' (18.5px)
document.addEventListener('alpine:init', () => {
    Alpine.data('fontSizeManager', () => ({
        size: 'md',
        open: false,
        sizes: [
            { id: 'sm', label: 'Ringkas', scale: '94%', px: '15px' },
            { id: 'md', label: 'Standar', scale: '100%', px: '16px' },
            { id: 'lg', label: 'Nyaman', scale: '108%', px: '17.2px' },
            { id: 'xl', label: 'Ekstra', scale: '116%', px: '18.5px' },
        ],

        init() {
            let stored = null;
            try { stored = localStorage.getItem('ute-font-size'); } catch (e) {}
            this.size = stored || 'md';
            this.applySize(this.size);
        },

        setSize(newSize) {
            this.size = newSize;
            this.applySize(newSize);
            try { localStorage.setItem('ute-font-size', newSize); } catch (e) {}
            this.open = false;
        },

        applySize(size) {
            document.documentElement.dataset.fontSize = size;
        }
    }));
});
