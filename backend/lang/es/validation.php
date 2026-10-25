<?php

/*
| Mensajes de validación en español para las reglas que usa SIAR.
| Las reglas que no estén aquí usan el texto en inglés de Laravel.
*/
return [
    'array' => 'El campo :attribute debe ser una lista.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'different' => 'El campo :attribute debe ser distinto de :other.',
    'distinct' => 'El campo :attribute tiene un valor repetido.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'enum' => 'El valor de :attribute no es válido.',
    'exists' => 'El :attribute seleccionado no existe.',
    'in' => 'El valor de :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'array' => 'El campo :attribute no debe tener más de :max elementos.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener más de :max caracteres.',
    ],
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'password' => [
        'letters' => 'La :attribute debe contener al menos una letra.',
        'mixed' => 'La :attribute debe contener al menos una mayúscula y una minúscula.',
        'numbers' => 'La :attribute debe contener al menos un número.',
        'symbols' => 'La :attribute debe contener al menos un símbolo.',
        'uncompromised' => 'La :attribute aparece en una filtración de datos. Elige otra.',
    ],
    'present' => 'El campo :attribute debe estar presente.',
    'prohibited' => 'El campo :attribute no está permitido.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'El :attribute ya está registrado.',

    'attributes' => [
        'correo' => 'correo',
        'contrasena' => 'contraseña',
        'contrasena_actual' => 'contraseña actual',
        'nombres' => 'nombres',
        'documento' => 'documento',
        'id_rol' => 'rol',
        'nombre_rol' => 'nombre del rol',
        'descripcion' => 'descripción',
        'permisos' => 'permisos',
        'token' => 'token',
    ],
];
