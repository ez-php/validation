<?php

declare(strict_types=1);

return [
    'required' => 'Das Feld :field ist erforderlich.',
    'string' => 'Das Feld :field muss eine Zeichenkette sein.',
    'integer' => 'Das Feld :field muss eine ganze Zahl sein.',
    'email' => 'Das Feld :field muss eine gültige E-Mail-Adresse sein.',
    'regex' => 'Das Format des Feldes :field ist ungültig.',
    'unique' => 'Der Wert für :field ist bereits vergeben.',
    'exists' => 'Der gewählte Wert für :field ist ungültig.',
    'confirmed' => 'Die Bestätigung für :field stimmt nicht überein.',
    'same' => ':field und :other müssen übereinstimmen.',
    'different' => ':field und :other müssen sich unterscheiden.',
    'file' => ':field muss eine gültig hochgeladene Datei sein.',
    'image' => ':field muss ein Bild sein.',
    'mimes' => ':field muss eine Datei vom Typ :values sein.',
    'max_size' => ':field darf :max Kilobyte nicht überschreiten.',
    'min' => [
        'string' => 'Das Feld :field muss mindestens :min Zeichen lang sein.',
        'numeric' => 'Das Feld :field muss mindestens :min betragen.',
    ],
    'max' => [
        'string' => 'Das Feld :field darf höchstens :max Zeichen lang sein.',
        'numeric' => 'Das Feld :field darf höchstens :max betragen.',
    ],
];
