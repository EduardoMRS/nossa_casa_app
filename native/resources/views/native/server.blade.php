<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full min-h-full p-6 gap-5 justify-center">
        <native:text class="text-3xl font-extrabold text-theme-on-background text-center">
            {{ __('native.server.title') }}
        </native:text>
        <native:text class="text-base text-theme-on-surface-variant text-center">
            {{ __('native.server.description') }}
        </native:text>
        <native:outlined-text-input
            label="{{ __('native.server.domain') }}"
            placeholder="{{ __('native.server.placeholder') }}"
            keyboard="url"
            native:model.blur="server"
            :is-error="$error !== ''"
            :supporting="$error"
        />
        <native:button
            label="{{ __('native.server.connect') }}"
            @press="connect"
            :loading="$connecting"
            :disabled="$connecting"
        />
        <native:button
            label="{{ __('native.server.scan') }}"
            variant="outlined"
            leading-icon="qr_code_scanner"
            @press="scan"
            :disabled="$connecting"
        />
    </native:column>
</native:scroll-view>
