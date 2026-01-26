<?php

namespace ProtegeId\DataObjects;

use ProtegeId\Enums\ProtegeIdVerificationStatus;

final class ProtegeIdVerificationResult
{
    public function __construct(
        public readonly string $userRef,
        public readonly ProtegeIdVerificationStatus $status,
        public readonly ?bool $ageVerified = null
    ) {}
}
