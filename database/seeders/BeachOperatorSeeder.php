// Добавим двух разных операторов для проверки разделения прав
DB::table('beach_operators')->insert([
    [
        'beach_id' => 2, // Пляж Омега (например)
        'operator_hash' => 'omega_secure_token_2026',
        'name' => 'Оператор пляжа Омега',
        'created_at' => now(), 'updated_at' => now()
    ],
    [
        'beach_id' => 3, // Пляж Аквамарин (например)
        'operator_hash' => 'aquamarin_secure_token_2026',
        'name' => 'Оператор пляжа Аквамарин',
        'created_at' => now(), 'updated_at' => now()
    ]
]);