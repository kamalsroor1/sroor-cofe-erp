export function usePOSCategoryColors() {
    const defaultCategoryColors = [
        { id: 'favorites', bg: '#F59E0B', bgLight: '#fef3c7', text: '#92400E', border: '#F59E0B' },
        { id: 'hot', bg: '#92400E', bgLight: '#fef3c7', text: '#FFFFFF', border: '#92400E' },
        { id: 'cold', bg: '#0EA5E9', bgLight: '#e0f2fe', text: '#FFFFFF', border: '#0EA5E9' },
        { id: 'sweet', bg: '#EC4899', bgLight: '#fce7f3', text: '#FFFFFF', border: '#EC4899' },
        { id: 'food', bg: '#10B981', bgLight: '#d1fae5', text: '#FFFFFF', border: '#10B981' },
        { id: 'beans', bg: '#78350F', bgLight: '#fef3c7', text: '#FFFFFF', border: '#78350F' },
        { id: 'pack', bg: '#8B5CF6', bgLight: '#ede9fe', text: '#FFFFFF', border: '#8B5CF6' },
        { id: 'accs', bg: '#6366F1', bgLight: '#e0e7ff', text: '#FFFFFF', border: '#6366F1' },
        { id: 'other', bg: '#64748B', bgLight: '#f1f5f9', text: '#FFFFFF', border: '#64748B' },
    ];

    const getCategoryBgStyle = (category) => {
        const color = category?.color || '#64748B';
        const colorLight = category?.color_light || '#f1f5f9';
        return {
            '--cat-color': color,
            '--cat-color-light': colorLight,
        };
    };

    const getProductButtonStyle = (category) => {
        const color = category?.color || '#64748B';
        return {
            borderColor: `${color}40`,
            backgroundColor: `${color}15`,
        };
    };

    const getProductButtonHoverStyle = (category) => {
        const color = category?.color || '#64748B';
        return {
            borderColor: `${color}80`,
            backgroundColor: `${color}25`,
        };
    };

    return {
        defaultCategoryColors,
        getCategoryBgStyle,
        getProductButtonStyle,
        getProductButtonHoverStyle,
    };
}
