<template>
  <component
    v-if="resolvedIcon"
    :is="resolvedIcon"
    :class="iconClass"
    :style="customStyle"
  />
  <span v-else :class="iconClass">{{ fallbackText }}</span>
</template>

<script setup>
import { computed } from 'vue';
import {
  Coffee,
  Sparkles,
  Zap,
  Crown,
  Leaf,
  Package,
  Star,
  Store,
  Banknote,
  CreditCard,
  Smartphone,
  Building2,
  Tag,
  Boxes,
  Layers,
  Flame,
  Droplet,
  GlassWater,
  Folder,
  CircleDot,
  ShoppingBag,
  ShoppingCart,
  Users,
  ShieldCheck,
  Activity,
  Trash2,
  Sliders,
  Truck,
  RotateCcw,
  Receipt,
  FileText,
  BarChart3,
  Scale,
  Printer,
  Search,
  Monitor,
  Server,
  ArrowDownLeft,
  ArrowUpRight,
  ArrowLeftRight,
  Lock,
  Calendar,
  AlertTriangle,
  HelpCircle
} from 'lucide-vue-next';

const props = defineProps({
  name: {
    type: [String, Object, Function],
    default: null
  },
  class: {
    type: String,
    default: 'w-4 h-4'
  },
  customStyle: {
    type: Object,
    default: () => ({})
  },
  fallback: {
    type: [Object, Function],
    default: () => Folder
  }
});

const emojiMap = {
  '☕': Coffee,
  '🌱': Leaf,
  '✨': Sparkles,
  '⚡': Zap,
  '👑': Crown,
  '🌰': Flame,
  '🌿': Leaf,
  '📦': Package,
  '🫖': Coffee,
  '🍯': Droplet,
  '⭐': Star,
  '🏪': Store,
  '💵': Banknote,
  '💳': CreditCard,
  '📱': Smartphone,
  '🏦': Building2,
  '🏷️': Tag,
  '🛍️': ShoppingBag,
  '🛒': ShoppingCart,
  '👥': Users,
  '📊': BarChart3,
  '📈': BarChart3,
  '📉': BarChart3,
  '⚙️': Sliders,
  '🚚': Truck,
  '↩️': RotateCcw,
  '📄': FileText,
  '🧾': Receipt,
  '🗑️': Trash2,
  '🛡️': ShieldCheck,
  '🔒': Lock,
  '⚖️': Scale,
  '🖨️': Printer,
  '🔍': Search,
  '💻': Monitor,
  '🖥️': Server,
  '📅': Calendar,
  '🔥': Flame,
  '📥': ArrowDownLeft,
  '📤': ArrowUpRight,
  '🔄': ArrowLeftRight,
  '⚠️': AlertTriangle,
};

const stringNameMap = {
  'coffee': Coffee,
  'leaf': Leaf,
  'sprout': Leaf,
  'sparkles': Sparkles,
  'zap': Zap,
  'crown': Crown,
  'flame': Flame,
  'package': Package,
  'boxes': Boxes,
  'layers': Layers,
  'star': Star,
  'store': Store,
  'banknote': Banknote,
  'credit-card': CreditCard,
  'smartphone': Smartphone,
  'building2': Building2,
  'tag': Tag,
  'tags': Tag,
  'shopping-bag': ShoppingBag,
  'shopping-cart': ShoppingCart,
  'users': Users,
  'bar-chart3': BarChart3,
  'sliders': Sliders,
  'truck': Truck,
  'rotate-ccw': RotateCcw,
  'file-text': FileText,
  'receipt': Receipt,
  'trash2': Trash2,
  'shield-check': ShieldCheck,
  'lock': Lock,
  'scale': Scale,
  'printer': Printer,
  'search': Search,
  'monitor': Monitor,
  'server': Server,
  'calendar': Calendar,
};

const resolvedIcon = computed(() => {
  if (!props.name) return props.fallback;
  if (typeof props.name === 'object' || typeof props.name === 'function') {
    return props.name;
  }
  if (typeof props.name === 'string') {
    const trimmed = props.name.trim();
    if (emojiMap[trimmed]) return emojiMap[trimmed];
    const lower = trimmed.toLowerCase();
    if (stringNameMap[lower]) return stringNameMap[lower];
  }
  return props.fallback;
});

const iconClass = computed(() => props.class);
const fallbackText = computed(() => (typeof props.name === 'string' && !resolvedIcon.value ? props.name : ''));
</script>
