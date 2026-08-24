export type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'full';

const modalSizeClasses: Record<ModalSize, string> = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-lg',
    lg: 'sm:max-w-2xl',
    xl: 'sm:max-w-4xl',
    '2xl': 'sm:max-w-6xl',
    full: 'sm:max-w-[calc(100%-2rem)]',
};

export const modalSizeClass = (size: ModalSize): string =>
    modalSizeClasses[size];

export const confirmationCanSubmit = (
    processing: boolean,
    inputRequired: boolean,
    inputValue: string,
): boolean => !processing && (!inputRequired || inputValue.trim().length > 0);
