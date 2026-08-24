import { reactive } from 'vue';
import { useI18n } from '@/lib/i18n';

export type ConfirmationOptions = {
    message: string;
    title?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    intent?: 'default' | 'danger';
};

export type PromptOptions = ConfirmationOptions & {
    inputLabel: string;
    inputPlaceholder?: string;
    inputRequired?: boolean;
};

type ConfirmationResult = boolean | string | null;
type ConfirmationResolver = (result: ConfirmationResult) => void;

export const confirmationDialogState = reactive({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    cancelLabel: '',
    intent: 'default' as 'default' | 'danger',
    inputLabel: '',
    inputPlaceholder: '',
    inputRequired: false,
    inputValue: '',
});

let resolvePending: ConfirmationResolver | null = null;

const dismissPending = (): void => {
    if (!resolvePending) {
        return;
    }

    const resolve = resolvePending;
    resolvePending = null;
    confirmationDialogState.open = false;
    resolve(confirmationDialogState.inputLabel ? null : false);
};

const requestConfirmation = (
    options: Required<ConfirmationOptions> & Partial<PromptOptions>,
): Promise<ConfirmationResult> => {
    dismissPending();
    Object.assign(confirmationDialogState, {
        ...options,
        inputLabel: options.inputLabel ?? '',
        inputPlaceholder: options.inputPlaceholder ?? '',
        inputRequired: options.inputRequired ?? false,
        inputValue: '',
        open: true,
    });

    return new Promise((resolve) => {
        resolvePending = resolve;
    });
};

export const resolveConfirmationDialog = (confirmed: boolean): void => {
    if (!resolvePending) {
        confirmationDialogState.open = false;

        return;
    }

    const resolve = resolvePending;
    resolvePending = null;
    const result = confirmed
        ? confirmationDialogState.inputLabel
            ? confirmationDialogState.inputValue.trim()
            : true
        : confirmationDialogState.inputLabel
          ? null
          : false;

    confirmationDialogState.open = false;
    resolve(result);
};

export const useConfirmDialog = () => {
    const { t } = useI18n();

    const confirm = async (options: ConfirmationOptions): Promise<boolean> =>
        (await requestConfirmation({
            title: options.title ?? t('dialogs.confirm.title'),
            message: options.message,
            confirmLabel: options.confirmLabel ?? t('dialogs.confirm.action'),
            cancelLabel: options.cancelLabel ?? t('actions.cancel'),
            intent: options.intent ?? 'default',
        })) === true;

    const prompt = async (options: PromptOptions): Promise<string | null> => {
        const result = await requestConfirmation({
            title: options.title ?? t('dialogs.prompt.title'),
            message: options.message,
            confirmLabel: options.confirmLabel ?? t('dialogs.confirm.action'),
            cancelLabel: options.cancelLabel ?? t('actions.cancel'),
            intent: options.intent ?? 'default',
            inputLabel: options.inputLabel,
            inputPlaceholder: options.inputPlaceholder,
            inputRequired: options.inputRequired ?? true,
        });

        return typeof result === 'string' ? result : null;
    };

    return { confirm, prompt };
};
