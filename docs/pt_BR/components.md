# Componentes frontend personalizados

## MarkdownWysiwyg

`resources/js/components/MarkdownWysiwyg.vue` é o editor Markdown do projeto. Ele carrega o Toast UI Editor dinamicamente para que páginas comuns não baixem o bundle do editor. O botão personalizado da toolbar abre um seletor de conteúdo que respeita o tema.

```vue
<MarkdownWysiwyg v-model="form.content" height="420px" />
```

O seletor insere formulários, mídias com categorias, posts públicos, eventos e itens da biblioteca. Ele grava um shortcode estável, como `[[event:01...]]`; a renderização é feita por `App\Support\ContentEmbedRenderer` e permanece limitada à church ativa. Use o namespace `editor.embeds` para novas traduções.

## PhoneInput

`PhoneInput.vue` combina seletor de país e campo numérico. O valor emitido é normalizado com o código telefônico selecionado.

```vue
<PhoneInput v-model="form.phone" name="phone" required />
```

Use `defaultCountry` quando o formulário precisar de outro país padrão. Não duplique máscaras de telefone nas páginas.

## MoneyInput

`MoneyInput.vue` fornece seleção de moeda, símbolo, entrada decimal e valor canônico com duas casas:

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

Use `lockCurrency` quando o valor deve seguir a moeda configurada na church. Persista o valor canônico emitido; a formatação visual pertence ao componente.

## Tradução e helpers compartilhados

Rótulos devem usar `useI18n().t()` e chaves de `resources/js/locales/en.json`. Não coloque texto de interface diretamente no componente. No backend, use os helpers de tradução e `ContentEmbedRenderer` em vez de montar HTML na página.
