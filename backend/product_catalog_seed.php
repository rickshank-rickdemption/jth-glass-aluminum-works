<?php

if (!function_exists('getSeedPricingRowsTableA')) {
    function getSeedPricingRowsTableA()
    {
        return [
            [798, 'Analok', 6, 'Ordinary', 'Bronze', 450, 500],
            [798, 'Analok', 6, 'Ordinary', 'Clear', 450, 500],
            [798, 'White', 6, 'Ordinary', 'Bronze', 450, 500],
            [798, 'White', 6, 'Ordinary', 'Clear', 450, 500],
            [798, 'Powder-Coated (Other Color)', 6, 'Ordinary', 'Bronze', 550, 600],
            [798, 'Powder-Coated (Other Color)', 6, 'Ordinary', 'Clear', 550, 600],
            [798, 'Analok', 6, 'Ordinary', 'Other Color', 500, 550],
            [798, 'White', 6, 'Ordinary', 'Other Color', 500, 550],
            [798, 'Powder-Coated (Other Color)', 6, 'Ordinary', 'Other Color', 600, 650],
            [798, 'Analok', 6, 'Tempered', 'Bronze', 550, 600],
            [798, 'Analok', 6, 'Tempered', 'Clear', 535, 585],
            [798, 'White', 6, 'Tempered', 'Bronze', 550, 600],
            [798, 'White', 6, 'Tempered', 'Clear', 535, 585],
            [798, 'Powder-Coated (Other Color)', 6, 'Tempered', 'Bronze', 650, 700],
            [798, 'Powder-Coated (Other Color)', 6, 'Tempered', 'Clear', 635, 685],
            [798, 'Analok', 6, 'Tempered', 'Other Color', 650, 700],
            [798, 'White', 6, 'Tempered', 'Other Color', 650, 700],
            [798, 'Powder-Coated (Other Color)', 6, 'Tempered', 'Other Color', 750, 800],

            [900, 'Analok', 6, 'Ordinary', 'Bronze', 550, 600],
            [900, 'Analok', 6, 'Ordinary', 'Clear', 550, 600],
            [900, 'White', 6, 'Ordinary', 'Bronze', 550, 600],
            [900, 'White', 6, 'Ordinary', 'Clear', 550, 600],
            [900, 'Powder-Coated (Other Color)', 6, 'Ordinary', 'Bronze', 650, 700],
            [900, 'Powder-Coated (Other Color)', 6, 'Ordinary', 'Clear', 650, 700],
            [900, 'Analok', 6, 'Ordinary', 'Other Color', 600, 650],
            [900, 'White', 6, 'Ordinary', 'Other Color', 600, 650],
            [900, 'Powder-Coated (Other Color)', 6, 'Ordinary', 'Other Color', 700, 750],
            [900, 'Analok', 6, 'Tempered', 'Bronze', 650, 700],
            [900, 'Analok', 6, 'Tempered', 'Clear', 635, 685],
            [900, 'White', 6, 'Tempered', 'Bronze', 650, 700],
            [900, 'White', 6, 'Tempered', 'Clear', 635, 685],
            [900, 'Powder-Coated (Other Color)', 6, 'Tempered', 'Bronze', 750, 800],
            [900, 'Powder-Coated (Other Color)', 6, 'Tempered', 'Clear', 735, 785],
            [900, 'Analok', 6, 'Tempered', 'Other Color', 800, 850],
            [900, 'White', 6, 'Tempered', 'Other Color', 800, 850],
            [900, 'Powder-Coated (Other Color)', 6, 'Tempered', 'Other Color', 900, 950],

            [900, 'Analok', 9, 'Ordinary', 'Bronze', 620, 670],
            [900, 'Analok', 9, 'Ordinary', 'Clear', 620, 670],
            [900, 'White', 9, 'Ordinary', 'Bronze', 620, 670],
            [900, 'White', 9, 'Ordinary', 'Clear', 620, 670],
            [900, 'Powder-Coated (Other Color)', 9, 'Ordinary', 'Bronze', 720, 770],
            [900, 'Powder-Coated (Other Color)', 9, 'Ordinary', 'Clear', 720, 770],
            [900, 'Analok', 9, 'Ordinary', 'Other Color', 700, 750],
            [900, 'White', 9, 'Ordinary', 'Other Color', 700, 750],
            [900, 'Powder-Coated (Other Color)', 9, 'Ordinary', 'Other Color', 800, 850],
            [900, 'Analok', 9, 'Tempered', 'Bronze', 800, 850],
            [900, 'Analok', 9, 'Tempered', 'Clear', 785, 835],
            [900, 'White', 9, 'Tempered', 'Bronze', 800, 850],
            [900, 'White', 9, 'Tempered', 'Clear', 785, 835],
            [900, 'Powder-Coated (Other Color)', 9, 'Tempered', 'Bronze', 900, 950],
            [900, 'Powder-Coated (Other Color)', 9, 'Tempered', 'Clear', 885, 935],
            [900, 'Analok', 9, 'Tempered', 'Other Color', 980, 1030],
            [900, 'White', 9, 'Tempered', 'Other Color', 980, 1030],
            [900, 'Powder-Coated (Other Color)', 9, 'Tempered', 'Other Color', 1080, 1130],
        ];
    }
}

