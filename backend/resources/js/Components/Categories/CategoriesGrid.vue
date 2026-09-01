<template>
  <div class="font-tajawal">
    <!-- 🔄 Skeleton Loading State (Facebook-Style Shimmer Grid) -->
    <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <CardSkeleton v-for="n in 8" :key="n" />
    </div>

    <!-- 🚫 Empty State -->
    <EmptyState
      v-else-if="categories.length === 0"
      :title="$t('inventory.no_categories_yet')"
      :description="$t('inventory.create_first_category_hint')"
      :icon="'🗂️'"
    >
      <template #action>
        <BaseButton
          type="button"
          variant="gradient"
          size="md"
          :icon="Plus"
          :label="$t('inventory.add_first_category')"
          @click="$emit('create')"
        />
      </template>
    </EmptyState>

    <!-- 🗂️ Categories Responsive Grid -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <CategoryCard
        v-for="cat in categories"
        :key="cat.id"
        :category="cat"
        @edit="$emit('edit', cat)"
        @delete="$emit('delete', cat)"
      />
    </div>
  </div>
</template>

<script setup>
import { Plus } from 'lucide-vue-next';
import EmptyState from '../Common/EmptyState.vue';
import BaseButton from '../Common/BaseButton.vue';
import CardSkeleton from '../Common/Skeletons/CardSkeleton.vue';
import CategoryCard from './CategoryCard.vue';

defineProps({
  categories: {
    type: Array,
    default: () => [],
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['create', 'edit', 'delete']);
</script>
