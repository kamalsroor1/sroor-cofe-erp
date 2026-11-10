import { ref, computed } from 'vue';
import api from '../Services/api';

/**
 * PRD-08: fetch a public legal document (`terms`, `privacy`, `dpa`) from
 * GET /api/v1/public/legal/{document} (no auth, no tenant).
 */
export function useLegalDocument() {
    const payload = ref(null);
    const isLoading = ref(false);
    const hasError = ref(false);

    const markdown = computed(() => payload.value?.markdown || '');
    const version = computed(() => payload.value?.version || '');
    const isDraft = computed(() => payload.value?.is_draft !== false);
    const brand = computed(() => payload.value?.brand || {});

    const blocks = computed(() => parseMarkdownBlocks(markdown.value));

    const load = async (documentKey) => {
        if (!documentKey) return;
        isLoading.value = true;
        hasError.value = false;
        try {
            const res = await api.get(`/public/legal/${encodeURIComponent(documentKey)}`);
            payload.value = res.data?.data || null;
            hasError.value = !payload.value;
        } catch {
            hasError.value = true;
        } finally {
            isLoading.value = false;
        }
    };

    return {
        payload,
        markdown,
        version,
        isDraft,
        brand,
        blocks,
        isLoading,
        hasError,
        load,
    };
}

function cleanInlineMarkdown(text) {
    return String(text || '')
        .replace(/\*\*([^*]+)\*\*/g, '$1')
        .replace(/`([^`]+)`/g, '$1')
        .trim();
}

function parseMarkdownBlocks(raw) {
    const lines = String(raw || '').split(/\r?\n/);
    const result = [];
    let listItems = [];

    const flushList = () => {
        if (listItems.length > 0) {
            result.push({ type: 'list', items: [...listItems] });
            listItems = [];
        }
    };

    lines.forEach((rawLine) => {
        const line = rawLine.trim();
        if (!line || line === '---') {
            flushList();
            return;
        }

        if (line.startsWith('>')) {
            flushList();
            result.push({ type: 'quote', text: cleanInlineMarkdown(line.replace(/^>\s*/, '')) });
            return;
        }

        if (line.startsWith('# ')) {
            flushList();
            result.push({ type: 'h1', text: cleanInlineMarkdown(line.slice(2)) });
            return;
        }

        if (line.startsWith('## ')) {
            flushList();
            result.push({ type: 'h2', text: cleanInlineMarkdown(line.slice(3)) });
            return;
        }

        if (line.startsWith('### ')) {
            flushList();
            result.push({ type: 'h3', text: cleanInlineMarkdown(line.slice(4)) });
            return;
        }

        if (/^[-*]\s+/.test(line) || /^\d+\.\s+/.test(line)) {
            listItems.push(cleanInlineMarkdown(line.replace(/^([-*]|\d+\.)\s+/, '')));
            return;
        }

        flushList();
        result.push({ type: 'p', text: cleanInlineMarkdown(line) });
    });

    flushList();
    return result;
}
