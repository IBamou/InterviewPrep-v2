<?php

namespace App\Enums;

enum ResourceType: string
{
    case Article = 'article';
    case Video = 'video';
    case Documentation = 'documentation';
    case Course = 'course';
    case Book = 'book';
    case Tool = 'tool';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Article => 'Article',
            self::Video => 'Video',
            self::Documentation => 'Documentation',
            self::Course => 'Course',
            self::Book => 'Book',
            self::Tool => 'Tool',
            self::Other => 'Other',
        };
    }
}
