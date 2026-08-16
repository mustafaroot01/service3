<script setup lang="ts">
import { themeConfig } from '@themeConfig'
</script>

<template>
  <div class="h-100 d-flex align-center justify-center text-medium-emphasis">
    <span>&copy; {{ new Date().getFullYear() }} {{ themeConfig.app.title }}</span>
  </div>
</template>
