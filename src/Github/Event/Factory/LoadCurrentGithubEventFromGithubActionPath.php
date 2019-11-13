<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Event;

use Doctrine\AutomaticReleases\Environment\Variables;
use Webmozart\Assert\Assert;
use function Safe\file_get_contents;

/** @TODO TEST ME */
final class LoadCurrentGithubEventFromGithubActionPath implements LoadCurrentGithubEvent
{
    /** @var Variables */
    private $variables;

    public function __construct(Variables $variables)
    {
        $this->variables = $variables;
    }

    public function __invoke() : ?MilestoneClosedEvent
    {
        $path = $this->variables->githubEventPath();

        Assert::fileExists($path);

        // @TODO check event type here

        return MilestoneClosedEvent::fromEventJson(file_get_contents($path));
    }
}
