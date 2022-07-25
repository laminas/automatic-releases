<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Gpg;

use Psl;
use Psl\Env;
use Psl\Filesystem;
use Psl\Regex;
use Psl\Shell;

use function Psl\File\write;

final class ImportGpgKeyFromStringViaTemporaryFile implements ImportGpgKeyFromString
{
    public function __invoke(string $keyContents): SecretKeyId
    {
        $keyFileName = Filesystem\create_temporary_file(Env\temp_dir(), 'imported-key');
        write($keyFileName, $keyContents);

        $output = Shell\execute('gpg', ['--import', $keyFileName], null, [], Shell\ErrorOutputBehavior::Append);

        $matches = Regex\first_match($output, '/key\\s+([A-F0-9]+):\\s+secret\\s+key\\s+imported/im', Regex\capture_groups([1]));

        Psl\invariant($matches !== null, 'unexpected output.');

        Filesystem\delete_file($keyFileName);

        return SecretKeyId::fromBase16String($matches[1]);
    }
}
