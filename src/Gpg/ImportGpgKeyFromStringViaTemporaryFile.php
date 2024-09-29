<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Gpg;

use Laminas\AutomaticReleases\Gpg\Value\ColonFormattedKeyRecord;
use Psl;
use Psl\Env;
use Psl\Filesystem;
use Psl\Shell;
use Psl\Str;
use Psl\Vec;

use function array_shift;
use function count;
use function Psl\File\write;

final class ImportGpgKeyFromStringViaTemporaryFile implements ImportGpgKeyFromString
{
    public function __invoke(string $keyContents): SecretKeyId
    {
        $keyFileName = Filesystem\create_temporary_file(Env\temp_dir(), 'imported-key');
        try {
            write($keyFileName, $keyContents);

            $output = Shell\execute(
                'gpg',
                ['--import', '--import-options', 'import-show', '--with-colons', $keyFileName],
                null,
                [],
                Shell\ErrorOutputBehavior::Discard,
            );

            $keyRecords = Vec\filter_nulls(Vec\map(
                Str\split($output, "\n"),
                static fn (string $record): ColonFormattedKeyRecord|null => ColonFormattedKeyRecord::fromRecordLine(
                    $record,
                ),
            ));

            // Primary key secret is exported as unusable gnu-stub secret with --export-secret-subkeys.
            // Consequently primary key secret is always present even when signing is done by subkey with actual secret.
            $primaryKeyRecords = Vec\filter(
                $keyRecords,
                static fn (ColonFormattedKeyRecord $record): bool => $record->isPrimaryKey() && $record->isSecretKey(),
            );

            Psl\invariant(count($primaryKeyRecords) > 0, 'Imported GPG key material does not contain secret key');
            // import can contain multiple keys. Sanity check to ensure no unexpected key usage.
            Psl\invariant(
                count($primaryKeyRecords) === 1,
                'Imported GPG key material contains more than one primary key',
            );

            $primaryKeyRecord = array_shift($primaryKeyRecords);

            return $primaryKeyRecord->keyId();
        } finally {
            Filesystem\delete_file($keyFileName);
        }
    }
}
