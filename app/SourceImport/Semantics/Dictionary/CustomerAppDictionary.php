<?php

namespace App\SourceImport\Semantics\Dictionary;

use App\SourceImport\Semantics\Contracts\SourceBusinessDictionary;
use App\SourceImport\Semantics\Data\DictionaryIdentity;
use App\SourceImport\Semantics\Data\ProductRollup;
use App\SourceImport\Semantics\Data\SemanticResult;
use App\SourceImport\Semantics\Enums\Classification as C;
use App\SourceImport\Semantics\Enums\ExportScope;
use App\SourceImport\Semantics\Enums\Resolution as R;
use InvalidArgumentException;

final readonly class CustomerAppDictionary implements SourceBusinessDictionary
{
    public const VERSION = 'customerapp.source-dictionary.v6';

    public const COMPOSITE_PROFILE = 'custapp2_composite';

    private const CALLS = [
        'PC1' => ['description' => 'Plot Install', 'service' => 'windows', 'revisit' => false],
        'CC1' => ['description' => 'Cavity Closer 1', 'service' => 'cavity_closers', 'revisit' => false],
        'CM1' => ['description' => 'Revisit 1', 'service' => 'cml', 'revisit' => true],
        'CM2' => ['description' => 'Revisit 2', 'service' => 'cml', 'revisit' => true],
        'CML' => ['description' => 'CML Call Off', 'service' => 'cml', 'revisit' => false],
    ];

    private const EXCLUDED_CALLS = [
        'CU4' => 'Customer care; no CustomerApp projection',
        'CM8' => 'Irrelevant to CustomerApp', 'P02' => 'Irrelevant to CustomerApp',
        'P06' => 'Irrelevant to CustomerApp', 'P08' => 'Irrelevant to CustomerApp',
        'Q01' => 'Irrelevant to CustomerApp', 'QU5' => 'Irrelevant to CustomerApp',
        'SS1' => 'Irrelevant to CustomerApp', 'T03' => 'Irrelevant to CustomerApp',
        'T05' => 'Irrelevant to CustomerApp', 'T07' => 'Irrelevant to CustomerApp',
        'T09' => 'Irrelevant to CustomerApp', 'T11' => 'Irrelevant to CustomerApp',
        'T13' => 'Irrelevant to CustomerApp', 'T15' => 'Irrelevant to CustomerApp',
        'VC1' => 'Irrelevant to CustomerApp', 'X10' => 'Irrelevant to CustomerApp',
        'X14' => 'Irrelevant to CustomerApp', 'X16' => 'Irrelevant to CustomerApp',
        'X50' => 'Irrelevant to CustomerApp', 'X99' => 'Irrelevant to CustomerApp',
        'XR1' => 'Irrelevant to CustomerApp', 'XR2' => 'Irrelevant to CustomerApp',
        'XX1' => 'Irrelevant to CustomerApp', 'Z05' => 'Irrelevant to CustomerApp',
        'Z09' => 'Irrelevant to CustomerApp',
    ];

    private const WINDOWS = ['VS' => 'Vertical Slider', 'TT' => 'Tilt and Turn', 'BAY' => 'Bay Window',
        'ALI' => 'Aluminium Windows', 'AOV' => 'Automatic Opening Vent Window', 'FI' => 'Fire Window'];

    private const DOORS = ['PSU' => 'PVC Door Utility', 'PSG' => 'PVC Door Garage',
        'CDF' => 'Composite Door Front', 'CDU' => 'Composite Door Utility',
        'CDG' => 'Composite Door Garage', 'PSP' => 'PVC Sliding Patio', 'BF' => 'Bifold'];

    private const EXCLUDED = ['CAS', 'FLU', 'PFD', 'GLS', 'WP', 'MISC'];

    private const FIELDS = [
        'items ordered status' => ['description' => 'Items Ordered Status', 'role' => 'ignored'],
        'site value' => ['description' => 'Site Value', 'role' => 'ignored'],
        'plot to be installed' => ['description' => 'PC1 operational arrival/installation date', 'role' => 'pc1_operational_install_date'],
        'site name' => ['description' => 'Transitional source-site clue', 'role' => 'transitional_site_clue'],
        'customerno' => ['description' => 'Authoritative RedZebra CustomerCode', 'role' => 'source_customer_code'],
        'customercode' => ['description' => 'Authoritative RedZebra CustomerCode', 'role' => 'source_customer_code'],
        'customer number' => ['description' => 'Authoritative RedZebra CustomerCode', 'role' => 'source_customer_code'],
        'source site id' => ['description' => 'Durable source identity when supplied', 'role' => 'source_site_identity'],
        'source site reference' => ['description' => 'Durable source identity when supplied', 'role' => 'source_site_identity'],
        'call no.' => ['description' => 'Source call-off reference', 'role' => 'call_reference'],
        'plot ref' => ['description' => 'Source plot reference', 'role' => 'plot_reference'],
        'call type' => ['description' => 'Source call type', 'role' => 'call_type'],
        'complete' => ['description' => 'Source call-off part completion flag', 'role' => 'completion'],
        'completed' => ['description' => 'Source call-off part completion flag', 'role' => 'completion'],
    ];

    private DictionaryIdentity $identity;

    public function __construct()
    {
        $this->identity = DictionaryIdentity::fromDefinition(self::VERSION, self::definition());
    }

    public static function definition(): array
    {
        return ['calls' => self::CALLS, 'excluded_calls' => self::EXCLUDED_CALLS,
            'invalid_calls' => ['CC!' => ['suggestion' => 'CC1', 'reason' => 'LIKELY_TYPO']],
            'windows' => self::WINDOWS, 'doors' => self::DOORS, 'excluded' => self::EXCLUDED,
            'fields' => self::FIELDS, 'completion' => ['yes' => true, 'no' => false],
            'normalization' => ['codes' => 'ASCII uppercase and surrounding whitespace trim; punctuation preserved',
                'headers' => 'Exact field labels, except CallNo semantic token pairs ignore case, spacing and punctuation; CustomerNo, CustomerCode and Customer Number are exact approved CustomerCode headers',
                'quantity' => 'plain nonnegative decimal, at most 3 fractional digits; unrepresented values make no assertion; no exponent/grouping/bool',
                'quantity_max_units' => Quantity::MAX_UNITS, 'scale' => 1000,
                'duplicates' => 'reject normalized duplicate codes', 'overflow' => 'null totals and blocked',
                'absence' => 'zero within supplied input only; no projection authority'],
            'rollups' => ['WINDOWS' => 'Total Windows', 'DOORS' => 'Total Doors'],
            'bf' => 'exact normalized BF with validated positive quantity only',
            'scope' => ['default' => ExportScope::PartialFilteredExport->value,
                'stronger' => [ExportScope::SiteCompleteSnapshot->value, ExportScope::GlobalCompleteSnapshot->value],
                'resolution' => 'explicit assertion still requires downstream confirmation'],
            'label_change' => 'requires new version'];
    }

    public function identity(): DictionaryIdentity
    {
        return $this->identity;
    }

    public function callType(string $raw, ?string $profile = null): SemanticResult
    {
        $key = strtoupper(trim($raw));
        if ($key === '') {
            return $this->result('call_type', $raw, null, C::Confirmed, ['description' => 'No call-off started'], null,
                ['BLANK_CALL_TYPE_NO_VISIT']);
        }
        if (self::excludesCallType($raw)) {
            return $this->result('call_type', $raw, $key, C::Ignored,
                ['code' => $key, 'description' => self::EXCLUDED_CALLS[$key]], null,
                ['IRRELEVANT_TO_CUSTOMERAPP']);
        }
        if ($profile === self::COMPOSITE_PROFILE && ! in_array($key, ['PC1', 'CC1', 'CM1'], true)) {
            return $this->result('call_type', $raw, $key, C::Unknown, null, null, ['UNKNOWN_CALL_TYPE']);
        }
        if ($key === 'CC!') {
            return $this->result('call_type', $raw, $key, C::Invalid, null, null,
                ['LIKELY_TYPO'], [['code' => 'CC1', 'reason' => 'LIKELY_TYPO']]);
        }
        $entry = self::CALLS[$key] ?? null;

        return $this->result('call_type', $raw, $key, $entry ? C::Confirmed : C::Unknown,
            $entry === null ? null : ['code' => $key, ...$entry], $entry['service'] ?? null,
            [$entry ? 'EXACT_DICTIONARY_MATCH' : 'UNKNOWN_CALL_TYPE']);
    }

    public static function excludesCallType(string $raw): bool
    {
        return isset(self::EXCLUDED_CALLS[strtoupper(trim($raw))]);
    }

    public function completion(string|int|float|bool|null $raw): SemanticResult
    {
        $key = is_string($raw) ? strtolower(trim($raw)) : null;
        $known = in_array($key, ['yes', 'no'], true);

        return $this->result('completion', $raw, $key, $known ? C::Confirmed : C::Unknown,
            $known ? ['description' => 'Source part completion flag'] : null,
            $known ? $key === 'yes' : null, [$known ? 'EXACT_YES_NO' : 'UNKNOWN_COMPLETION']);
    }

    public function product(string $code, string|int|float|bool|null $quantity): SemanticResult
    {
        $key = strtoupper(trim($code));
        $entry = isset(self::WINDOWS[$key]) ? ['description' => self::WINDOWS[$key], 'group' => 'WINDOWS']
            : (isset(self::DOORS[$key]) ? ['description' => self::DOORS[$key], 'group' => 'DOORS'] : null);
        $excluded = in_array($key, self::EXCLUDED, true);
        $evidence = ['raw_code' => $code];
        if ($entry === null && ! $excluded) {
            return $this->result('product_quantity', $quantity, $key, C::Unknown, null, null, ['UNKNOWN_PRODUCT_CODE'], evidence: $evidence);
        }
        $units = Quantity::units($quantity);
        $match = ['code' => $key, ...($entry ?? ['description' => $key, 'group' => 'EXCLUDED'])];
        if ($units === null) {
            return $this->result('product_quantity', $quantity, $key, C::Invalid, $match, null, ['INVALID_QUANTITY'], evidence: $evidence);
        }

        return $this->result('product_quantity', $quantity, $key, $excluded ? C::Ignored : C::Confirmed,
            $match, Quantity::decimal($units), [$excluded ? 'EXCLUDED_PRODUCT' : 'EXACT_DICTIONARY_MATCH'], evidence: $evidence);
    }

    public function field(string $raw): SemanticResult
    {
        $key = strtolower(trim($raw));
        $entry = CallReferenceHeader::matches($raw)
            ? ['description' => 'Source call-off reference', 'role' => 'call_reference']
            : (self::FIELDS[$key] ?? null);

        return $this->result('field', $raw, $key, $entry === null ? C::Unknown
            : ($entry['role'] === 'ignored' ? C::Ignored : C::Confirmed),
            $entry, $entry['role'] ?? null, [$entry === null ? 'UNKNOWN_FIELD' : 'EXACT_FIELD_TREATMENT']);
    }

    public function scope(?ExportScope $assertion = null): SemanticResult
    {
        $scope = $assertion ?? ExportScope::PartialFilteredExport;

        return new SemanticResult('export_scope', $assertion?->value, $scope->value, C::Confirmed,
            $scope === ExportScope::PartialFilteredExport ? R::Resolved : R::RequiresConfirmation,
            $this->identity, ['description' => $scope->value], $scope->value,
            reasons: [$scope === ExportScope::PartialFilteredExport ? 'PARTIAL_DEFAULT' : 'DOWNSTREAM_SCOPE_CONFIRMATION_REQUIRED'],
            evidence: ['explicit_assertion' => $assertion !== null]);
    }

    /** Input contains quantities for one caller-supplied record, never a projection snapshot. */
    public function rollup(array $products): ProductRollup
    {
        $items = [];
        $seen = [];
        $totals = ['WINDOWS' => 0, 'DOORS' => 0];
        $reasons = [];
        $bf = false;
        ksort($products, SORT_STRING);
        foreach ($products as $code => $quantity) {
            if (! is_string($code) || (! is_scalar($quantity) && $quantity !== null)) {
                throw new InvalidArgumentException('invalid_product_input');
            }
            $item = $this->product($code, $quantity);
            $key = $item->lookupValue;
            if (isset($seen[$key])) {
                $reasons[] = 'DUPLICATE_PRODUCT_CODE';
            }
            $seen[$key] = true;
            $items[] = $item;
            if (! $item->isResolved()) {
                $reasons[] = 'UNRESOLVED_PRODUCT';

                continue;
            }
            if ($item->classification === C::Ignored) {
                continue;
            }
            $units = Quantity::units($quantity);
            $group = $item->match['group'];
            $bf = $bf || ($key === 'BF' && $units > 0);
            if ($units > Quantity::MAX_UNITS - $totals[$group]) {
                $reasons[] = 'ROLLUP_OVERFLOW';

                continue;
            }
            $totals[$group] += $units;
        }
        $resolved = $reasons === [];

        return new ProductRollup($items, $resolved ? Quantity::decimal($totals['WINDOWS']) : null,
            $resolved ? Quantity::decimal($totals['DOORS']) : null, $bf, $resolved,
            $this->identity, array_values(array_unique($reasons)));
    }

    private function result(string $concept, string|int|float|bool|null $raw, ?string $lookup, C $classification,
        ?array $match = null, string|int|bool|null $value = null, array $reasons = [], array $suggestions = [],
        array $evidence = []): SemanticResult
    {
        return new SemanticResult($concept, $raw, $lookup, $classification,
            in_array($classification, [C::Confirmed, C::Ignored], true) ? R::Resolved : R::RequiresConfirmation,
            $this->identity, $match, $value, $suggestions, $reasons, $evidence);
    }
}
