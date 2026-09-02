<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $heading }}</title>
</head>
<body style="margin: 0; padding: 0; background: #eef2f7; color: #172033; font-family: Arial, Helvetica, sans-serif;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0;">
        {{ $preheader }}
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; background: #eef2f7;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 620px; overflow: hidden; border: 1px solid #dfe5ee; border-radius: 18px; background: #ffffff; box-shadow: 0 12px 36px rgba(15, 23, 42, 0.08);">
                    <tr>
                        <td style="padding: 28px 32px; background: {{ $branding['primary_color'] }}; background-image: linear-gradient(135deg, {{ $branding['primary_color'] }}, {{ $branding['secondary_color'] }});">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="64" valign="middle">
                                        <div style="width: 52px; height: 52px; overflow: hidden; border-radius: 14px; background: #ffffff; text-align: center;">
                                            <img src="{{ $branding['logo_url'] }}" width="52" height="52" alt="{{ $branding['name'] }}" style="display: block; width: 52px; height: 52px; object-fit: contain;">
                                        </div>
                                    </td>
                                    <td valign="middle" style="padding-left: 14px;">
                                        <div style="font-size: 21px; font-weight: 700; line-height: 1.25; color: #ffffff;">
                                            {{ $branding['name'] }}
                                        </div>
                                        @if ($branding['tagline'] !== '')
                                            <div style="margin-top: 5px; font-size: 13px; line-height: 1.4; color: rgba(255, 255, 255, 0.82);">
                                                {{ $branding['tagline'] }}
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 38px 36px 34px;">
                            <div style="width: 42px; height: 4px; margin-bottom: 22px; border-radius: 999px; background: {{ $branding['accent_color'] }};"></div>
                            <h1 style="margin: 0; color: #111827; font-size: 28px; line-height: 1.25; letter-spacing: -0.02em;">
                                {{ $heading }}
                            </h1>
                            <p style="margin: 18px 0 0; color: #475569; font-size: 16px; line-height: 1.7;">
                                {{ $introduction }}
                            </p>

                            @foreach ($lines as $line)
                                <p style="margin: 14px 0 0; color: #475569; font-size: 15px; line-height: 1.7;">
                                    {{ $line }}
                                </p>
                            @endforeach

                            @if (filled($actionText) && filled($actionUrl))
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top: 28px;">
                                    <tr>
                                        <td style="border-radius: 10px; background: {{ $branding['primary_color'] }};">
                                            <a href="{{ $actionUrl }}" style="display: inline-block; padding: 13px 22px; color: #ffffff; font-size: 15px; font-weight: 700; text-decoration: none;">
                                                {{ $actionText }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if (filled($outro))
                                <p style="margin: 28px 0 0; color: #64748b; font-size: 14px; line-height: 1.65;">
                                    {{ $outro }}
                                </p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="border-top: 1px solid #e5eaf1; padding: 22px 32px; background: #f8fafc; text-align: center;">
                            <p style="margin: 0; color: #718096; font-size: 12px; line-height: 1.6;">
                                @if ($branding['is_church'])
                                    {{ __('mail.footer.church', ['brand' => $branding['name']]) }}
                                @else
                                    {{ __('mail.footer.application') }}
                                @endif
                            </p>
                            <p style="margin: 8px 0 0; font-size: 12px;">
                                <a href="{{ $branding['legal_url'] }}" style="color: {{ $branding['primary_color'] }}; text-decoration: underline;">
                                    {{ __('mail.footer.legal') }}
                                </a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
