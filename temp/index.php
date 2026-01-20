foreach ($csvFiles as $index => $file) {
    if (!isset($file['url'])) {
        continue;
    }

    // Buka stream dari URL (tidak download penuh ke memory)
    $csvStream = fopen($file['url'], 'r');
    if (!$csvStream) {
        Log::warning('Gagal buka stream CSV', ['url' => $file['url']]);
        continue;
    }

    // Simpan stream ke file temp sementara (chunk by chunk, tidak load penuh)
    $tempCsvPath = storage_path('app/tmp_csv_' . uniqid() . '.csv');
    $tempCsvFile = fopen($tempCsvPath, 'w');
    
    // Copy stream chunk by chunk (efisien memory)
    stream_copy_to_stream($csvStream, $tempCsvFile);
    
    fclose($csvStream);
    fclose($tempCsvFile);

    // Tambahkan file ke ZIP (lebih efisien daripada addFromString)
    $fileName = 'csv_' . ($index + 1) . '.csv';
    $zip->addFile($tempCsvPath, $fileName);

    // Simpan path untuk cleanup nanti
    $tempCsvFiles[] = $tempCsvPath;
}