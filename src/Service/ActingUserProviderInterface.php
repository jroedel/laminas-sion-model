<?php

namespace SionModel\Service;

/**
 * Resolves the id of the user performing the current request, at call time.
 *
 * Consult this at write time, never during construction: resolving identity
 * while the container is mid-construction is what produced the
 * UserTable/ProblemService/ProblemTable/AuthService dependency cycle, and an
 * id captured at construction goes stale the moment a user logs in
 * mid-request (magic-link redemption).
 */
interface ActingUserProviderInterface
{
    public function getActingUserId(): ?int;
}
