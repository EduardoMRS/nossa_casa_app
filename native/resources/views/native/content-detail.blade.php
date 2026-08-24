<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full min-h-full p-5 gap-4">
        <native:button label="{{ __('native.content.back') }}" variant="text" @press="portal" />
        @if ($error !== '')
            <native:text class="text-sm text-theme-error">{{ $error }}</native:text>
        @else
            <native:text class="text-2xl font-extrabold text-theme-on-background">
                {{ $content['title'] ?? $content['name'] ?? __('native.content.title') }}
            </native:text>
            @if (! empty($content['excerpt']) || ! empty($content['description']))
                <native:text class="text-base text-theme-on-surface-variant">
                    {{ $content['excerpt'] ?? $content['description'] }}
                </native:text>
            @endif
            @if (! empty($content['started_at']))
                <native:text class="text-sm text-theme-on-surface-variant">
                    {{ __('native.content.started_at', ['date' => $content['started_at']]) }}
                </native:text>
            @endif
        @endif
    </native:column>
</native:scroll-view>
