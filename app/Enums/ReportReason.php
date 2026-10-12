<?php

namespace App\Enums;

/** MOD-02 report reasons. Labels: lang/en/reports.php. */
enum ReportReason: string
{
    case Scam = 'scam';
    case Prohibited = 'prohibited';
    case WrongCategory = 'wrong_category';
    case Duplicate = 'duplicate';
    case Offensive = 'offensive';
    case Sold = 'sold';
    case Other = 'other';
}
