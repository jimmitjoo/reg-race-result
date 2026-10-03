<?php

namespace App\Timing;

enum ResultStatus: string
{
    case Finished = 'finished';
    case Missing = 'missing';
    case Dns = 'dns';
}
