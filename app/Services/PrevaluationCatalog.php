<?php

namespace App\Services;

class PrevaluationCatalog
{
    public const DOCUMENTS = [
        'prop_escrito' => 'Propuesta por escrito',
        'acta_nac' => 'Acta de nacimiento',
        'identi_ofici' => 'Identificación oficial',
        'domicilio' => 'Comprobante de domicilio',
        'dat_grals' => 'Datos generales',
        'dos_cartas' => 'Primera carta de recomendación',
        'ine_cartarecom_1' => 'INE de la primera recomendación',
        'carta_recom2' => 'Segunda carta de recomendación',
        'ine_cartarecom_2' => 'INE de la segunda recomendación',
        'carta_protesta' => 'Carta protesta',
        'menu_aval' => 'Menú avalado por nutriólogo',
        'forma_prec' => 'Formato de publicación de precios',
        'mobiliario' => 'Mobiliario que utilizará',
        'menuali_precio' => 'Listado de productos o servicios con precios',
        'marca_porcion' => 'Marca y porción de los alimentos',
    ];

    public const CAFE_PRICES = [
        'tlacoyo' => 'Tlacoyo de nopales', 'tortafrijol' => 'Torta de frijoles',
        'tortapollo' => 'Torta de pollo', 'quesadilla' => 'Quesadilla',
        'tostada' => 'Tostada de nopales', 'enfrijoladas' => 'Enfrijoladas',
        'gelatina' => 'Gelatina', 'yogurt' => 'Yogurt', 'palomitas' => 'Palomitas',
        'atole' => 'Atole', 'aguasimple' => 'Agua simple', 'aguafruta' => 'Agua de frutas',
    ];

    public const FOTO_PRICES = [
        'oficio' => 'Tamaño oficio', 'carta' => 'Tamaño carta',
        'mtc' => 'MTC', 'mto' => 'MTO', 'rtc' => 'RTC', 'rto' => 'RTO',
        'rmtc' => 'RMTC', 'rmto' => 'RMTO',
    ];

    public static function documents(string $service): array
    {
        return $service === 'cafeteria' ? self::DOCUMENTS : array_diff_key(self::DOCUMENTS, array_flip(['menu_aval', 'marca_porcion']));
    }

    public static function prices(string $service): array
    {
        return $service === 'cafeteria' ? self::CAFE_PRICES : self::FOTO_PRICES;
    }
}
