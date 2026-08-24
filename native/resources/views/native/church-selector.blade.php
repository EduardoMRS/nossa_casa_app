<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full min-h-full p-5 gap-4">
        <native:text class="text-2xl font-extrabold text-theme-on-background">
            {{ __('native.church.title') }}
        </native:text>
        @forelse ($memberships as $membership)
            <native:button
                label="{{ $membership['name'] ?? '' }}"
                variant="outlined"
                @press="select('{{ $membership['church_id'] ?? '' }}')"
            />
        @empty
            <native:text class="text-base text-theme-on-surface-variant">
                {{ __('native.church.empty') }}
            </native:text>
        @endforelse
    </native:column>
</native:scroll-view>
