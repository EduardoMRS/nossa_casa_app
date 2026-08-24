<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full min-h-full p-6 gap-4 justify-center">
        <native:text class="text-3xl font-extrabold text-theme-on-background text-center">
            {{ __('native.login.title') }}
        </native:text>
        <native:outlined-text-input
            label="{{ __('native.login.email') }}"
            keyboard="email"
            native:model.blur="email"
        />
        <native:outlined-text-input
            label="{{ __('native.login.password') }}"
            keyboard="password"
            secure
            native:model.blur="password"
        />
        <native:outlined-text-input
            label="{{ __('native.login.two_factor') }}"
            keyboard="number"
            native:model.blur="twoFactorCode"
        />
        @if ($error !== '')
            <native:text class="text-sm text-theme-error">{{ $error }}</native:text>
        @endif
        <native:button
            label="{{ __('native.login.submit') }}"
            @press="login"
            :loading="$submitting"
            :disabled="$submitting"
        />
        <native:button
            label="{{ __('native.login.continue_publicly') }}"
            variant="outlined"
            @press="continuePublicly"
        />
        <native:button
            label="{{ __('native.server.change') }}"
            variant="text"
            @press="changeServer"
        />
    </native:column>
</native:scroll-view>