if (!function_exists('buildSeedProductsCatalogTableA')) {
    function buildSeedProductsCatalogTableA($productType = 'windows', $actor = 'seed-import')
    {
        $rows = getSeedPricingRowsTableA();
        $catalog = [];
        $nowTs = time();

        foreach ($rows as $r) {
            [$series, $alColor, $thickness, $glassType, $glassColor, $priceNoScreen, $priceWScreen] = $r;
            $series = (int)$series;
            $seriesKey = 'series_' . $series;
            $seriesName = $series . ' Series';

            if (!isset($catalog[$seriesKey])) {
                $catalog[$seriesKey] = [
                    'display_name' => $seriesName,
                    'variants' => []
                ];
            }

            $variantLabel = trim($alColor . ' - ' . $thickness . 'mm ' . $glassType . ' ' . $glassColor);
            $variantKey = strtolower(trim((string)$variantLabel));
            $variantKey = preg_replace('/[^a-z0-9]+/', '_', $variantKey);
            $variantKey = trim((string)$variantKey, '_');

            if ($variantKey === '') {
                continue;
            }

            $catalog[$seriesKey]['variants'][$variantKey] = [
                'label' => $variantLabel,
                'price_no_screen' => (float)$priceNoScreen,
                'price_w_screen' => (float)$priceWScreen,
                'product_type' => $productType,
                'is_available' => true,
                'updated_at' => $nowTs,
                'updated_by' => $actor
            ];
        }

        return $catalog;
    }
}

