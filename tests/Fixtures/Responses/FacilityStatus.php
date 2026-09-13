<?php

declare(strict_types=1);

namespace Tests\Fixtures\Responses;

enum FacilityStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
