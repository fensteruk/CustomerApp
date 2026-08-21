<?php

namespace App\Services;

use App\Actions\CallOff\BuildCallOffMatrixAction;
use App\Actions\CallOff\SubmitMultiCallOffBatchAction;
use App\Models\CallOffBatch;
use App\Models\Site;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;

class CallOffSubmissionWorkflow
{
    public const SESSION_KEY = 'sprint_3d_call_off_confirmation';

    public function __construct(private readonly BuildCallOffMatrixAction $matrix, private readonly SubmitMultiCallOffBatchAction $submit) {}

    /** @param array<int,string> $plots @param array<string,string> $dates @param array<int,string> $excluded @param array<string,string> $reasons */
    public function review(User $user, Site $site, array $plots, array $dates, array $excluded, array $reasons, ?string $message, Session $session): array
    {
        $rows = $this->matrix->handle($user, $site, $plots, $dates, $excluded, $reasons);
        $payload = ['user_id' => $user->id, 'site_id' => $site->id, 'message' => $message ?? '', 'rows' => collect($rows)->sortBy('key')->values()->all(), 'request_count' => collect($rows)->where('included', true)->count()];
        if ($payload['request_count'] === 0) {
            throw ValidationException::withMessages(['combinations' => 'Select at least one available plot and service combination.']);
        }
        $payload['signature'] = hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), (string) config('app.key'));
        $session->put(self::SESSION_KEY, $payload);

        return $payload;
    }

    public function submit(User $user, Site $site, string $signature, Session $session): CallOffBatch
    {
        $payload = $session->pull(self::SESSION_KEY);
        if (! is_array($payload) || ! hash_equals((string) ($payload['signature'] ?? ''), $signature) || (int) ($payload['user_id'] ?? 0) !== (int) $user->id || (int) ($payload['site_id'] ?? 0) !== (int) $site->id) {
            throw ValidationException::withMessages(['request' => 'Review the call-off details again before submitting.']);
        }
        $plots = collect($payload['rows'])->pluck('plot_uuid')->unique()->values()->all();
        $dates = collect($payload['rows'])->unique('service')->mapWithKeys(fn ($row) => [$row['service'] => $row['requested_date']])->all();
        $excluded = collect($payload['rows'])->where('included', false)->pluck('key')->all();
        $reasons = collect($payload['rows'])->filter(fn ($row) => $row['is_early_exception'])->mapWithKeys(fn ($row) => [$row['key'] => $row['early_reason']])->all();
        $fresh = $this->matrix->handle($user, $site, $plots, $dates, $excluded, $reasons);
        $freshPayload = ['user_id' => $user->id, 'site_id' => $site->id, 'message' => $payload['message'], 'rows' => collect($fresh)->sortBy('key')->values()->all(), 'request_count' => collect($fresh)->where('included', true)->count()];
        if (! hash_equals($payload['signature'], hash_hmac('sha256', json_encode($freshPayload, JSON_THROW_ON_ERROR), (string) config('app.key')))) {
            throw ValidationException::withMessages(['request' => 'One or more plot services changed. Review the call-off again.']);
        }

        return $this->submit->handle($user, $site, collect($fresh)->where('included', true)->map(fn ($row) => ['plot_service_id' => $row['plot_service_id'], 'requested_date' => $row['requested_date'], 'early_date_reason' => $row['early_reason']])->values()->all(), $payload['message']);
    }
}
