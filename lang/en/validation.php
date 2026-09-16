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
    'confirmed' => 'The :field confirmation does not match.',
    'same' => 'The :field and :other must match.',
    'different' => 'The :field and :other must be different.',
    'file' => 'The :field must be a valid uploaded file.',
    'image' => 'The :field must be an image.',
    'mimes' => 'The :field must be a file of type: :values.',
    'max_size' => 'The :field must not exceed :max kilobytes.',
    'min' => [
        'string' => 'The :field field must be at least :min characters.',
        'numeric' => 'The :field field must be at least :min.',
    ],
    'max' => [
        'string' => 'The :field field must not exceed :max characters.',
        'numeric' => 'The :field field must not exceed :max.',
    ],
    'boolean' => 'The :field field must be true or false.',
    'not_in' => 'The :field must not be one of: :values.',
    'uuid' => 'The :field must be a valid UUID.',
    'alpha' => 'The :field field must only contain letters.',
    'alpha_num' => 'The :field field must only contain letters and numbers.',
    'alpha_dash' => 'The :field field must only contain letters, numbers, dashes, and underscores.',
    'distinct' => 'The :field field has a duplicate value.',
];
