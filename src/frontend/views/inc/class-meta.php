<?php

class Meta
{
    private static $data = [
      'title' => 'Film-o-mètre',
      'description' => 'Film-o-mètre est un site de notation de films, permettant aux utilisateurs de noter les films qu\'ils ont vus.',
      'image' => 'og-image.png'
    ];

    public static function set(array $data)
    {
        self::$data = array_merge(self::$data, $data);
    }

    public static function get($key)
    {
        return self::$data[$key] ?? null;
    }
}
