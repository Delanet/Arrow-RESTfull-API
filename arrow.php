<?php

// Version
$strVersion = '3.1';

// URI этого файла
$strFileName = $_SERVER[REQUEST_URI];

// Случайное число для сброса кеша
$rnd = mt_rand (10000, 99999);

// Доступ в Arrow API
$strItemService = 'http://api.arrow.com/itemservice/v3/en/search/token';

$arrQueryData = array (
    'login' => '', // <Login>
    'apikey' => '', // <API Key>
);

if (isset ($_GET['query'])) {
    
    $arrQueryData['search_token'] = trim (filter_input (INPUT_GET, 'query', FILTER_SANITIZE_STRING));
    
/**
*    http://api.arrow.com/itemservice/v3/en/search/token?login=<login>&apikey=<apikey>&search_token=<token>
*/
    // Собираем строку запроса
    $strQuery = http_build_query ($arrQueryData);
    
    // Получаем JSON-ответ от сервера API
    $strJson = file_get_contents ("{$strItemService}?{$strQuery}");
    
    // Декодируем JSON-строку в ассоциативный массив
    $arrResult = json_decode ($strJson, true);
    
    // Удачный поиск
    // 1 - успешно
    $intResponseSuccess = $arrResult['itemserviceresult']['transactionArea'][0]['response']['success'];
    
    $arrPartList = $arrResult['itemserviceresult']['data'][0]['PartList'];
    $strOutput = "<p><strong>Not found!</strong></p>";
    $strMoreSearch = '';
    
    if ($intResponseSuccess) {
        
        if ($arrResult['itemserviceresult']['data'][0]['resources'][0]['type'] == 'search') {
            $strMoreSearch = "<span style=\"margin-left: 30px;\">Искать тоже самое на: <a href=\"{$arrResult['itemserviceresult']['data'][0]['resources'][0]['uri']}\" title=\"Искать на сайте Arrow\" target=\"_blank\">Arrow.com</a></span>";
        }
        
        $strOutput = "<p>Вы искали: <strong>{$arrQueryData['search_token']}</strong></p>";
        $strTablePart1 = '
<table>
    <tr>
        <th>Items</th>
        <th>Sources</th>
        <th>MPQ</th>
        <th>MOQ</th>
        <th>Prices & Quantities</th>
        <th>Availability</th>
        <th>DC</th>
        <th>Lead Time</th>
        <th>NCNR</th>
        <th>NPI</th>
        <th>ECCN</th>
        <th>Container</th>
        <th>Detail</th>
    </tr>
    ';
        foreach ($arrPartList as $arrPart) {
    
            // Euro RoHS Compliant
            $strEnvData = '';
            foreach ($arrPart['EnvData']['compliance'] as $rohs) {
                if ($rohs['displayLabel'] == 'eurohs') {
                    $strEnvData = $rohs['displayValue'];
                    break;
                }
            }
    
    
            $strTablePart2 = '';
            // Кол-во строк для объединения в таблице
            $intRowSpan1 = 0;
            foreach ($arrPart['InvOrg']['sources'] as $s => $d) {
                
                // Кол-во строк для объединения в таблице
                $intRowSpan2 = count ($d['sourceParts']);
                $intRowSpan1 += $intRowSpan2;
                
                $strTablePart3 = '';
                foreach ($d['sourceParts'] as $z => $y) {
                    
                    $strResaleList = '';
                    foreach ($y['Prices']['resaleList'] as $a => $prs) {
                        $strLineBreak = $a == 0 ? '' : '<br>';
                        $strResaleList .= "{$strLineBreak}{$prs['displayPrice']} &rarr; {$prs['minQty']}-{$prs['maxQty']}";
                    }
            
                    $strAvailability = '';
                    foreach ($y['Availability'] as $b => $ava) {
                
                        $strPipeline = '';
                        foreach ($ava['pipeline'] as $o => $ppl) {
                            // Преобразуем дату к единому формату
                            $strDelivery = date ('dS \o\f F Y', strtotime ($ppl['delivery']));
                            $strPipeline .= "<br>{$ppl['quantity']} pcs &rarr; {$strDelivery}";
                        }
                        
                        // Формируем строку наличия
                        $strFohQty = $y['inStock'] ? "{$ava['fohQty']} pcs &rarr; " : '';
                        $strAvailability .= "{$strFohQty}{$ava['availabilityMessage']}{$strPipeline}";
                    }
            
                    if ($z > 0) {
                    $strTablePart3 .= "
    <tr>";
                    }
                    
                    // Формируем описание источника предложения
                    $strResourse = '';
                    foreach ($y['resources'] as $resources) {
                        if ($resources['type'] == 'detail' and isset ($resources['uri'])) {
                            $strResourse = "<a href=\"{$resources['uri']}\" target=\"_blank\" title=\"Detail link\">link</a>";
                            break;
                        }
                    }
                    // Формируем строку датакода
                    if (strpos ($y['dateCode'], '+')) {
                        $strdateCode = $y['dateCode'];
                    } elseif ($y['dateCode'] != '') {
                        $strdateCode = "{$y['dateCode']}+";
                    } else {
                        $strdateCode = '';
                    }
                    
                    // Non-cancelable and Non-returnable
                    $strNonCancelableNonReturnable = $y['isNcnr'] ? '<span title="Non-Cancelable and Non-Returnable">NCNR</span>' : '';
                    
                    // New Product Introduction
                    $strNewProduct = $y['isNpi'] ? '<span title="New Product Introduction">NPI</span>' : '';
                    
                    // Формируем строку срока поставки
                    $strMfrLeadTime = $y['mfrLeadTime'] > 0 ? "{$y['mfrLeadTime']} weeks" : '';
                    
                    $strTablePart3 .= "
        <td class=\"center\">{$y['packSize']}</td>
        <td class=\"center\">{$y['minimumOrderQuantity']}</td>
        <td>{$strResaleList}</td>
        <td>{$strAvailability}</td>
        <td class=\"center\">{$strdateCode}</td>
        <td class=\"center\">{$strMfrLeadTime}</td>
        <td class=\"center\">{$strNonCancelableNonReturnable}</td>
        <td class=\"center\">{$strNewProduct}</td>
        <td class=\"center\">{$y['eccnCode']}</td>
        <td>{$y['containerType']}</td>
        <td class=\"center\">{$strResourse}</td>
    </tr>";
                }
        
                if ($s > 0) {
                    $strTablePart2 .= "
    <tr>";
                }
        
                // Формируем `rowspan` для тега <td>
                $strRowSpan2 = $intRowSpan2 > 1 ? " rowspan=\"{$intRowSpan2}\"" : '';
        
                $strTablePart2 .= "
        <td{$strRowSpan2}><strong><span title=\"{$d['displayName']}\">{$d['sourceCd']}</span></strong></td>        {$strTablePart3}";
            }
    
            // Формируем `rowspan` для тега <td>
            $strRowSpan1 = $intRowSpan1 > 1 ? " rowspan=\"{$intRowSpan1}\"" : '';
            
            // Формируем ссылку на техническое описание
            $strPartDetail = '';
            foreach ($arrPart['resources'] as $resources) {
                if ($resources['type'] == 'cloud_part_detail') {
                    $strDatasheet = $arrPart['hasDatasheet'] ? ' <sup>+PDF</sup>': '';
                    $strPartDetail = "<a href=\"{$resources['uri']}\" title=\"Cloud part detail\" target=\"_blank\">Cloud link{$strDatasheet}</a>";
                    break;
                }
            }
            // Построчное заполнение
            $strTablePart1 .= "    <tr>
        <td class=\"items\"{$strRowSpan1}>
            <table>
                <tr>
                    <td class=\"items_options\">Part number</td>
                    <td class=\"items_partNum\">{$arrPart['partNum']}</td>
                </tr>";
                $strTablePart1 .= $arrPart['manufacturer']['mfrName'] ? "
                <tr>
                    <td class=\"items_options\">Manufacturer</td>
                    <td class=\"items_params\">{$arrPart['manufacturer']['mfrName']} [{$arrPart['manufacturer']['mfrCd']}]</td>
                </tr>" : '';
                $strTablePart1 .= $arrPart['packageType'] ? "
                <tr>
                    <td class=\"items_options\">Package</td>
                    <td class=\"items_params\">{$arrPart['packageType']}</td>
                </tr>" : '';
                $strTablePart1 .= $strEnvData ? "
                <tr>
                    <td class=\"items_options\">EU RoHS</td>
                    <td class=\"items_params\">{$strEnvData}</td>
                </tr>" : '';
                $strTablePart1 .= $arrPart['desc'] ? "
                <tr>
                    <td class=\"items_options\">Description</td>
                    <td class=\"items_params\">{$arrPart['desc']}</td>
                </tr>" : '';
                $strTablePart1 .= $strPartDetail ? "
                <tr>
                    <td class=\"items_options\">Part detail</td>
                    <td class=\"items_params\">{$strPartDetail}</td>
                </tr>" : '';
                $strTablePart1 .= "
            </table>
        </td>{$strTablePart2}
";
        }
        
        // Закрываем таблицу и собираем вывод полностью
        $strOutput .= "{$strTablePart1}</table>";
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Search by Arrow</title>
    <style>
        table {
            width: auto;
            border-collapse: collapse;
            border-spacing: 0;
        }
        th, td {
            border: 1px solid black;
            vertical-align: top;
            padding: 5px;
        }
        th {
            height: 24px;
            background: #ddd;
            vertical-align: middle;
        }
        .items {
            padding: 0;
        }
        .items_options {
            text-align:right;
            border: 0;
            border-right: solid 1px;
        }
        .items_params,
        .items_partNum {
            width: 100%;
            border: 0;
        }
        .items_partNum {
            font-weight: 700;
        }
        .center {
            text-align: center;
        }
        .footer {
            border-top: solid 1px;
            font-style: italic;
            margin-top: 12px;
        }
        sup {
            font-size: 60%;
        }
    </style>
</head>
<body>

<div>
    <img src="data:image/jpg;base64,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" alt="Arrow.com">
</div>
<div>
    <form action="<?php echo $strFileName; ?>" metod="GET" autocomplete="on">
        <input type="hidden" name="rnd" value="<?php echo $rnd; ?>">
        <input type="text" size="60" name="query" placeholder="Search millions of products..." required autofocus>
        <button type="submit">Search</button><?php echo $strMoreSearch; ?>
    </form>
</div>
<div>
<?php
echo $strOutput;
?>
</div>

<footer>
    <div class="footer">
        <p>Syntactical Analyzer: <?php echo "v{$strVersion}"; if (isset ($_GET['query'])) echo " / Arrow Search API: v{$arrResult['itemserviceresult']['serviceMetaData'][0]['version']}";?> &bull; Arrow Electronics provides web-based <a href="http://developers.arrow.com/api/" target="_blank">RESTful API services</a> that allow customers and partners to automate some of the tasks that can be performed on Arrow.com, MyArrow, and Verical.com. Pricing & Availability API provides the capability to search for part numbers and retrieve price data and available inventory.</p>
    </div>
</footer>

</body>
</html>
