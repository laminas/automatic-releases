<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Laminas\AutomaticReleases\Twitter\CreateTweetThroughApiCall;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TweetReleaseCommand extends Command
{
    private LoadCurrentGithubEvent $loadEvent;
    private CreateTweetThroughApiCall $createTweet;

    public function __construct(
        LoadCurrentGithubEvent $loadEvent,
        CreateTweetThroughApiCall $createTweet
    ) {
        parent::__construct('laminas:automatic-releases:tweet-release');

        $this->loadEvent   = $loadEvent;
        $this->createTweet = $createTweet;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        ($this->createTweet)(($this->loadEvent)());

        return 0;
    }
}
