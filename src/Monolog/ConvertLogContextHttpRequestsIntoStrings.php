<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Monolog;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Psr\Http\Message\RequestInterface;

use function array_map;

/** @internal */
final class ConvertLogContextHttpRequestsIntoStrings implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $record->message,
            array_map(static function ($item): mixed {
                if (! $item instanceof RequestInterface) {
                    return $item;
                }

                return $item->getMethod()
                    . ' '
                    . $item
                        ->getUri()
                        ->withUserInfo('')
                        ->__toString();
            }, $record->context),
            $record->extra,
            $record->formatted,
        );
    }
}
