<?php

declare(strict_types=1);

return [
    'required' => 'The :field field is required.',
    'string' => 'The :field field must be a string.',
    'integer' => 'The :field field must be an integer.',
    'email' => 'The :field field must be a valid email address.',
    'regex' => 'The :field field format is invalid.',
    'unique' => 'The :field has already been taken.',
    'exists' => 'The selected :field is invalid.',
    'min' => [
        'string' => 'The :field field must be at least :min characters.',
        'numeric' => 'The :field field must be at least :min.',
    ],
    'max' => [
        'string' => 'The :field field must not exceed :max characters.',
        'numeric' => 'The :field field must not exceed :max.',
    ],
];
