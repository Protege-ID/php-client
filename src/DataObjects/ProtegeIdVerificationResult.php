<?php

namespace ProtegeId\DataObjects;

final class ProtegeIdVerificationResult
{
    public function __construct(
        public string $userRef,
        public string $status,
        public ?bool $ageVerified = null
    ) {}
}
