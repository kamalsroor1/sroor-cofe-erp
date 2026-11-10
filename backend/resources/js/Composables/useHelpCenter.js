import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAppConfigStore } from '../stores/appConfig';
import { useTrans } from './useTrans';

const rawMarkdownFiles = import.meta.glob('../../help/*/*.md', {
    query: '?raw',
    import: 'default',
    eager: true,
});

/**
 * Parse a Markdown help file into structured sections (title, intro, and h2 blocks with paragraphs/items)
 * so the viewer renders clean semantic elements without raw HTML injection.
 */
function parseMarkdownArticle(rawContent) {
    const lines = String(rawContent ?? '')
        .replace(/\r\n/g, '\n')
        .split('\n');
    let title = '';
    const intro = [];
    const sections = [];
    let currentSection = null;

    for (const rawLine of lines) {
        const line = rawLine.trim();
        if (!line) continue;

        if (line.startsWith('# ') && !title) {
            title = line.slice(2).trim();
            continue;
        }

        if (line.startsWith('## ')) {
            currentSection = {
                heading: line.slice(3).trim(),
                paragraphs: [],
                bullets: [],
            };
            sections.push(currentSection);
            continue;
        }

        if (line.startsWith('- ') || line.startsWith('* ')) {
            const itemText = line.slice(2).trim();
            if (!currentSection) {
                currentSection = { heading: '', paragraphs: [], bullets: [] };
                sections.push(currentSection);
            }
            currentSection.bullets.push(parseInlineTokens(itemText));
            continue;
        }

        if (currentSection) {
            currentSection.paragraphs.push(parseInlineTokens(line));
        } else {
            intro.push(parseInlineTokens(line));
        }
    }

    return { title, intro, sections };
}

/**
 * Splits a single line of text into plain, bold (**...**), and code (`...`) tokens for safe Vue rendering.
 */
function parseInlineTokens(text) {
    const tokens = [];
    const pattern = /(\*\*[^*]+\*\*|`[^`]+`)/g;
    let lastIndex = 0;
    let match;

    while ((match = pattern.exec(text)) !== null) {
        if (match.index > lastIndex) {
            tokens.push({ type: 'text', value: text.slice(lastIndex, match.index) });
        }
        const token = match[0];
        if (token.startsWith('**') && token.endsWith('**')) {
            tokens.push({ type: 'bold', value: token.slice(2, -2) });
        } else if (token.startsWith('`') && token.endsWith('`')) {
            tokens.push({ type: 'code', value: token.slice(1, -1) });
        }
        lastIndex = pattern.lastIndex;
    }

    if (lastIndex < text.length) {
        tokens.push({ type: 'text', value: text.slice(lastIndex) });
    }

    return tokens;
}

export function useHelpCenter() {
    const route = useRoute();
    const router = useRouter();
    const appConfigStore = useAppConfigStore();
    const { t } = useTrans();

    const isLoading = ref(false);
    const searchQuery = ref('');
    const activeSlug = ref('');

    const localeArticles = computed(() => {
        const locale = appConfigStore.locale === 'en' ? 'en' : 'ar';
        const entries = [];

        for (const [path, content] of Object.entries(rawMarkdownFiles)) {
            const match = path.match(/\/help\/(ar|en)\/(\d+)-([a-z0-9-]+)\.md$/i);
            if (!match) continue;

            const [, fileLocale, orderStr, slug] = match;
            if (fileLocale !== locale) continue;

            const parsed = parseMarkdownArticle(content);
            entries.push({
                slug,
                order: Number(orderStr),
                title: parsed.title || t(`help.categories.${slug}`),
                categoryLabel: t(`help.categories.${slug}`),
                intro: parsed.intro,
                sections: parsed.sections,
            });
        }

        return entries.sort((a, b) => a.order - b.order);
    });

    const filteredArticles = computed(() => {
        const q = searchQuery.value.trim().toLowerCase();
        if (!q) return localeArticles.value;

        return localeArticles.value.filter(
            (article) => article.title.toLowerCase().includes(q) || article.categoryLabel.toLowerCase().includes(q)
        );
    });

    const activeArticle = computed(() => {
        const list = filteredArticles.value;
        if (list.length === 0) return null;
        return list.find((a) => a.slug === activeSlug.value) || list[0];
    });

    const selectArticle = (slug) => {
        activeSlug.value = slug;
        if (route && router && route.query?.article !== slug) {
            router.replace({ query: { ...route.query, article: slug } }).catch(() => {});
        }
    };

    watch(
        () => route?.query?.article,
        (qSlug) => {
            if (typeof qSlug === 'string' && qSlug.trim() !== '') {
                activeSlug.value = qSlug.trim();
            } else if (!activeSlug.value && localeArticles.value.length > 0) {
                activeSlug.value = localeArticles.value[0].slug;
            }
        },
        { immediate: true }
    );

    return {
        isLoading,
        searchQuery,
        articles: localeArticles,
        filteredArticles,
        activeSlug,
        activeArticle,
        selectArticle,
    };
}
