# Custom frontend components

## MarkdownWysiwyg

`resources/js/components/MarkdownWysiwyg.vue` is the project Markdown editor.
It loads Toast UI Editor dynamically so normal pages do not download the
editor bundle. The custom toolbar button opens a themed content picker.

```vue
<MarkdownWysiwyg v-model="form.content" height="420px" />
```

The picker can insert forms, categorized media, public posts, events, and
library items. It stores a stable shortcode such as
`[[event:01...]]`; rendering is performed server-side by
`App\Support\ContentEmbedRenderer` and remains scoped to the active church.
Use the existing `editor.embeds` translation namespace for new labels.

## PhoneInput

`PhoneInput.vue` combines a country selector and a numeric national-number
field. It emits the normalized value including the selected dial code.

```vue
<PhoneInput v-model="form.phone" name="phone" required />
```

Use `defaultCountry` for a form-specific default and do not duplicate phone
masks in individual pages. Add a country mask to the component when a new
supported country is required.

## MoneyInput

`MoneyInput.vue` provides currency selection, symbol display, decimal input,
and a canonical two-decimal value:

```vue
<MoneyInput
  v-model="form.price"
  v-model:currency="form.currency"
  name="price"
  currency-name="currency"
  :lock-currency="true"
  required
/>
```

Use `lockCurrency` when the value must follow the church currency setting.
Persist the emitted canonical value; presentation formatting belongs to the
component.

## Translation and shared helpers

Component labels must use `useI18n().t()` and keys from
`resources/js/locales/en.json`. Do not place Portuguese or English UI text
directly in a component. For backend rendering, use the translation helpers
and the shared `ContentEmbedRenderer` instead of assembling HTML in a page.
