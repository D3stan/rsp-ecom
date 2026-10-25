<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('public');
    $this->tempFiles = [];
});

afterEach(function () {
    foreach ($this->tempFiles as $path) {
        @unlink($path);
    }
});

it('renders a markdown preview for an admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson(route('admin.products.markdown-preview'), ['markdown' => '**Bold**'])
        ->assertOk()
        ->assertJson(['html' => '<p><strong>Bold</strong></p>']);
});

it('rejects a preview text over the description limit', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson(route('admin.products.markdown-preview'), ['markdown' => str_repeat('a', 10001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('markdown');
});

it('uploads a description image for an admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.products.description-images'), ['image' => UploadedFile::fake()->image('photo.png')])
        ->assertOk();

    expect($response->json('url'))->toContain('products/descriptions/');
    expect(Storage::disk('public')->files('products/descriptions'))->toHaveCount(1);
});

it('rejects files that are not images', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson(route('admin.products.description-images'), ['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');
});

it('never stores a script disguised as a png', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $path = tempnam(sys_get_temp_dir(), 'desc');
    $this->tempFiles[] = $path;
    file_put_contents($path, '<?php echo "pwned";');
    $disguised = new UploadedFile($path, 'x.png', 'image/png', null, true);

    $this->actingAs($admin)
        ->postJson(route('admin.products.description-images'), ['image' => $disguised])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    expect(Storage::disk('public')->allFiles('products/descriptions'))->toBeEmpty();
});

it('keeps both endpoints away from guests and customers', function () {
    $payload = ['markdown' => 'x'];

    $this->postJson(route('admin.products.markdown-preview'), $payload)->assertUnauthorized();
    $this->postJson(route('admin.products.description-images'), ['image' => UploadedFile::fake()->image('a.png')])->assertUnauthorized();
    $this->actingAs(User::factory()->create())
        ->postJson(route('admin.products.markdown-preview'), $payload)->assertForbidden();
    $this->actingAs(User::factory()->create())
        ->postJson(route('admin.products.description-images'), ['image' => UploadedFile::fake()->image('a.png')])
        ->assertForbidden();
});
