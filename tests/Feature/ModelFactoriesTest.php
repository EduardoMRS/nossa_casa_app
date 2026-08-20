<?php

use App\Models\AiQuery;
use App\Models\Church;
use App\Models\ChurchRegistrationRequest;
use App\Models\Community;
use App\Models\LiveStream;
use App\Models\Recording;

test('model factories create valid records and relationships', function () {
    $community = Community::factory()->create();
    $church = Church::factory()->create(['community_id' => $community->id]);
    $registrationRequest = ChurchRegistrationRequest::factory()->create();
    $liveStream = LiveStream::factory()->create(['church_id' => $church->id]);
    $recording = Recording::factory()->create(['live_stream_id' => $liveStream->id]);
    $aiQuery = AiQuery::factory()->create(['church_id' => $church->id]);

    expect($community->owner)->not->toBeNull()
        ->and($church->community->is($community))->toBeTrue()
        ->and($registrationRequest->requester)->not->toBeNull()
        ->and($registrationRequest->community)->not->toBeNull()
        ->and($liveStream->church->is($church))->toBeTrue()
        ->and($recording->liveStream->is($liveStream))->toBeTrue()
        ->and($aiQuery->church->is($church))->toBeTrue();
});
