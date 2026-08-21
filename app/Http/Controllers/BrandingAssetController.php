<?php

namespace App\Http\Controllers;

use App\Support\ChurchBrandingResolver;
use App\Support\ChurchDomainContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class BrandingAssetController extends Controller
{
    public function logo(
        ChurchDomainContext $context,
        ChurchBrandingResolver $brandingResolver,
    ): Response|RedirectResponse {
        $logo = $brandingResolver->logoContents($context->church());

        if ($logo['external_url'] !== null) {
            return redirect()->away($logo['external_url']);
        }

        abort_if($logo['contents'] === null, 404);

        return response($logo['contents'], 200, $this->assetHeaders($this->imageMimeType($logo['mime_type'])));
    }

    public function icon(
        ChurchDomainContext $context,
        ChurchBrandingResolver $brandingResolver,
    ): Response {
        $logo = $brandingResolver->iconContents($context->church());
        $mimeType = $this->imageMimeType($logo['mime_type']);
        $imageUrl = $logo['external_url'] !== null
            ? htmlspecialchars($logo['external_url'], ENT_QUOTES | ENT_XML1, 'UTF-8')
            : 'data:'.$mimeType.';base64,'.base64_encode((string) $logo['contents']);
        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img">
                <image href="{$imageUrl}" x="16" y="16" width="480" height="480" preserveAspectRatio="xMidYMid meet" />
            </svg>
            SVG;

        return response($svg, 200, [
            ...$this->assetHeaders('image/svg+xml; charset=UTF-8'),
            'Content-Security-Policy' => "default-src 'none'; img-src data: https: http:",
        ]);
    }

    /** @return array<string, string> */
    private function assetHeaders(string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'public, max-age=300, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    private function imageMimeType(string $mimeType): string
    {
        return in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)
            ? $mimeType
            : 'image/png';
    }
}
