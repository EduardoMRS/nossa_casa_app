{{ $branding['name'] }}
{{ str_repeat('=', mb_strlen($branding['name'])) }}

{{ $heading }}

{{ $introduction }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
@if (filled($actionText) && filled($actionUrl))
{{ $actionText }}:
{{ $actionUrl }}

@endif
@if (filled($outro))
{{ $outro }}

@endif
@if ($branding['is_church'])
{{ __('mail.footer.church', ['brand' => $branding['name']]) }}
@else
{{ __('mail.footer.application') }}
@endif

{{ __('mail.footer.legal') }}: {{ $branding['legal_url'] }}
