<?php

namespace JaOcero\ConversationalForms\Support\Assets;

/**
 * Filament versions asset URLs by the installed package version, which never
 * changes for a development checkout (for example a Composer path repository),
 * so browsers kept serving stale scripts after a rebuild. Versioning by the
 * file's contents busts the cache exactly when the file changes.
 */
trait VersionsByContent
{
    protected ?string $contentVersion = null;

    public function getVersion(): string
    {
        $path = $this->getPath();

        if (blank($path) || $this->isRemote() || (! is_file($path))) {
            return parent::getVersion();
        }

        return $this->contentVersion ??= hash_file('xxh128', $path);
    }
}
