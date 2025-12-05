<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dataDir = __DIR__ . '/../data';
$salesFile = $dataDir . '/sales.json';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

function readJsonSafe($file) {
    if (!file_exists($file)) return [];
    $content = @file_get_contents($file);
    if ($content === false || $content === '') return [];
    $json = json_decode($content, true);
    return is_array($json) ? $json : [];
}

// 月間レポートを生成
function generateMonthlyReport($year, $month) {
    global $salesFile;
    
    $allSales = readJsonSafe($salesFile);
    $monthlyData = [];
    
    // 指定された月のデータを抽出
    foreach ($allSales as $sale) {
        $saleDate = $sale['date'] ?? '';
        $saleYear = intval(substr($saleDate, 0, 4));
        $saleMonth = intval(substr($saleDate, 5, 2));
        
        if ($saleYear === $year && $saleMonth === $month) {
            $monthlyData[] = $sale;
        }
    }
    
    // レポートデータを集計
    $report = [
        'year' => $year,
        'month' => $month,
        'totalDays' => count($monthlyData),
        'totalReservations' => 0,
        'totalPeople' => 0,
        'menuSales' => [],
        'dailySales' => [],
        'timeSlotSales' => [],
        'topMenu' => [],
        'averageDailyPeople' => 0,
        'busiestDay' => null,
        'busiestTimeSlot' => null
    ];
    
    // 基本統計を計算
    foreach ($monthlyData as $sale) {
        $report['totalReservations'] += $sale['totalReservations'] ?? 0;
        $report['totalPeople'] += $sale['totalPeople'] ?? 0;
        
        // メニュー別売上を集計
        foreach ($sale['menuSales'] ?? [] as $menu => $quantity) {
            if (!isset($report['menuSales'][$menu])) {
                $report['menuSales'][$menu] = 0;
            }
            $report['menuSales'][$menu] += $quantity;
        }
        
        // 時間帯別売上を集計
        foreach ($sale['timeSlots'] ?? [] as $time => $quantity) {
            if (!isset($report['timeSlotSales'][$time])) {
                $report['timeSlotSales'][$time] = 0;
            }
            $report['timeSlotSales'][$time] += $quantity;
        }
        
        // 日別データを保存
        $report['dailySales'][] = [
            'date' => $sale['date'],
            'reservations' => $sale['totalReservations'] ?? 0,
            'people' => $sale['totalPeople'] ?? 0
        ];
    }
    
    // 平均値を計算
    if ($report['totalDays'] > 0) {
        $report['averageDailyPeople'] = round($report['totalPeople'] / $report['totalDays'], 1);
    }
    
    // トップメニューを計算
    arsort($report['menuSales']);
    $report['topMenu'] = array_slice($report['menuSales'], 0, 5, true);
    
    // 最も忙しかった日を特定
    $maxPeople = 0;
    foreach ($report['dailySales'] as $daily) {
        if ($daily['people'] > $maxPeople) {
            $maxPeople = $daily['people'];
            $report['busiestDay'] = $daily['date'];
        }
    }
    
    // 最も忙しかった時間帯を特定
    $maxTimeSlot = 0;
    foreach ($report['timeSlotSales'] as $time => $quantity) {
        if ($quantity > $maxTimeSlot) {
            $maxTimeSlot = $quantity;
            $report['busiestTimeSlot'] = $time;
        }
    }
    
    return $report;
}

// Excel形式のCSVを生成
function generateExcelReport($report) {
    $csv = "月間レポート - {$report['year']}年{$report['month']}月\n\n";
    $csv .= "基本統計\n";
    $csv .= "総営業日数,{$report['totalDays']}\n";
    $csv .= "総予約数,{$report['totalReservations']}\n";
    $csv .= "総来客数,{$report['totalPeople']}\n";
    $csv .= "1日平均来客数,{$report['averageDailyPeople']}\n";
    $csv .= "最も忙しかった日,{$report['busiestDay']}\n";
    $csv .= "最も忙しかった時間帯,{$report['busiestTimeSlot']}\n\n";
    
    $csv .= "メニュー別売上\n";
    $csv .= "メニュー名,売上数\n";
    foreach ($report['menuSales'] as $menu => $quantity) {
        $csv .= "{$menu},{$quantity}\n";
    }
    
    $csv .= "\n時間帯別売上\n";
    $csv .= "時間帯,来客数\n";
    foreach ($report['timeSlotSales'] as $time => $quantity) {
        $csv .= "{$time},{$quantity}\n";
    }
    
    $csv .= "\n日別売上\n";
    $csv .= "日付,予約数,来客数\n";
    foreach ($report['dailySales'] as $daily) {
        $csv .= "{$daily['date']},{$daily['reservations']},{$daily['people']}\n";
    }
    
    return $csv;
}

// API処理
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
    $format = $_GET['format'] ?? 'json';
    
    $report = generateMonthlyReport($year, $month);
    
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="monthly_report_' . $year . '_' . sprintf('%02d', $month) . '.csv"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache, max-age=0');
        echo generateExcelReport($report);
    } else {
        echo json_encode($report, JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
}
?>
