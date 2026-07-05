<?php

use App\Services\Dna\Scores\Generators\ScalarScoresGenerator;
use App\Services\Dna\Scores\LocationLifestyleBridgeGenerator;
use App\Services\Dna\Scores\PetFriendlinessScoreService;
use App\Services\Dna\Scores\LockAndLeaveScoreService;
use App\Services\Dna\Scores\WaterfrontLifestyleScoreService;

return [

    /*
    |--------------------------------------------------------------------------
    | Production DNA score generation (Beyond-MLS Phase 13)
    |--------------------------------------------------------------------------
    |
    | Master gate for wiring dna_scores generation into the platform lifecycle.
    | Default OFF: all triggers (observers, ComputeLocationDna chain, bulk
    | command) are additive but inert until the owner enables this. Enabling
    | generation is independent of Matching V2, which stays disabled.
    |
    */

    'generation_enabled' => env('DNA_SCORES_GENERATION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Generators
    |--------------------------------------------------------------------------
    |
    | Ordered list of DnaScoreGenerator implementations the pipeline runs for a
    | listing. This is the ONLY place to register a new DNA score type — future
    | generators (Property DNA, Location Preference DNA, Audience DNA, Investment
    | DNA, Marketing DNA, Domain DNA, Behavioral DNA) plug in here with no change
    | to orchestration.
    |
    */

    'generators' => [
        ScalarScoresGenerator::class,
        LocationLifestyleBridgeGenerator::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scalar scores
    |--------------------------------------------------------------------------
    |
    | The SymmetricScoreService set that ScalarScoresGenerator persists. Adding a
    | new scalar score is a one-line edit here.
    |
    */

    'scalar_scores' => [
        PetFriendlinessScoreService::class,
        LockAndLeaveScoreService::class,
        WaterfrontLifestyleScoreService::class,
    ],

];