if (!function_exists('getSeedPricingRowsTableB')) {
    function getSeedPricingRowsTableB()
    {
        return [
            [38, 'Analok', 6, 'Ordinary', 'Bronze', 450, 500],
            [38, 'Analok', 6, 'Ordinary', 'Clear', 450, 500],
            [38, 'White', 6, 'Ordinary', 'Bronze', 450, 500],
            [38, 'White', 6, 'Ordinary', 'Clear', 450, 500],
            [38, 'Powder-Coated (Other Color)', 6, 'Ordinary', 'Bronze', 550, 600],
            [38, 'Powder-Coated (Other Color)', 6, 'Ordinary', 'Clear', 550, 600],
            [38, 'Analok', 6, 'Ordinary', 'Other Color', 500, 550],
            [38, 'White', 6, 'Ordinary', 'Other Color', 500, 550],
            [38, 'Powder-Coated (Other Color)', 6, 'Ordinary', 'Other Color', 600, 650],
            [38, 'Analok', 6, 'Tempered', 'Bronze', 550, 600],
            [38, 'Analok', 6, 'Tempered', 'Clear', 535, 585],
            [38, 'White', 6, 'Tempered', 'Bronze', 550, 600],
            [38, 'White', 6, 'Tempered', 'Clear', 535, 585],
            [38, 'Powder-Coated (Other Color)', 6, 'Tempered', 'Bronze', 650, 700],
            [38, 'Powder-Coated (Other Color)', 6, 'Tempered', 'Clear', 635, 685],
            [38, 'Analok', 6, 'Tempered', 'Other Color', 650, 700],
            [38, 'White', 6, 'Tempered', 'Other Color', 650, 700],
            [38, 'Powder-Coated (Other Color)', 6, 'Tempered', 'Other Color', 750, 800],

            [50, 'Analok', 6, 'Ordinary', 'Bronze', 620, 670],
            [50, 'Analok', 6, 'Ordinary', 'Clear', 620, 670],
            [50, 'White', 6, 'Ordinary', 'Bronze', 620, 670],
            [50, 'White', 6, 'Ordinary', 'Clear', 620, 670],
            [50, 'Powder-Coated (Other Color)', 6, 'Ordinary', 'Bronze', 720, 770],
            [50, 'Powder-Coated (Other Color)', 6, 'Ordinary', 'Clear', 720, 770],
            [50, 'Analok', 6, 'Ordinary', 'Other Color', 670, 720],
            [50, 'White', 6, 'Ordinary', 'Other Color', 670, 720],
            [50, 'Powder-Coated (Other Color)', 6, 'Ordinary', 'Other Color', 770, 820],
            [50, 'Analok', 6, 'Tempered', 'Bronze', 720, 770],
            [50, 'Analok', 6, 'Tempered', 'Clear', 705, 755],
            [50, 'White', 6, 'Tempered', 'Bronze', 720, 770],
            [50, 'White', 6, 'Tempered', 'Clear', 705, 755],
            [50, 'Powder-Coated (Other Color)', 6, 'Tempered', 'Bronze', 820, 870],
            [50, 'Powder-Coated (Other Color)', 6, 'Tempered', 'Clear', 805, 855],
            [50, 'Analok', 6, 'Tempered', 'Other Color', 870, 920],
            [50, 'White', 6, 'Tempered', 'Other Color', 870, 920],
            [50, 'Powder-Coated (Other Color)', 6, 'Tempered', 'Other Color', 970, 1020],

            [50, 'Analok', 9, 'Ordinary', 'Bronze', 690, 740],
            [50, 'Analok', 9, 'Ordinary', 'Clear', 690, 740],
            [50, 'White', 9, 'Ordinary', 'Bronze', 690, 740],
            [50, 'White', 9, 'Ordinary', 'Clear', 690, 740],
            [50, 'Powder-Coated (Other Color)', 9, 'Ordinary', 'Bronze', 790, 840],
            [50, 'Powder-Coated (Other Color)', 9, 'Ordinary', 'Clear', 790, 840],
            [50, 'Analok', 9, 'Ordinary', 'Other Color', 770, 820],
            [50, 'White', 9, 'Ordinary', 'Other Color', 770, 820],
            [50, 'Powder-Coated (Other Color)', 9, 'Ordinary', 'Other Color', 870, 920],
            [50, 'Analok', 9, 'Tempered', 'Bronze', 870, 920],
            [50, 'Analok', 9, 'Tempered', 'Clear', 855, 905],
            [50, 'White', 9, 'Tempered', 'Bronze', 870, 920],
            [50, 'White', 9, 'Tempered', 'Clear', 855, 905],
            [50, 'Powder-Coated (Other Color)', 9, 'Tempered', 'Bronze', 970, 1020],
            [50, 'Powder-Coated (Other Color)', 9, 'Tempered', 'Clear', 955, 1005],
            [50, 'Analok', 9, 'Tempered', 'Other Color', 1050, 1100],
            [50, 'White', 9, 'Tempered', 'Other Color', 1050, 1100],
            [50, 'Powder-Coated (Other Color)', 9, 'Tempered', 'Other Color', 1150, 1200],
        ];
    }
}

