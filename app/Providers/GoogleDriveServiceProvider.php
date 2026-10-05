<?php

namespace App\Providers;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;

/*
 * Registers the 'google' filesystem driver, which only config/backup.php uses.
 *
 * masbug/flysystem-google-drive-ext comes from the inline repository in
 * composer.json rather than Packagist. Upstream still requires
 * guzzlehttp/guzzle ^6.3|^7.0 and this app is on Guzzle 8, but the adapter
 * imports four GuzzleHttp\Psr7 classes and never the HTTP client, so that
 * requirement blocked nothing real — the inline definition drops it and
 * downloads the same upstream zip. google/apiclient has allowed Guzzle 8
 * since 2.20, so nothing else needed widening. Taking the Packagist version
 * instead would downgrade Guzzle for the whole app, the Paddle calls included.
 */
class GoogleDriveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Storage::extend('google', function ($app, $config) {
            $client = new Client;
            $client->setClientId($config['clientId']);
            $client->setClientSecret($config['clientSecret']);
            $client->refreshToken($config['refreshToken']);

            $service = new Drive($client);

            /*
             * Display paths stay on (the adapter's default), so the folder
             * is a path of names like "SpanishClassPrintables/backups". With
             * them off, every path segment is read as a Drive ID, and the
             * upload fails with nothing but "Not able to write the file".
             * The cost is a Drive call from the adapter's constructor, which
             * only runs when a backup command resolves this disk.
             */
            $adapter = new GoogleDriveAdapter($service, $config['folder']);

            $driver = new Filesystem($adapter);

            return new FilesystemAdapter($driver, $adapter);
        });
    }
}
