<?php

use App\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** A 1x1 transparent PNG. */
const DESCRIPTION_TEST_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

beforeEach(function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('public');
});

it('stores a description image under the description folder and returns a URL below the base URL', function () {
    $service = app(ImageUploadService::class);

    $url = $service->uploadDescriptionImage(UploadedFile::fake()->image('photo.jpg'));

    expect($url)->toStartWith($service->descriptionImageBaseUrl());
    $files = Storage::disk('public')->files('products/descriptions');
    expect($files)->toHaveCount(1)
        ->and($url)->toEndWith(basename($files[0]));
});

/** A real temp file (UploadedFile::fake() reports its mime type from the file NAME, which would hide the bug). */
function descriptionUpload(string $clientName, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'desc');
    file_put_contents($path, $content);

    return new UploadedFile($path, $clientName, null, null, true);
}

it('names the stored file after its real content, not the client file name', function () {
    $file = descriptionUpload('evil.php', base64_decode(DESCRIPTION_TEST_PNG));

    $url = app(ImageUploadService::class)->uploadDescriptionImage($file);

    expect($url)->toEndWith('.png')->not->toContain('.php');
});

it('refuses content that is not a supported image, whatever its name says', function () {
    $file = descriptionUpload('photo.png', '<?php echo 1;');

    app(ImageUploadService::class)->uploadDescriptionImage($file);
})->throws(InvalidArgumentException::class);
