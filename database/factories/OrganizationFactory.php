<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 999999),
            'cnpj' => null,
            'logo_path' => null,
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the organization is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Attach a unique, Brazilian-format CNPJ to the organization.
     */
    public function withCnpj(): static
    {
        return $this->state(fn (array $attributes) => [
            'cnpj' => fake()->unique()->numerify('##.###.###/####-##'),
        ]);
    }
}
