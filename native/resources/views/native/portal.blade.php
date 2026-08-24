<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full min-h-full p-5 gap-4">
        <native:text class="text-2xl font-extrabold text-theme-on-background">
            {{ $serverName }}
        </native:text>
        @if ($offline)
            <native:badge
                label="{{ $stale ? __('native.offline.stale') : __('native.offline.available') }}"
                variant="warning"
            />
        @endif
        @if ($error !== '')
            <native:text class="text-sm text-theme-error">{{ $error }}</native:text>
        @endif
        <native:row class="w-full gap-3">
            <native:button label="{{ __('native.portal.refresh') }}" @press="load" />
            @if ($authenticated)
                <native:button label="{{ __('native.portal.churches') }}" variant="outlined" @press="churches" />
            @endif
        </native:row>
        <native:text class="text-lg font-bold text-theme-on-background">
            {{ __('native.portal.highlights') }}
        </native:text>
        @foreach (($content['latestPosts'] ?? []) as $post)
            <native:column class="w-full p-4 gap-2 bg-theme-surface rounded-xl">
                <native:text class="text-base font-bold text-theme-on-surface">
                    {{ $post['title'] ?? '' }}
                </native:text>
                <native:text class="text-sm text-theme-on-surface-variant">
                    {{ $post['excerpt'] ?? '' }}
                </native:text>
            </native:column>
        @endforeach
        @if (! empty($content['featuredEvents']))
            <native:text class="text-lg font-bold text-theme-on-background">
                {{ __('native.portal.events') }}
            </native:text>
            @foreach ($content['featuredEvents'] as $event)
                <native:column class="w-full p-4 gap-2 bg-theme-surface rounded-xl">
                    <native:text class="text-base font-bold text-theme-on-surface">
                        {{ $event['title'] ?? '' }}
                    </native:text>
                    <native:text class="text-sm text-theme-on-surface-variant">
                        {{ $event['excerpt'] ?? '' }}
                    </native:text>
                </native:column>
            @endforeach
        @endif
        @if (! empty($content['dailyVerse']))
            <native:column class="w-full p-4 gap-2 bg-theme-surface rounded-xl">
                <native:text class="text-lg font-bold text-theme-on-surface">
                    {{ __('native.portal.daily_verse') }}
                </native:text>
                <native:text class="text-sm text-theme-on-surface-variant">
                    {{ $content['dailyVerse']['text'] ?? '' }}
                </native:text>
                <native:text class="text-xs text-theme-on-surface-variant">
                    {{ $content['dailyVerse']['reference'] ?? '' }}
                </native:text>
            </native:column>
        @endif
        @if (! empty($content['liveStream']))
            <native:column class="w-full p-4 gap-2 bg-theme-surface rounded-xl">
                <native:row class="w-full justify-between">
                    <native:text class="text-lg font-bold text-theme-on-surface">
                        {{ __('native.portal.live') }}
                    </native:text>
                    <native:badge
                        label="{{ $realtimeConnected ? __('native.portal.realtime_connected') : __('native.portal.realtime_reconnecting') }}"
                        variant="{{ $realtimeConnected ? 'success' : 'warning' }}"
                    />
                </native:row>
                <native:text class="text-sm text-theme-on-surface-variant">
                    {{ $content['liveStream']['name'] ?? '' }}
                </native:text>
            </native:column>
        @endif
        @if ($authenticated)
            <native:button label="{{ __('native.logout') }}" variant="text" @press="logout" />
        @else
            <native:button label="{{ __('native.login.submit') }}" variant="outlined" @press="login" />
        @endif
        <native:button label="{{ __('native.offline.title') }}" variant="outlined" @press="offline" />
        <native:button label="{{ __('native.server.change') }}" variant="text" @press="changeServer" />
    </native:column>
</native:scroll-view>
