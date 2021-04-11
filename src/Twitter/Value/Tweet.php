<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Twitter\Value;

use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;

use function str_replace;

/** @psalm-immutable */
final class Tweet
{
    /** @psalm-var non-empty-string */
    private const TEMPLATE = 'Released: {repository} {version} {link}';
    /** @psalm-var non-empty-string */
    private string $content;

    /**
     * @psalm-param non-empty-string $content
     */
    private function __construct(string $content)
    {
        $this->content = $content;
    }

    public function content(): string
    {
        return $this->content;
    }

    /** @psalm-pure */
    public static function fromMilestoneClosedEvent(MilestoneClosedEvent $event): self
    {
        $repository = $event->repository()->owner() . '/' . $event->repository()->name();
        $version    = $event->version()->fullReleaseName();
        /** @psalm-var non-empty-string $content*/
        $content = str_replace([
            '{repository}',
            '{version}',
            '{link}',
        ], [
            $repository,
            $version,
            'https://github.com/laminas/laminas-navigation/releases/tag/' . $version,
        ], self::TEMPLATE);

        return new self($content);
    }
}
