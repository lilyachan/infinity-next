<?php

namespace App\Observers;

use App\FileStorage;
use Storage;
/**
 * File Storage observer.
 *
 * @category   Observers
 *
 * @author     Joshua Moon <josh@jaw.sh>
 * @copyright  2016 Infinity Next Development Group
 * @license    http://www.gnu.org/licenses/agpl-3.0.en.html AGPL3
 *
 * @since      0.6.0
 */
class FileObserver
{
    /**
     * Handles model after create (non-existant save).
     *
     * @param  \App\FileStorage  $file
     *
     * @return bool
     */
    public function created(FileStorage $file)
    {
        return true;
    }

    /**
     * Checks if this model is allowed to create (non-existant save).
     *
     * @param  \App\FileStorage  $file
     *
     * @return bool
     */
    public function creating(FileStorage $file)
    {
        $file->putFile();

        $file->filesize = Storage::size($file->getPath());
        $file->first_uploaded_at = now();
        $file->last_uploaded_at = now();
        $file->upload_count = $file->upload_count ?? 1;

        return Storage::exists($file->getPath());
    }

    /**
     * Handles model after delete (pre-existing hard or soft deletion).
     *
     * @param  \App\FileStorage  $file
     *
     * @return bool
     */
    public function deleted($file)
    {
        $file->deleteFile();

        return true;
    }

    /**
     * Checks if this model is allowed to delete (pre-existing deletion).
     *
     * @param  \App\FileStorage  $file
     *
     * @return bool
     */
    public function deleting($file)
    {
        return true;
    }

    /**
     * Handles model after save (pre-existing or non-existant save).
     *
     * @param  \App\FileStorage  $file
     *
     * @return bool
     */
    public function saved(FileStorage $file)
    {
        return true;
    }

    /**
     * Checks if this model is allowed to save (pre-existing or non-existant save).
     *
     * @param  \App\FileStorage  $file
     *
     * @return bool
     */
    public function saving(FileStorage $file)
    {
        return true;
    }

    /**
     * Handles model after update (pre-existing save).
     *
     * @param  \App\FileStorage  $file
     *
     * @return bool
     */
    public function updated(FileStorage $file)
    {
        return true;
    }

    /**
     * Checks if this model is allowed to update (pre-existing save).
     *
     * @param  \App\FileStorage  $file
     *
     * @return bool
     */
    public function updating(FileStorage $file)
    {
        return true;
    }
}
