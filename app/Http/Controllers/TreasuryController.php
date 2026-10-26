<?php

namespace App\Http\Controllers;

use App\Enums\ContributionType;
use App\Enums\UserRole;
use App\Models\Church;
use App\Support\ChurchDomainContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TreasuryController extends Controller
{
    /**
     * Roles allowed to manage the treasury within their church (treasurer,
     * pastor/church leadership) plus global system/superadmin.
     *
     * @var array<int, UserRole>
     */
    private const TREASURY_ROLES = [
        UserRole::TREASURER, UserRole::CHURCH_LEADER, UserRole::SUPERADMIN,
    ];

    /**
     * Public page where anyone (logged in or not) can see how to contribute
     * and declare a tithe or offering.
     */
    public function show(): Response
    {
        $church = app(ChurchDomainContext::class)->church();
        abort_if($church === null, 404);

        $settings = $church->treasurySetting;

        return Inertia::render('Treasury/Contribute', [
            'church' => ['id' => $church->id, 'name' => $church->name],
            'treasury' => $settings === null || ! $settings->accepts_contributions ? null : [
                'pix_key_type' => $settings->pix_key_type,
                'pix_key' => $settings->pix_key,
                'bank_name' => $settings->bank_name,
                'bank_account' => $settings->bank_account,
                'account_holder' => $settings->account_holder,
                'instructions' => $settings->instructions,
            ],
            'types' => [
                ['value' => ContributionType::TITHE->value, 'label' => __('treasury.types.tithe')],
                ['value' => ContributionType::OFFERING->value, 'label' => __('treasury.types.offering')],
            ],
        ]);
    }

    /**
     * Register a tithe or offering. Allowed for both guests and members.
     */
    public function contribute(Request $request): RedirectResponse
    {
        $church = app(ChurchDomainContext::class)->church();
        abort_if($church === null, 404);

        $settings = $church->treasurySetting;
        abort_unless($settings !== null && $settings->accepts_contributions, 403, __('treasury.not_accepting'));

        $validated = $request->validate([
            'type' => ['required', Rule::enum(ContributionType::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'contributor_name' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $church->contributions()->create([
            'user_id' => $request->user()?->id,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'contributor_name' => $validated['contributor_name'] ?? null,
            'message' => $validated['message'] ?? null,
            'contributed_at' => Carbon::now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('treasury.notifications.contribution_recorded')]);

        return back();
    }

    /**
     * Treasury configuration screen (restricted).
     */
    public function settings(Request $request): Response
    {
        $church = $this->currentChurch($request);
        $this->ensureCanManageTreasury($request, $church);

        $settings = $church->treasurySetting;

        return Inertia::render('Admin/Treasury', [
            'settings' => [
                'pix_key_type' => $settings?->pix_key_type,
                'pix_key' => $settings?->pix_key,
                'bank_name' => $settings?->bank_name,
                'bank_account' => $settings?->bank_account,
                'account_holder' => $settings?->account_holder,
                'instructions' => $settings?->instructions,
                'accepts_contributions' => $settings?->accepts_contributions ?? true,
            ],
            'report' => $this->monthlyReport($church),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $church = $this->currentChurch($request);
        $this->ensureCanManageTreasury($request, $church);

        $validated = $request->validate([
            'pix_key_type' => ['nullable', 'string', 'max:50'],
            'pix_key' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:255'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'accepts_contributions' => ['boolean'],
        ]);

        $church->treasurySetting()->updateOrCreate([], $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('treasury.notifications.settings_updated')]);

        return back();
    }

    /**
     * Monthly report export as CSV (restricted).
     */
    public function export(Request $request): StreamedResponse
    {
        $church = $this->currentChurch($request);
        $this->ensureCanManageTreasury($request, $church);

        $month = $this->resolveMonth($request);
        $contributions = $church->contributions()
            ->whereBetween('contributed_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('contributed_at')
            ->get();

        $filename = __('treasury.export.filename').'-'.$month->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($contributions): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                __('treasury.export.date'),
                __('treasury.export.type'),
                __('treasury.export.amount'),
                __('treasury.export.contributor'),
                __('treasury.export.message'),
            ]);

            foreach ($contributions as $contribution) {
                fputcsv($handle, array_map($this->csvSafe(...), [
                    $contribution->contributed_at?->format('Y-m-d H:i'),
                    __('treasury.types.'.$contribution->type->value),
                    number_format((float) $contribution->amount, 2, '.', ''),
                    $contribution->contributor_name ?? ($contribution->user_id ? '' : __('treasury.anonymous')),
                    $contribution->message,
                ]));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Neutralize spreadsheet formula injection for user-supplied cells.
     */
    private function csvSafe(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }

    private function currentChurch(Request $request): Church
    {
        $churchId = app(ChurchDomainContext::class)->churchId() ?? $request->user()?->church?->id;
        abort_if($churchId === null, 422, __('church.context.required'));

        return Church::query()->findOrFail($churchId);
    }

    private function ensureCanManageTreasury(Request $request, Church $church): void
    {
        $user = $request->user();

        if ($user?->role === UserRole::SYSTEM) {
            return;
        }

        abort_unless(
            $user?->church?->id === $church->id
                && in_array($user->role, self::TREASURY_ROLES, true),
            403
        );
    }

    private function resolveMonth(Request $request): Carbon
    {
        $month = (string) $request->query('month', '');

        return $month !== '' && preg_match('/^\d{4}-\d{2}$/', $month) === 1
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : Carbon::now()->startOfMonth();
    }

    /**
     * @return array{month: string, tithe_total: string, offering_total: string, total: string, count: int}
     */
    private function monthlyReport(Church $church, ?Carbon $month = null): array
    {
        $month ??= Carbon::now()->startOfMonth();
        $query = $church->contributions()
            ->whereBetween('contributed_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);

        $titheTotal = (clone $query)->where('type', ContributionType::TITHE->value)->sum('amount');
        $offeringTotal = (clone $query)->where('type', ContributionType::OFFERING->value)->sum('amount');

        return [
            'month' => $month->format('Y-m'),
            'tithe_total' => number_format((float) $titheTotal, 2, '.', ''),
            'offering_total' => number_format((float) $offeringTotal, 2, '.', ''),
            'total' => number_format((float) $titheTotal + (float) $offeringTotal, 2, '.', ''),
            'count' => (int) (clone $query)->count(),
        ];
    }
}
