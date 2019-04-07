<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Api\GraphQL;

interface RunQuery
{
    /**
     * @param string               $query     a GraphQL query
     * @param array<string, mixed> $variables
     *
     * @return mixed[]
     */
    function __invoke(
        string $query,
        array $variables = []
    ) : array;
}