if (!function_exists('buildSeedProductsCatalogTableB')) {
    function buildSeedProductsCatalogTableB($productType = 'windows', $actor = 'seed-import')
    {
        $rows = getSeedPricingRowsTableB();
        $catalog = [];
        $nowTs = time();

        foreach ($rows as $r) {
            [$series, $alColor, $thickness, $glassType, $glassColor, $priceNoScreen, $priceWScreen] = $r;
            $series = (int)$series;
            $seriesKey = 'series_' . $series;
            $seriesName = $series . ' Series';

            if (!isset($catalog[$seriesKey])) {
                $catalog[$seriesKey] = [
                    'display_name' => $seriesName,
                    'variants' => []
                ];
            }

            $variantLabel = trim($alColor . ' - ' . $thickness . 'mm ' . $glassType . ' ' . $glassColor);
            $variantKey = strtolower(trim((string)$variantLabel));
            $variantKey = preg_replace('/[^a-z0-9]+/', '_', $variantKey);
            $variantKey = trim((string)$variantKey, '_');
            if ($variantKey === '') {
                continue;
            }

            $catalog[$seriesKey]['variants'][$variantKey] = [
                'label' => $variantLabel,
                'price_no_screen' => (float)$priceNoScreen,
                'price_w_screen' => (float)$priceWScreen,
                'product_type' => $productType,
                'is_available' => true,
                'updated_at' => $nowTs,
                'updated_by' => $actor
            ];
        }

        return $catalog;
    }
}

if (!function_exists('getSeedPricingRowsTableCNoScreen')) {
    function getSeedPricingRowsTableCNoScreen()
    {
        return [
            ['Analok', 6, 'Ordinary', 'Bronze', 680],
            ['Analok', 6, 'Ordinary', 'Clear', 680],
            ['White', 6, 'Ordinary', 'Bronze', 680],
            ['White', 6, 'Ordinary', 'Clear', 680],
            ['Powder-Coated (Other Color)', 6, 'Ordinary', 'Bronze', 780],
            ['Powder-Coated (Other Color)', 6, 'Ordinary', 'Clear', 780],
            ['Analok', 6, 'Ordinary', 'Other Color', 730],
            ['White', 6, 'Ordinary', 'Other Color', 730],
            ['Powder-Coated (Other Color)', 6, 'Ordinary', 'Other Color', 830],

            ['Analok', 6, 'Tempered', 'Bronze', 780],
            ['Analok', 6, 'Tempered', 'Clear', 765],
            ['White', 6, 'Tempered', 'Bronze', 780],
            ['White', 6, 'Tempered', 'Clear', 765],
            ['Powder-Coated (Other Color)', 6, 'Tempered', 'Bronze', 880],
            ['Powder-Coated (Other Color)', 6, 'Tempered', 'Clear', 765],
            ['Analok', 6, 'Tempered', 'Other Color', 930],
            ['White', 6, 'Tempered', 'Other Color', 930],
            ['Powder-Coated (Other Color)', 6, 'Tempered', 'Other Color', 1030],

            ['Analok', 9, 'Ordinary', 'Bronze', 750],
            ['Analok', 9, 'Ordinary', 'Clear', 750],
            ['White', 9, 'Ordinary', 'Bronze', 750],
            ['White', 9, 'Ordinary', 'Clear', 750],
            ['Powder-Coated (Other Color)', 9, 'Ordinary', 'Bronze', 850],
            ['Powder-Coated (Other Color)', 9, 'Ordinary', 'Clear', 850],
            ['Analok', 9, 'Ordinary', 'Other Color', 830],
            ['White', 9, 'Ordinary', 'Other Color', 830],
            ['Powder-Coated (Other Color)', 9, 'Ordinary', 'Other Color', 930],

            ['Analok', 9, 'Tempered', 'Bronze', 930],
            ['Analok', 9, 'Tempered', 'Clear', 930],
            ['White', 9, 'Tempered', 'Bronze', 930],
            ['White', 9, 'Tempered', 'Clear', 930],
            ['Powder-Coated (Other Color)', 9, 'Tempered', 'Bronze', 1030],
            ['Powder-Coated (Other Color)', 9, 'Tempered', 'Clear', 1030],
            ['Analok', 9, 'Tempered', 'Other Color', 1110],
            ['White', 9, 'Tempered', 'Other Color', 1110],
            ['Powder-Coated (Other Color)', 9, 'Tempered', 'Other Color', 1210],
        ];
    }
}

