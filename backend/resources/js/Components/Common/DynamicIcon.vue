<template>
  <component v-if="resolvedIcon" :is="resolvedIcon" :class="iconClass" :style="customStyle" />
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
  Folder,
  FolderKanban,
  ShoppingBag,
  ShoppingCart,
  Users,
  User,
  Shield,
  ShieldCheck,
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
  Warehouse,
  Gift,
  Bookmark,
  Archive,
  Utensils,
  CupSoda,
  Phone,
  PackageOpen,
} from 'lucide-vue-next';

const props = defineProps({
  name: {
    type: [String, Object, Function],
    default: null,
  },
  class: {
    type: String,
    default: 'w-4 h-4',
  },
  customStyle: {
    type: Object,
    default: () => ({}),
  },
  fallback: {
    type: [Object, Function, String],
    default: () => Folder,
  },
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
  '🏬': Store,
  '💵': Banknote,
  '💳': CreditCard,
  '📱': Smartphone,
  '🏦': Building2,
  '🏷️': Tag,
  '🛍️': ShoppingBag,
  '🛒': ShoppingCart,
  '👥': Users,
  '👤': User,
  '📊': BarChart3,
  '📈': BarChart3,
  '📉': BarChart3,
  '⚙️': Sliders,
  '🚚': Truck,
  '🏭': Warehouse,
  '↩️': RotateCcw,
  '📄': FileText,
  '🧾': Receipt,
  '💸': Receipt,
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
  '🗂️': Folder,
  '📞': Phone,
};

const stringNameMap = {
  coffee: Coffee,
  leaf: Leaf,
  sprout: Leaf,
  sparkles: Sparkles,
  zap: Zap,
  crown: Crown,
  flame: Flame,
  package: Package,
  'package-open': PackageOpen,
  packageopen: PackageOpen,
  boxes: Boxes,
  layers: Layers,
  folder: Folder,
  'folder-kanban': FolderKanban,
  folderkanban: FolderKanban,
  star: Star,
  store: Store,
  banknote: Banknote,
  'credit-card': CreditCard,
  creditcard: CreditCard,
  smartphone: Smartphone,
  building2: Building2,
  tag: Tag,
  tags: Tag,
  'shopping-bag': ShoppingBag,
  shoppingbag: ShoppingBag,
  'shopping-cart': ShoppingCart,
  shoppingcart: ShoppingCart,
  users: Users,
  user: User,
  'bar-chart3': BarChart3,
  barchart3: BarChart3,
  sliders: Sliders,
  truck: Truck,
  warehouse: Warehouse,
  'rotate-ccw': RotateCcw,
  'file-text': FileText,
  filetext: FileText,
  receipt: Receipt,
  trash2: Trash2,
  shield: Shield,
  'shield-check': ShieldCheck,
  shieldcheck: ShieldCheck,
  lock: Lock,
  scale: Scale,
  printer: Printer,
  search: Search,
  monitor: Monitor,
  server: Server,
  calendar: Calendar,
  droplet: Droplet,
  gift: Gift,
  bookmark: Bookmark,
  archive: Archive,
  utensils: Utensils,
  'cup-soda': CupSoda,
  cupsoda: CupSoda,
  phone: Phone,
};

const resolveIcon = (val) => {
  if (!val) return null;
  if (typeof val === 'object' || typeof val === 'function') {
    return val;
  }
  if (typeof val === 'string') {
    const trimmed = val.trim();
    if (emojiMap[trimmed]) return emojiMap[trimmed];
    const lower = trimmed.toLowerCase();
    if (stringNameMap[lower]) return stringNameMap[lower];
    const kebab = lower.replace(/\s+/g, '-');
    if (stringNameMap[kebab]) return stringNameMap[kebab];
    const plain = lower.replace(/[-_\s]/g, '');
    if (stringNameMap[plain]) return stringNameMap[plain];
  }
  return null;
};

const resolvedIcon = computed(() => {
  return resolveIcon(props.name) || resolveIcon(props.fallback) || Folder;
});

const iconClass = computed(() => props.class);
const fallbackText = computed(() => (typeof props.name === 'string' && !resolvedIcon.value ? props.name : ''));
</script>
