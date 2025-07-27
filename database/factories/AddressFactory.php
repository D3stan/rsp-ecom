<?php

namespace Database\Factories;

use App\Models\User;
use Database\Seeders\SeederConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $location = SeederConfig::getRandomEuLocation();
        $type = $this->faker->randomElement(['shipping', 'billing']);
        
        return [
            'user_id' => User::factory(),
            'type' => $type,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'company' => $this->faker->boolean(30) ? $this->faker->company() : null,
            'address_line_1' => $this->faker->streetAddress(),
            'address_line_2' => $this->faker->boolean(20) ? $this->faker->secondaryAddress() : null,
            'city' => $location['city'],
            'state' => $this->getStateForCountry($location['country']),
            'postal_code' => $this->getPostalCodeForCountry($location['country']),
            'country' => $location['country'],
            'phone' => $this->getPhoneForCountry($location['country']),
            'is_default' => false, // Will be set manually for one address per type
        ];
    }

    /**
     * Get appropriate state/region for country
     */
    private function getStateForCountry(string $country): string
    {
        $states = [
            'Germany' => ['Bavaria', 'North Rhine-Westphalia', 'Baden-Württemberg', 'Lower Saxony', 'Hesse'],
            'France' => ['Île-de-France', 'Auvergne-Rhône-Alpes', 'Provence-Alpes-Côte d\'Azur', 'Occitanie', 'Nouvelle-Aquitaine'],
            'Italy' => ['Lombardy', 'Lazio', 'Campania', 'Sicily', 'Veneto'],
            'Spain' => ['Madrid', 'Catalonia', 'Valencia', 'Andalusia', 'Basque Country'],
            'Netherlands' => ['North Holland', 'South Holland', 'Utrecht', 'North Brabant', 'Gelderland'],
            'Belgium' => ['Brussels', 'Flanders', 'Wallonia'],
            'Austria' => ['Vienna', 'Upper Austria', 'Styria', 'Tyrol', 'Salzburg'],
            'Sweden' => ['Stockholm', 'Västra Götaland', 'Skåne', 'Uppsala', 'Värmland'],
            'Denmark' => ['Capital Region', 'Central Jutland', 'North Jutland', 'Zealand', 'Southern Denmark'],
            'Finland' => ['Uusimaa', 'Pirkanmaa', 'Northern Ostrobothnia', 'Southwest Finland', 'Central Finland'],
        ];

        $countryStates = $states[$country] ?? ['Region'];
        return $this->faker->randomElement($countryStates);
    }

    /**
     * Get realistic postal code for country
     */
    private function getPostalCodeForCountry(string $country): string
    {
        return match ($country) {
            'Germany' => $this->faker->numerify('#####'),
            'France' => $this->faker->numerify('#####'),
            'Italy' => $this->faker->numerify('#####'),
            'Spain' => $this->faker->numerify('#####'),
            'Netherlands' => $this->faker->numerify('#### ??'),
            'Belgium' => $this->faker->numerify('####'),
            'Austria' => $this->faker->numerify('####'),
            'Sweden' => $this->faker->numerify('### ##'),
            'Denmark' => $this->faker->numerify('####'),
            'Finland' => $this->faker->numerify('#####'),
            default => $this->faker->postcode(),
        };
    }

    /**
     * Get realistic phone number for country
     */
    private function getPhoneForCountry(string $country): string
    {
        return match ($country) {
            'Germany' => '+49 ' . $this->faker->numerify('### #######'),
            'France' => '+33 ' . $this->faker->numerify('# ## ## ## ##'),
            'Italy' => '+39 ' . $this->faker->numerify('### #######'),
            'Spain' => '+34 ' . $this->faker->numerify('### ### ###'),
            'Netherlands' => '+31 ' . $this->faker->numerify('# ########'),
            'Belgium' => '+32 ' . $this->faker->numerify('### ######'),
            'Austria' => '+43 ' . $this->faker->numerify('### #######'),
            'Sweden' => '+46 ' . $this->faker->numerify('## ### ####'),
            'Denmark' => '+45 ' . $this->faker->numerify('## ## ## ##'),
            'Finland' => '+358 ' . $this->faker->numerify('## #######'),
            default => $this->faker->phoneNumber(),
        };
    }

    /**
     * Create a shipping address
     */
    public function shipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'shipping',
        ]);
    }

    /**
     * Create a billing address
     */
    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'billing',
        ]);
    }

    /**
     * Create a default address
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
