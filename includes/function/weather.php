<?php
function getWeather() {
    // File untuk menyimpan cache
    $cacheFile = 'weather_cache.json';
    // Cache berlaku 30 menit
    $cacheTime = 1800; 

    // Jika cache masih valid, langsung gunakan data cache
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    // Jika cache tidak valid, baru ambil data dari API
    $url = "https://api.open-meteo.com/v1/forecast?latitude=-6.5944&longitude=106.7892&current=temperature_2m&hourly=temperature_2m&daily=weather_code";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return ["error" => "Error"];
    }
    curl_close($ch);
    
    $data = json_decode($response, true);
    if (isset($data['hourly']['temperature_2m'][0])) {
        $result = ["temperature" => round($data['hourly']['temperature_2m'][0])];
        // Simpan hasil ke cache
        file_put_contents($cacheFile, json_encode($result));
        return $result;
    }
    return ["error" => "N/A"];
}
?>