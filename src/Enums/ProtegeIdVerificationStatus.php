<?php

namespace ProtegeId\Enums;

enum ProtegeIdVerificationStatus: string
{
    case PENDING = 'pending';
    case AWAITING_CLIENT = 'awaiting_client';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
    case TIMEOUT = 'timeout';
    case CANCELED = 'canceled';
}