if (!function_exists('buildSeedProductsCatalogTableCNoScreen')) {
    function buildSeedProductsCatalogTableCNoScreen($productType = 'windows', $actor = 'seed-import')
    {
        $rows = getSeedPricingRowsTableCNoScreen();
        $nowTs = time();
        $productKey = 'catalog_no_screen_only';
        $catalog = [
            $productKey => [
                'display_name' => 'Catalog (No Screen Only)',
                'variants' => []
            ]
        ];

        foreach ($rows as $r) {
            [$alColor, $thickness, $glassType, $glassColor, $priceNoScreen] = $r;
            $variantLabel = trim($alColor . ' - ' . $thickness . 'mm ' . $glassType . ' ' . $glassColor . ' (No Screen)');
            $variantKey = strtolower(trim((string)$variantLabel));
            $variantKey = preg_replace('/[^a-z0-9]+/', '_', $variantKey);
            $variantKey = trim((string)$variantKey, '_');
            if ($variantKey === '') {
                continue;
            }

            $catalog[$productKey]['variants'][$variantKey] = [
                'label' => $variantLabel,
                'price_no_screen' => (float)$priceNoScreen,
                'price_w_screen' => 0,
                'product_type' => $productType,
                'is_available' => true,
                'updated_at' => $nowTs,
                'updated_by' => $actor
            ];
        }

        return $catalog;
    }
}

if (!function_exists('getSeedAccessoriesRowsTableD')) {
    function getSeedAccessoriesRowsTableD()
    {
        return [
            [10, 'Tempered', 'Bronze', 'Patch Fitting Set w/ Handle', 1250],
            [10, 'Tempered', 'Clear', 'Patch Fitting Set w/ Handle', 1100],
            [10, 'Tempered', 'Other Color', 'Patch Fitting Set w/ Handle', 1320],
            [12, 'Tempered', 'Bronze', 'Patch Fitting Set w/ Handle', 1350],
            [12, 'Tempered', 'Clear', 'Patch Fitting Set w/ Handle', 1200],
            [12, 'Tempered', 'Other Color', 'Patch Fitting Set w/ Handle', 1420],
        ];
    }
}

if (!function_exists('buildSeedAccessoriesCatalogTableD')) {
    function buildSeedAccessoriesCatalogTableD($actor = 'seed-import')
    {
        $rows = getSeedAccessoriesRowsTableD();
        $nowTs = time();
        $productKey = 'accessories_patch_fitting';
        $catalog = [
            $productKey => [
                'display_name' => 'Accessories - Patch Fitting Set w/ Handle',
                'variants' => []
            ]
        ];

        foreach ($rows as $r) {
            [$thickness, $glassType, $glassColor, $accessoryLabel, $priceNoScreen] = $r;
            $variantLabel = trim($thickness . 'mm ' . $glassType . ' ' . $glassColor . ' - ' . $accessoryLabel);
            $variantKey = strtolower(trim((string)$variantLabel));
            $variantKey = preg_replace('/[^a-z0-9]+/', '_', $variantKey);
            $variantKey = trim((string)$variantKey, '_');
            if ($variantKey === '') {
                continue;
            }

            $catalog[$productKey]['variants'][$variantKey] = [
                'label' => $variantLabel,
                'price_no_screen' => (float)$priceNoScreen,
                'price_w_screen' => 0,
                'product_type' => 'accessories',
                'is_available' => true,
                'updated_at' => $nowTs,
                'updated_by' => $actor
            ];
        }

        return $catalog;
    }
}

