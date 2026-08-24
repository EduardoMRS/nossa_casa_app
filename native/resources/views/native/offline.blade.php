<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full min-h-full p-5 gap-4">
        <native:text class="text-2xl font-extrabold text-theme-on-background">
            {{ __('native.offline.title') }}
        </native:text>
        <native:text class="text-sm text-theme-on-surface-variant">
            {{ __('native.offline.description') }}
        </native:text>
        @if ($status !== '')
            <native:text class="text-sm text-theme-primary">{{ $status }}</native:text>
        @endif
        <native:button
            label="{{ __('native.offline.sync') }}"
            @press="sync"
            :loading="$syncing"
            :disabled="$syncing"
        />
        <native:outlined-text-input
            label="{{ __('native.offline.bible_version') }}"
            placeholder="{{ __('native.offline.bible_placeholder') }}"
            native:model.blur="bibleVersion"
        />
        <native:button
            label="{{ __('native.offline.download_bible') }}"
            variant="outlined"
            @press="downloadBible"
        />
        @foreach ($caches as $cache)
            <native:row class="w-full p-3 justify-between bg-theme-surface rounded-xl">
                <native:text class="text-sm font-bold text-theme-on-surface">{{ $cache['key'] }}</native:text>
                <native:badge
                    label="{{ $cache['stale'] ? __('native.offline.stale_short') : __('native.offline.ready') }}"
                    variant="{{ $cache['stale'] ? 'warning' : 'success' }}"
                />
            </native:row>
        @endforeach
        <native:button label="{{ __('native.offline.back') }}" variant="text" @press="portal" />
    </native:column>
</native:scroll-view>
