<?php

namespace App\Services;

use App\Contracts\HolidayProvider;
use App\Models\CallOffRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CallOffAmendmentRules
{
    public const EXPLANATION_REQUIRED_REASON = 'OTHER';

    public function __construct(
        private readonly CallOffLeadTimeService $leadTimes,
        private readonly HolidayProvider $holidays,
    ) {}

    /** @return array<string, string> */
    public function reasons(): array
    {
        return config('call_off_amendments.reasons', []);
    }

    public function revision(CallOffRequest $request): string
    {
        return hash('sha256', json_encode([
            $request->uuid, $request->stateSnapshot(),
            $request->histories()->max('sequence'),
            $request->dateNegotiations()->max('id'),
        ], JSON_THROW_ON_ERROR));
    }

    public function requiresExplanation(mixed $reasonCode): bool
    {
        return $reasonCode === self::EXPLANATION_REQUIRED_REASON;
    }

    /** @return array{requested_date: string, reason_code: string, customer_response: ?string} */
    public function validate(array $input): array
    {
        if ($this->reasons() === []) {
            throw ValidationException::withMessages(['reason_code' => 'Date change reasons are awaiting confirmation. Please contact Fenster.']);
        }

        // Also normalize direct action callers, which do not pass HTTP middleware.
        if (isset($input['customer_response']) && is_string($input['customer_response'])) {
            $input['customer_response'] = Str::trim($input['customer_response']);
            if ($input['customer_response'] === '') {
                $input['customer_response'] = null;
            }
        }

        return Validator::make($input, [
            'requested_date' => ['required', 'date_format:Y-m-d', 'after:today'],
            'reason_code' => ['required', 'string', 'max:80', Rule::in(array_keys($this->reasons()))],
            'customer_response' => [Rule::requiredIf($this->requiresExplanation($input['reason_code'] ?? null)), 'nullable', 'string', 'max:2000'],
        ], [
            'customer_response.required' => 'Add additional information when you select Other.',
            'customer_response.max' => 'Additional information must not exceed 2,000 characters.',
        ])->validate() + ['customer_response' => null];
    }

    public function validateDate(string $value): CarbonImmutable
    {
        Validator::make(['requested_date' => $value], [
            'requested_date' => ['required', 'date_format:Y-m-d', 'after:today'],
        ])->validate();
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        if (! $this->leadTimes->isPermittedRequestedDate($date)) {
            throw ValidationException::withMessages(['requested_date' => 'Choose a working day within the next six months.']);
        }

        return $date;
    }

    public function isUrgent(CarbonInterface $agreedDate, ?CarbonInterface $from = null): bool
    {
        $today = CarbonImmutable::instance($from ?? now())->startOfDay();
        $threshold = CarbonImmutable::instance($agreedDate)->startOfDay();
        for ($days = 0; $days < 3;) {
            $threshold = $threshold->subDay();
            if ($threshold->isWeekday() && ! $this->holidays->isHoliday($threshold)) {
                $days++;
            }
        }

        // Dates already passed are late, not ineligible.
        return $today->greaterThanOrEqualTo($threshold);
    }
}
