<?php

declare(strict_types=1);

use App\Domains\Api\Actions\PersonalAccessTokens\CreatePersonalAccessToken;
use App\Domains\Api\Enums\TokenExpiration;
use Northwestern\SysDev\Chassis\Testing\Browser\FilamentPages;

/*
 * Every app panel page fits each width, with values long enough to wrap.
 *
 * An administrator sees every page, so all of them are measured.
 */

it('fits every app panel page and its tables at each width', function () {
    $user = $this->signInAsSuperAdministrator();
    $user->forceFill([
        'job_titles' => ['Associate Director of Strategic Enrollment Communications and Outreach'],
        'departments' => ['Feinberg School of Medicine Department of Preventive Medicine'],
    ])->save();
    resolve(CreatePersonalAccessToken::class)($user, 'Weekly enrollment report for the dean of the graduate school', [], TokenExpiration::OneMonth);

    expect(FilamentPages::in('app'))->toAllFitEachWidth();
});
