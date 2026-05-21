<?php

namespace App\Enums;

enum RelationType: string
{
    case DependsOn = 'depends_on';
    case RequiredBy = 'required_by';
    case RelatedTo = 'related_to';
    case AlternativeTo = 'alternative_to';
    case SimilarTo = 'similar_to';
}
