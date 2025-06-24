
<script setup>
import { Head, Link, router } from '@inertiajs/vue3'

const goBack = () => {
  router.visit('/dashboard') 
}

</script>

<template>
    <Head title="403" />

    <div class="flex flex-col items-center justify-center min-h-screen bg-white-100 dark:bg-gray-900">
      
      <!--<p class="mt-4 text-xl text-gray-700 dark:text-gray-300">No tienes permiso para ver esta página.</p>-->

      <img src="/img/403.gif" alt="403 Error" class="w-73 h-73"/>

      <button
        @click="goBack"
        class="mt-6 px-4 py-2 bg-blue-500 hover:bg-blue-700 text-white rounded"
      >
        Volver al inicio
      </button>

    </div>
</template>

  