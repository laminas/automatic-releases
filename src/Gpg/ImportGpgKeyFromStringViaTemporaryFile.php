<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Gpg;

use function Psl\Env\temp_dir;
use function Psl\Filesystem\create_temporary_file;
use function Psl\Filesystem\delete_file;
use function Psl\Filesystem\write_file;
use function Psl\invariant;
use function Psl\Regex\capture_groups;
use function Psl\Regex\first_match;
use function Psl\Regex\matches;
use function Psl\Shell\escape_argument;
use function Psl\Shell\execute;
use function Psl\Str\is_empty;

final class ImportGpgKeyFromStringViaTemporaryFile implements ImportGpgKeyFromString
{
    public function __invoke(string $keyContents): SecretKeyId
    {
        $keyFileName = create_temporary_file(temp_dir(), 'imported-key');
        write_file($keyFileName, $keyContents);

        // redirect output from STDERR to STDOUT since Shell\execute only returns STDOUT content.
        $output = execute(
            'gpg',
            ['--import', escape_argument($keyFileName), '2>&1'],
            null,
            [],
            false
        );

        $matches = first_match(
            $output,
            '#key\s+([A-F0-9]+):\s+secret\s+key\s+imported#im',
            capture_groups([1])
        );

        invariant($matches !== null, 'unexpected output.');

        delete_file($keyFileName);

        $secretKeyId = $matches[1];

        invariant(! is_empty($secretKeyId), 'Expected a non-empty key id.');

        invariant(matches($secretKeyId, '/^[A-F0-9]+$/i'), 'Key id is malformed.');

        return SecretKeyId::fromBase16String($secretKeyId);
    }
}
