<?php

namespace App\Enums;

enum ConceptStatus: string
{
    case ToReview = 'to_review';
    case InProgress = 'in_progress';
    case Mastered = 'mastered';
}