if (!function_exists('getSeedPricingRowsTableENoScreen')) {
    function getSeedPricingRowsTableENoScreen()
    {
        return [
            ['Analok', 6, 'Ordinary', 'Bronze', 300],
            ['Analok', 6, 'Ordinary', 'Clear', 300],
            ['White', 6, 'Ordinary', 'Bronze', 300],
            ['White', 6, 'Ordinary', 'Clear', 300],
            ['Powder-Coated (Other Color)', 6, 'Ordinary', 'Bronze', 400],
            ['Powder-Coated (Other Color)', 6, 'Ordinary', 'Clear', 400],
            ['Analok', 6, 'Ordinary', 'Other Color', 350],
            ['White', 6, 'Ordinary', 'Other Color', 350],
            ['Powder-Coated (Other Color)', 6, 'Ordinary', 'Other Color', 450],

            ['Analok', 6, 'Tempered', 'Bronze', 400],
            ['Analok', 6, 'Tempered', 'Clear', 385],
            ['White', 6, 'Tempered', 'Bronze', 400],
            ['White', 6, 'Tempered', 'Clear', 385],
            ['Powder-Coated (Other Color)', 6, 'Tempered', 'Bronze', 500],
            ['Powder-Coated (Other Color)', 6, 'Tempered', 'Clear', 385],
            ['Analok', 6, 'Tempered', 'Other Color', 550],
            ['White', 6, 'Tempered', 'Other Color', 550],
            ['Powder-Coated (Other Color)', 6, 'Tempered', 'Other Color', 650],

            ['Analok', 9, 'Ordinary', 'Bronze', 370],
            ['Analok', 9, 'Ordinary', 'Clear', 370],
            ['White', 9, 'Ordinary', 'Bronze', 370],
            ['White', 9, 'Ordinary', 'Clear', 370],
            ['Powder-Coated (Other Color)', 9, 'Ordinary', 'Bronze', 470],
            ['Powder-Coated (Other Color)', 9, 'Ordinary', 'Clear', 470],
            ['Analok', 9, 'Ordinary', 'Other Color', 450],
            ['White', 9, 'Ordinary', 'Other Color', 450],
            ['Powder-Coated (Other Color)', 9, 'Ordinary', 'Other Color', 550],

            ['Analok', 9, 'Tempered', 'Bronze', 550],
            ['Analok', 9, 'Tempered', 'Clear', 550],
            ['White', 9, 'Tempered', 'Bronze', 550],
            ['White', 9, 'Tempered', 'Clear', 550],
            ['Powder-Coated (Other Color)', 9, 'Tempered', 'Bronze', 650],
            ['Powder-Coated (Other Color)', 9, 'Tempered', 'Clear', 650],
            ['Analok', 9, 'Tempered', 'Other Color', 730],
            ['White', 9, 'Tempered', 'Other Color', 730],
            ['Powder-Coated (Other Color)', 9, 'Tempered', 'Other Color', 830],
        ];
    }
}

if (!function_exists('buildSeedProductsCatalogTableENoScreen')) {
    function buildSeedProductsCatalogTableENoScreen($productType = 'windows', $actor = 'seed-import')
    {
        $rows = getSeedPricingRowsTableENoScreen();
        $nowTs = time();
        $productKey = 'catalog_no_screen_only_tier_2';
        $catalog = [
            $productKey => [
                'display_name' => 'Catalog (No Screen Only) - Tier 2',
                'variants' => []
            ]
        ];

        foreach ($rows as $r) {
            [$alColor, $thickness, $glassType, $glassColor, $priceNoScreen] = $r;
            $variantLabel = trim($alColor . ' - ' . $thickness . 'mm ' . $glassType . ' ' . $glassColor . ' (No Screen)');
            $variantKey = strtolower(trim((string)$variantLabel));
            $variantKey = preg_replace('/[^a-z0-9]+/', '_', $variantKey);
            $variantKey = trim((string)$variantKey, '_');
            if ($variantKey === '') {
                continue;
            }

            $catalog[$productKey]['variants'][$variantKey] = [
                'label' => $variantLabel,
                'price_no_screen' => (float)$priceNoScreen,
                'price_w_screen' => 0,
                'product_type' => $productType,
                'is_available' => true,
                'updated_at' => $nowTs,
                'updated_by' => $actor
            ];
        }

        return $catalog;
    }
}
