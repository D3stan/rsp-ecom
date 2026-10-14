<?php

namespace App\Http\Requests\Admin;

use App\Settings\SettingDefinition;
use App\Settings\Settings;
use App\Settings\SettingsRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * PATCH admin/settings/{group}. The body holds the group's fields by their short name (the part of the key after
 * the group: `rate` for tax.rate), only the fields to change; read-only fields and unknown names are ignored.
 * Rules are generated from the catalogue; min <= max style rules are checked after the field rules.
 */
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-settings');
    }

    /**
     * @return array<string, SettingDefinition> short name => definition (writable fields of the group)
     */
    public function fields(): array
    {
        $registry = app(SettingsRegistry::class);
        $group = (string) $this->route('group');

        abort_unless($registry->hasGroup($group) && ! $registry->isPanelGroup($group), 404);

        $fields = [];
        foreach ($registry->group($group) as $definition) {
            if ($definition->isWritable()) {
                $fields[$definition->name] = $definition;
            }
        }

        return $fields;
    }

    protected function prepareForValidation(): void
    {
        $coerced = [];

        foreach ($this->fields() as $name => $definition) {
            if ($this->has($name)) {
                $coerced[$name] = $definition->coerce($this->input($name));
            }
        }

        $this->merge($coerced);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->fields() as $name => $definition) {
            foreach ($definition->validationRules($name) as $attribute => $attributeRules) {
                $rules[$attribute] = $attribute === $name ? ['sometimes', ...$attributeRules] : $attributeRules;
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->fields() as $name => $definition) {
            $label = __($definition->labelKey());
            $attributes[$name] = $label;

            // items of lists/tables (`destinations.0`, `carriers.1.code`): name them instead of echoing the dotted path
            if ($definition->type->isCollection()) {
                $attributes["{$name}.*"] = $label;

                foreach (array_keys($definition->schema) as $column) {
                    $columnKey = "settings.ui.column.{$column}";
                    $attributes["{$name}.*.{$column}"] = __($columnKey) !== $columnKey ? __($columnKey) : $column;
                }
            }
        }

        return $attributes;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $byKey = [];
            foreach ($this->fields() as $name => $definition) {
                if ($this->has($name)) {
                    $byKey[$definition->key] = $this->input($name);
                }
            }

            foreach (app(Settings::class)->crossFieldErrors($byKey) as $key => $message) {
                $validator->errors()->add(app(SettingsRegistry::class)->get($key)->name, $message);
            }
        }];
    }

    /**
     * Validated values keyed by setting key, ready for Settings::setMany().
     *
     * @return array<string, mixed>
     */
    public function settingValues(): array
    {
        $validated = $this->validated();
        $values = [];

        foreach ($this->fields() as $name => $definition) {
            if (array_key_exists($name, $validated)) {
                $values[$definition->key] = $validated[$name];
            }
        }

        return $values;
    }
}
