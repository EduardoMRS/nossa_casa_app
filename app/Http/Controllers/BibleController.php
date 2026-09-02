<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Services\Bible\BibleAccessResolver;
use App\Services\Bible\BibleApiClient;
use App\Support\ChurchDomainContext;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class BibleController extends Controller
{
    public function __construct(
        private readonly BibleApiClient $bible,
        private readonly BibleAccessResolver $access,
        private readonly ChurchDomainContext $context,
    ) {}

    public function books(string $version): JsonResponse
    {
        $allowedVersion = $this->allowedVersion($version);

        return $this->withOfflinePolicy(
            $this->respond(fn (): array => ['books' => $this->bible->books($allowedVersion)]),
            $allowedVersion,
        );
    }

    public function offline(string $version): JsonResponse
    {
        $allowedVersion = $this->allowedVersion($version);
        abort_unless($this->bible->canDownloadOffline($allowedVersion), 404);

        return $this->withOfflinePolicy(
            $this->respond(function () use ($allowedVersion): array {
                return $this->bible->offlineBundle($allowedVersion);
            }),
            $allowedVersion,
        );
    }

    public function chapters(string $version, string $book): JsonResponse
    {
        $allowedVersion = $this->allowedVersion($version);

        return $this->withOfflinePolicy(
            $this->respond(function () use ($allowedVersion, $book): array {
                $this->ensureBook($allowedVersion, $book);

                return ['chapters' => $this->bible->chapters($allowedVersion, $book)];
            }),
            $allowedVersion,
        );
    }

    public function chapter(string $version, string $book, int $chapter): JsonResponse
    {
        $allowedVersion = $this->allowedVersion($version);

        return $this->withOfflinePolicy(
            $this->respond(function () use ($allowedVersion, $book, $chapter): array {
                $this->ensureBook($allowedVersion, $book);

                return ['verses' => $this->bible->chapter($allowedVersion, $book, $chapter)];
            }),
            $allowedVersion,
        );
    }

    private function allowedVersion(string $version): string
    {
        $user = request()->user();
        $church = $user !== null && $user->church === null
            ? null
            : $this->context->church();
        $allowed = $church instanceof Church
            ? in_array($version, $this->access->forChurch($church)['versions'], true)
            : collect($this->bible->versions())->contains('id', $version);

        abort_unless($allowed, 404);

        return $version;
    }

    private function ensureBook(string $version, string $book): void
    {
        abort_unless(collect($this->bible->books($version))->contains('slug', $book), 404);
    }


    /** @param callable(): array<string, mixed> $callback */
    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback());
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => __('bible.service_unavailable')], 503);
        }
    }

    private function withOfflinePolicy(JsonResponse $response, string $version): JsonResponse
    {
        return $response->header(
            'X-Bible-Offline-Allowed',
            $this->bible->canDownloadOffline($version) ? '1' : '0',
        );
    }
}
