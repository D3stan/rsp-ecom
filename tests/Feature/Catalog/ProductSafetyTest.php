<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

/*
 * GPSR (Regulation (EU) 2023/988): the product page shows the manufacturer (name, postal and electronic address), the
 * EU responsible person when there is one, a product identifier and the product's safety information.
 */

beforeEach(fn () => Queue::fake());

function safetyProduct(array $attributes = []): Product
{
    $product = CatalogFixtures::product(array_merge(['base_sku' => 'FAR-AE'], $attributes));
    $product->defaultVariant->update(['images' => [], 'sku_suffix' => '-BLK', 'stock_quantity' => 3]);

    return $product->fresh();
}

/** The admin edit-form payload (Admin/Products/Edit.tsx) for a product with its default variant. */
function safetyEditPayload(Product $product, array $extra = []): array
{
    $variant = $product->defaultVariant;

    return array_merge([
        'name' => $product->name,
        'category_id' => (string) $product->category_id,
        'size_id' => (string) $product->size_id,
        'status' => 'active',
        'featured' => '0',
        'base_sku' => $product->base_sku,
        'variants' => json_encode([[
            'id' => $variant->id,
            'name' => $variant->name,
            'sku_suffix' => $variant->sku_suffix,
            'price' => (string) $variant->price,
            'stock_quantity' => (string) $variant->stock_quantity,
            'stock_quantity_original' => $variant->stock_quantity,
            'description' => '',
            'is_default' => true,
            'sort_order' => 0,
            'existing_images' => [],
        ]]),
    ], $extra);
}

it('shows the store identity as manufacturer while the compliance settings are empty', function () {
    settings()->set('store.legal_name', 'Moto Luci Srl');
    settings()->set('store.address', "Via Roma 1\n20100 Milano MI\nItalia");
    settings()->set('contact.support_email', 'support@motoluci.test');
    $product = safetyProduct(['safety_information' => 'Installation by a qualified mechanic only.']);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('product')
            ->where('compliance.manufacturer_name', 'Moto Luci Srl')
            ->where('compliance.manufacturer_address', "Via Roma 1\n20100 Milano MI\nItalia")
            ->where('compliance.manufacturer_email', 'support@motoluci.test')
            ->where('compliance.eu_responsible_person', null)
            ->where('product.safety_information', 'Installation by a qualified mechanic only.')
            ->where('product.base_sku', 'FAR-AE')
            ->where('product.variants.0.sku', 'FAR-AE-BLK'));
});

it('shows the compliance settings when they are filled in', function () {
    settings()->set('store.legal_name', 'Moto Luci Srl');
    settings()->set('compliance.manufacturer_name', 'Shenzhen Bright Lamps Co., Ltd.');
    settings()->set('compliance.manufacturer_address', 'No. 8 Keji Road, Shenzhen, China');
    settings()->set('compliance.manufacturer_email', 'info@brightlamps.test');
    settings()->set('compliance.eu_responsible_person', "Moto Luci Srl\nVia Roma 1, 20100 Milano, Italia\nsafety@motoluci.test");
    $product = safetyProduct();

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('compliance.manufacturer_name', 'Shenzhen Bright Lamps Co., Ltd.')
            ->where('compliance.manufacturer_address', 'No. 8 Keji Road, Shenzhen, China')
            ->where('compliance.manufacturer_email', 'info@brightlamps.test')
            ->where('compliance.eu_responsible_person', "Moto Luci Srl\nVia Roma 1, 20100 Milano, Italia\nsafety@motoluci.test")
            ->where('product.safety_information', null));
});

it('saves the safety information from the admin edit form', function () {
    $product = safetyProduct();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), safetyEditPayload($product, ['safety_information' => 'Do not look into the beam.']))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->safety_information)->toBe('Do not look into the beam.');

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), safetyEditPayload($product, ['safety_information' => '']))
        ->assertSessionHasNoErrors();

    expect($product->fresh()->safety_information)->toBeNull();
});

it('saves the safety information when the admin creates a product', function () {
    $template = safetyProduct();

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('admin.products.store'), [
            'name' => 'Brand new headlight',
            'category_id' => $template->category_id,
            'size_id' => $template->size_id,
            'status' => 'active',
            'featured' => false,
            'base_sku' => '', // the create form always sends it (store() reads it without a default)
            'safety_information' => 'Check the beam alignment after fitting.',
            'variants' => [[
                'name' => 'Standard',
                'price' => 59,
                'stock_quantity' => 2,
                'is_default' => true,
                'sort_order' => 0,
            ]],
        ])
        ->assertSessionHasNoErrors();

    expect(Product::where('name', 'Brand new headlight')->value('safety_information'))->toBe('Check the beam alignment after fitting.');
});

it('limits the safety information to 5000 characters', function () {
    $product = safetyProduct();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), safetyEditPayload($product, ['safety_information' => str_repeat('a', 5001)]))
        ->assertSessionHasErrors('safety_information');

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), safetyEditPayload($product, ['safety_information' => str_repeat('a', 5000)]))
        ->assertSessionHasNoErrors();
});

it('has the compliance settings group with labels in every locale', function () {
    $keys = ['compliance.manufacturer_name', 'compliance.manufacturer_address', 'compliance.manufacturer_email', 'compliance.eu_responsible_person'];

    foreach ($keys as $key) {
        expect(settings($key))->toBe('');
    }

    foreach (['en', 'it', 'de', 'fr', 'es'] as $locale) {
        $strings = json_decode(file_get_contents(lang_path("{$locale}.json")), true);

        expect($strings['settings.group.compliance.label'] ?? '')->not->toBe('', "{$locale} group label");

        foreach ($keys as $key) {
            expect($strings["settings.{$key}.label"] ?? '')->not->toBe('', "{$locale} {$key} label")
                ->and($strings["settings.{$key}.help"] ?? '')->not->toBe('', "{$locale} {$key} help");
        }

        foreach (['title', 'manufacturer', 'eu_responsible_person', 'identifier', 'information'] as $key) {
            expect($strings['product_safety'][$key] ?? '')->not->toBe('', "{$locale} product_safety.{$key}");
        }
    }

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.settings'))
        ->assertInertia(fn (Assert $page) => $page->where('groups', fn ($groups) => collect($groups)->contains('key', 'compliance')));
});
