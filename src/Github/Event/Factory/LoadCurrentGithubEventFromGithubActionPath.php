<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Event\Factory;

use Doctrine\AutomaticReleases\Environment\Variables;
use Doctrine\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Webmozart\Assert\Assert;
use function Safe\file_get_contents;

final class LoadCurrentGithubEventFromGithubActionPath implements LoadCurrentGithubEvent
{
    private Variables $variables;

    public function __construct(Variables $variables)
    {
        $this->variables = $variables;
    }

    public function __invoke() : MilestoneClosedEvent
    {
        $path = $this->variables->githubEventPath();

        Assert::fileExists($path);

        return MilestoneClosedEvent::fromEventJson(file_get_contents($path));
    }
}
