<?php

namespace ProtegeId\DataObjects;

final class ProtegeIdSession
{
    public function __construct(
        public string $sessionId,
        public string $temporaryUrl,
        public string $userRef,
        public ?string $expiresAt = null
    ) {}
}
