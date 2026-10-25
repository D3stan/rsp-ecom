<?php

use App\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('public');

    $this->tempFiles = [];

    /** A real temp file (UploadedFile::fake() reports its mime type from the file NAME, which would hide the bug). */
    $this->makeUpload = function (string $clientName, string $content): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'desc');
        $this->tempFiles[] = $path;
        file_put_contents($path, $content);

        return new UploadedFile($path, $clientName, null, null, true);
    };
});

afterEach(function () {
    array_map('unlink', array_filter($this->tempFiles, 'file_exists'));
});

it('stores a description image under the description folder and returns a URL below the base URL', function () {
    $service = app(ImageUploadService::class);

    $url = $service->uploadDescriptionImage(UploadedFile::fake()->image('photo.jpg'));

    expect($url)->toStartWith('/storage/products/descriptions/')
        ->and($url)->toStartWith($service->descriptionImageBaseUrl())
        ->and(parse_url($url, PHP_URL_HOST))->toBeNull();
    $files = Storage::disk('public')->files('products/descriptions');
    expect($files)->toHaveCount(1)
        ->and($url)->toEndWith(basename($files[0]));
});

it('names the stored file after its real content, not the client file name', function () {
    $file = ($this->makeUpload)('evil.php', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    $url = app(ImageUploadService::class)->uploadDescriptionImage($file);

    expect($url)->toEndWith('.png')->not->toContain('.php');
});

it('refuses content that is not a supported image, whatever its name says', function () {
    $file = ($this->makeUpload)('photo.png', '<?php echo 1;');

    app(ImageUploadService::class)->uploadDescriptionImage($file);
})->throws(InvalidArgumentException::class);
