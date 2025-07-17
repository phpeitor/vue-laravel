<script setup>
import { router } from "@inertiajs/vue3";

defineProps({
    data: {
        type: Object,
    },
    pageNumberUpdated: {
        type: Function,
        required: true,
    },
});
/*
const updatePageNumber = (link) => {
    let pageNumber = link.url.split("=")[1];

    router.visit(`/students?&page=${pageNumber}`, {
        preserveScroll: true,
    });
};*/
</script>

<template>
  <div class="max-w-7xl mx-auto py-6">
    <div class="max-w-none mx-auto">
      <div class="bg-card dark:bg-card overflow-hidden shadow sm:rounded-lg transition-colors">
        <div
          class="bg-card dark:bg-card px-4 py-3 flex items-center justify-between border-t border-border sm:px-6"
        >
          <div class="flex-1 flex justify-between sm:hidden" />
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-muted-foreground">
                Showing
                <span class="font-medium">{{ data.meta.from }}</span>
                to
                <span class="font-medium">{{ data.meta.to }}</span>
                of
                <span class="font-medium">{{ data.meta.total }}</span>
                results
              </p>
            </div>
            <div>
              <nav
                class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px"
                aria-label="Pagination"
              >
                <button
                  v-for="(link, index) in data.meta.links"
                  :key="index"
                  @click.prevent="pageNumberUpdated(link)"
                  :disabled="link.active || !link.url"
                  class="relative inline-flex items-center px-4 py-2 border text-sm font-medium transition-colors"
                  :class="{
                    'z-10 bg-muted dark:bg-muted border-primary text-primary': link.active,
                    'bg-card dark:bg-card border-border text-muted-foreground hover:bg-muted dark:hover:bg-muted':
                      !link.active,
                  }"
                >
                  <span v-html="link.label"></span>
                </button>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>