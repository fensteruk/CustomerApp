<?php

namespace Tests\Support;

use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Knowledge\Actions\ActivateProfile;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\Actions\RegisterContext;
use App\SourceImport\Knowledge\Actions\SaveProfileDraft;
use App\SourceImport\Knowledge\AnalysisSnapshot;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\Models\KnowledgeProfile;
use App\SourceImport\Semantics\Data\ObservedCell;
use App\Wald\Contracts\CellObservation;
use App\Wald\Services\HeaderDetector;
use App\Wald\Services\RegionDetector;
use App\Wald\Services\SheetProfiler;
use App\Wald\Services\ValueProfiler;
use App\Wald\Services\WorkbookProfiler;
use App\Wald\Services\WorkbookSourceFactory;
use Illuminate\Support\Str;

final class Wald04Fixtures
{
    public static function snapshot(array $headers = ['Plot', 'Call Type', 'VS'], int $offset = 0, ?string $rawCall = null): AnalysisSnapshot
    {
        $rows = [1 => array_combine(range(1, count($headers)), $headers)];
        foreach (range(2, 8) as $row) {
            foreach ($headers as $i => $header) {
                $rows[$row][$i + 1] = match ($header) {
                    'Call Type' => $rawCall ?? 'PC1', 'VS', 'BF' => $row + 0.25,
                    'Plot', 'House No.', 'Sales Plot' => sprintf('%03d', $row), default => 'ABC',
                };
            }
        }
        $values = new ValueProfiler;
        $profiler = new WorkbookProfiler(new WorkbookSourceFactory, new SheetProfiler(new RegionDetector($values), new HeaderDetector($values), $values));
        $profile = $profiler->profile(WaldFixtures::xlsx([['name' => 'Data', 'rows' => WaldFixtures::move($rows, $offset)]]), 'xlsx');
        $observations = [];
        if ($rawCall !== null) {
            $p = $profile->toArray();
            $observations[] = new ObservedCell($p['source_checksum'], $p['sheets'][0]['id'],
                new CellObservation(2 + $offset, array_search('Call Type', $headers, true) + 1, $rawCall));
        }

        return AnalysisSnapshot::fromProfile($profile, $observations);
    }

    public static function owner(): array
    {
        $org = CustomerOrganisation::factory()->create(['name' => 'Wald QA '.self::command()]);
        $site = Site::factory()->create(['customer_organisation_id' => $org->id]);
        $office = User::factory()->create(['customer_organisation_id' => null, 'email' => self::command().'@example.test',
            'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
            'is_active' => true, 'is_preview_user' => false]);

        return [$office, new KnowledgeScope($org->id, $site->id, 'synthetic', 'family-v1')];
    }

    public static function command(): string
    {
        return (string) Str::uuid();
    }

    public static function draft(User $office, KnowledgeScope $scope): array
    {
        $context = (new RegisterContext)->handle($office, $scope, self::snapshot(), self::command());
        $question = collect((new KnowledgeQueries)->questions($office, $scope, $context->uuid))->firstWhere('key', 'structure:plot_reference');
        $answer = (new AnswerClarification)->handle($office, $scope, $context->uuid, $question['uuid'], 0,
            $question['evidence']['candidates'][0]['id'], 'Reviewed synthetic header.', self::command());
        $version = (new SaveProfileDraft)->handle($office, $scope, $context->uuid, $answer->uuid, self::command());
        $profile = KnowledgeProfile::query()->findOrFail($version->profile_id);

        return [$context, $question, $answer, $version, $profile];
    }

    public static function active(User $office, KnowledgeScope $scope): array
    {
        [$context, $question, $answer, $version, $profile] = self::draft($office, $scope);
        $profile = (new ActivateProfile)->handle($office, $scope, $profile->uuid, $version->version,
            $version->definition_hash, $profile->lock_version, 'Explicit activation.', self::command());

        return [$context, $question, $answer, $version, $profile];
    }
}
