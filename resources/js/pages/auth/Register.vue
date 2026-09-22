<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/lib/i18n';
import { login } from '@/routes';
import { store } from '@/routes/register';

const props = defineProps<{
    passwordRules: string;
    redirect?: string | null;
}>();

defineOptions({
    layout: {
        title: 'auth.register.title',
        description: 'auth.register.description',
    },
});

const { t } = useI18n();
const phone = ref('');
const browserLocale = ref('en-US');
const defaultPhoneCountry = ref('US');

onMounted(() => {
    const locale = navigator.languages?.[0] ?? navigator.language ?? 'en-US';
    const normalizedLocale = locale.toLowerCase();

    browserLocale.value = normalizedLocale.startsWith('pt') ? 'pt-BR' : 'en-US';
    defaultPhoneCountry.value = normalizedLocale.startsWith('pt') ? 'BR' : 'US';
});
</script>

<template>
    <Head :title="t('auth.register.meta_title')" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <input
            v-if="props.redirect"
            type="hidden"
            name="redirect"
            :value="props.redirect"
        />
        <input type="hidden" name="location_lang" :value="browserLocale" />

        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">{{ t('auth.common.name') }}</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    :placeholder="t('auth.common.full_name')"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ t('auth.common.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="birth_date">
                        {{ t('auth.common.birth_date') }}
                        <span class="font-normal text-muted-foreground">
                            ({{ t('auth.common.optional') }})
                        </span>
                    </Label>
                    <Input
                        id="birth_date"
                        type="date"
                        :tabindex="3"
                        autocomplete="bday"
                        name="birth_date"
                    />
                    <InputError :message="errors.birth_date" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="phone">
                        {{ t('auth.common.phone') }}
                        <span class="font-normal text-muted-foreground">
                            ({{ t('auth.common.optional') }})
                        </span>
                    </Label>
                    <PhoneInput
                        id="phone"
                        v-model="phone"
                        name="phone"
                        :default-country="defaultPhoneCountry"
                    />
                    <InputError :message="errors.phone" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="gender">
                    {{ t('auth.common.gender') }}
                    <span class="font-normal text-muted-foreground">
                        ({{ t('auth.common.optional') }})
                    </span>
                </Label>
                <select
                    id="gender"
                    name="gender"
                    :tabindex="4"
                    autocomplete="sex"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">
                        {{ t('auth.common.not_informed') }}
                    </option>
                    <option value="female">
                        {{ t('auth.common.female') }}
                    </option>
                    <option value="male">{{ t('auth.common.male') }}</option>
                    <option value="other">{{ t('auth.common.other') }}</option>
                </select>
                <InputError :message="errors.gender" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ t('auth.common.password') }}</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="5"
                    autocomplete="new-password"
                    name="password"
                    :placeholder="t('auth.common.password')"
                    :passwordrules="passwordRules"
                />
                <p class="text-xs text-muted-foreground">
                    {{ t('auth.register.password_hint') }}
                </p>
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">
                    {{ t('auth.common.confirm_password') }}
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="6"
                    autocomplete="new-password"
                    name="password_confirmation"
                    :placeholder="t('auth.common.confirm_password')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-start gap-3">
                    <input
                        id="terms_accepted"
                        type="checkbox"
                        name="terms_accepted"
                        value="1"
                        required
                        :tabindex="7"
                        class="mt-0.5 size-4 rounded border-input accent-primary"
                    />
                    <Label for="terms_accepted" class="block text-sm leading-5">
                        {{ t('auth.register.accept_legal_prefix') }}
                        <TextLink
                            href="/privacy-and-terms"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="underline underline-offset-4"
                        >
                            {{ t('auth.register.legal_document') }}
                        </TextLink>
                    </Label>
                </div>
                <InputError :message="errors.terms_accepted" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :tabindex="8"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ t('auth.register.submit') }}
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            {{ t('auth.register.has_account') }}
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="9"
            >
                {{ t('auth.login.submit') }}
            </TextLink>
        </div>
    </Form>
</template>
