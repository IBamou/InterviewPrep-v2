<?php

return [

    'xp_per_rating' => [
        0 => 2,
        1 => 3,
        2 => 5,
        3 => 10,
        4 => 15,
        5 => 25,
    ],

    'tiers' => [
        'junior' => [
            'xp_threshold' => 0,
            'minimum_sessions' => 0,
            'minimum_avg_rating' => 0,
        ],
        'mid' => [
            'xp_threshold' => env('GAMIFICATION_MID_XP', 300),
            'minimum_sessions' => env('GAMIFICATION_MID_SESSIONS', 3),
            'minimum_avg_rating' => env('GAMIFICATION_MID_RATING', 2.5),
        ],
        'senior' => [
            'xp_threshold' => env('GAMIFICATION_SENIOR_XP', 1000),
            'minimum_sessions' => env('GAMIFICATION_SENIOR_SESSIONS', 10),
            'minimum_avg_rating' => env('GAMIFICATION_SENIOR_RATING', 3.5),
        ],
    ],

    'bonus' => [
        'first_practice_of_day' => env('GAMIFICATION_BONUS_FIRST', 15),
        'perfect_set' => env('GAMIFICATION_BONUS_PERFECT', 25),
        'first_explanation' => env('GAMIFICATION_BONUS_EXPLANATION', 10),
    ],

    'mastery' => [
        'recent_session_weight' => 2,
        'recent_session_count' => 2,
        'standard_weight' => 1,
        'old_weight' => 0.5,
    ],

    'auto_status' => [
        'to_review' => [
            'to_in_progress' => [
                'min_sessions' => 1,
            ],
        ],
        'in_progress' => [
            'to_mastered' => [
                'min_junior_xp' => env('GAMIFICATION_MASTER_XP', 300),
                'min_avg_rating' => env('GAMIFICATION_MASTER_RATING', 3.5),
            ],
        ],
    ],

];
