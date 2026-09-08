<?php

use App\Models\CallOffRequest;
use App\SourceImport\Knowledge\Actions\RegisterContext;
use App\SourceImport\Knowledge\Actions\UseProfile;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\Models\KnowledgeEvent;
use App\SourceImport\Knowledge\ProfileState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\WaldFixtures;

uses(RefreshDatabase::class);
afterEach(fn () => WaldFixtures::cleanup());

it('keeps answer draft and activation separately audited without Portal writes', function () {
    [$office, $scope] = F::owner();
    [$context, , , $version, $profile] = F::draft($office, $scope);
    expect($profile->state)->toBe(ProfileState::Draft)->and($profile->active_version)->toBeNull()
        ->and(KnowledgeEvent::query()->pluck('action')->all())->toBe(['register', 'answer', 'draft'])
        ->and(CallOffRequest::query()->count())->toBe(0);
    expect($version->toArray())->toBe(['uuid' => $version->uuid]);
});

it('reuses an exact scoped activated profile on fresh evidence', function () {
    [$office, $scope] = F::owner();
    [, , , , $profile] = F::active($office, $scope);
    $context = (new RegisterContext)->handle($office, $scope, F::snapshot(), F::command());
    $receipt = (new UseProfile)->handle($office, $scope, $context->uuid, $profile->uuid, $profile->lock_version, F::command());
    expect($receipt->reason)->toBe('EXACT_REVIEWED_STRUCTURE')->and($receipt->applied)->toBeTrue()
        ->and((new KnowledgeQueries)->receiptEligible($office, $scope, $receipt->uuid))->toBeTrue();
});

it('moves absolute source coordinates without changing a reusable descriptor', function () {
    $a = F::snapshot()->data;
    $b = F::snapshot(offset: 4)->data;
    expect(array_values($a['tables'])[0]['descriptor'])->toBe(array_values($b['tables'])[0]['descriptor']);
});
