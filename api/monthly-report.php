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
    $dataDir = __DIR__ . '/../data';
    $reservationsFile = $dataDir . '/reservations.json';
    $archiveFile = $dataDir . '/reservations-archive.json';
    $holidaysFile = $dataDir . '/holidays.json';
    
    // 予約データを読み込み（現在の予約 + アーカイブ）
    $currentReservations = readJsonSafe($reservationsFile);
    $archivedReservations = readJsonSafe($archiveFile);
    $allReservations = array_merge($archivedReservations, $currentReservations);
    
    // 休業日データを読み込み
    $allHolidays = readJsonSafe($holidaysFile);
    
    // 指定された月の日数を計算
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    
    // 指定された月の休業日を抽出
    $monthHolidays = [];
    foreach ($allHolidays as $holiday) {
        $holidayDate = $holiday['date'] ?? '';
        $holidayYear = intval(substr($holidayDate, 0, 4));
        $holidayMonth = intval(substr($holidayDate, 5, 2));
        
        if ($holidayYear === $year && $holidayMonth === $month) {
            $monthHolidays[] = $holidayDate;
        }
    }
    
    // 営業日数 = 月の日数 - 休業日数
    $totalDays = $daysInMonth - count($monthHolidays);
    
    // 指定された月の予約を抽出
    $monthReservations = [];
    foreach ($allReservations as $reservation) {
        $reservationDate = $reservation['date'] ?? '';
        $reservationYear = intval(substr($reservationDate, 0, 4));
        $reservationMonth = intval(substr($reservationDate, 5, 2));
        
        if ($reservationYear === $year && $reservationMonth === $month) {
            $monthReservations[] = $reservation;
        }
    }
    
    // レポートデータを初期化
    $report = [
        'year' => $year,
        'month' => $month,
        'totalDays' => $totalDays,
        'totalReservations' => 0, // 予約した人数の合計
        'totalPeople' => 0, // 認証済み（verified=true）の人数
        'menuSales' => [],
        'dailySales' => [],
        'timeSlotSales' => [],
        'topMenu' => [],
        'averageDailyPeople' => 0,
        'busiestDay' => null,
        'busiestTimeSlot' => null
    ];
    
    // 日別データを初期化
    $dailyData = [];
    
    // 予約データを集計
    foreach ($monthReservations as $reservation) {
        $date = $reservation['date'] ?? '';
        $people = intval($reservation['people'] ?? 1); // 予約人数
        $food = $reservation['food'] ?? '';
        $time = $reservation['time'] ?? '';
        $verified = isset($reservation['verified']) && ($reservation['verified'] === true || $reservation['verified'] === 'true' || $reservation['verified'] === 1);
        
        // 日別データを初期化（まだ存在しない場合）
        if (!isset($dailyData[$date])) {
            $dailyData[$date] = [
                'date' => $date,
                'reservations' => 0, // 予約人数の合計
                'people' => 0 // 認証済み人数
            ];
        }
        
        // 予約人数を加算（予約数）
        $dailyData[$date]['reservations'] += $people;
        $report['totalReservations'] += $people;
        
        // 認証済みの場合のみ来客数に加算
        if ($verified) {
            $dailyData[$date]['people'] += $people;
            $report['totalPeople'] += $people;
        }
        
        // メニュー別売上を集計（認証済みのみ）
        if ($verified && $food) {
            if (!isset($report['menuSales'][$food])) {
                $report['menuSales'][$food] = 0;
            }
            $report['menuSales'][$food] += $people;
        }
        
        // 時間帯別売上を集計（認証済みのみ）
        if ($verified && $time) {
            // 時間帯を30分単位でグループ化（例: 11:00-11:30, 11:30-12:00）
            $timeSlot = substr($time, 0, 5); // HH:MM形式
            if (!isset($report['timeSlotSales'][$timeSlot])) {
                $report['timeSlotSales'][$timeSlot] = 0;
            }
            $report['timeSlotSales'][$timeSlot] += $people;
        }
    }
    
    // 日別データを配列に変換（日付順にソート）
    ksort($dailyData);
    $report['dailySales'] = array_values($dailyData);
    
    // 平均値を計算
    if ($report['totalDays'] > 0) {
        $report['averageDailyPeople'] = round($report['totalPeople'] / $report['totalDays'], 1);
    }
    
    // トップメニューを計算
    arsort($report['menuSales']);
    $report['topMenu'] = array_slice($report['menuSales'], 0, 5, true);
    
    // 最も忙しかった日を特定（認証済み人数が最多の日）
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
