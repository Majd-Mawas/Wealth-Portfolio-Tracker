<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class ApiFormRequest extends FormRequest
{
    /** @return list<string> */
    abstract protected function allowedFields(): array;

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unexpected = array_diff(array_keys($this->all()), $this->allowedFields());

            foreach ($unexpected as $field) {
                $validator->errors()->add($field, "The {$field} field is not allowed.");
            }
        });
    }
}
