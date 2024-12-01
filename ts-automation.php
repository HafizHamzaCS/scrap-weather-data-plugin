<?php
// URL of the target website
$url = "https://www.bergfex.at/oesterreich/schneewerte/";

// Initialize cURL session
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);

// Check if the request was successful
if (curl_errno($ch)) {
    echo "Failed to fetch page: " . curl_error($ch);
    curl_close($ch);
    exit();
}
curl_close($ch);

// Load the HTML response
$dom = new DOMDocument();
libxml_use_internal_errors(true); // Suppress warnings for invalid HTML
$dom->loadHTML($response);
libxml_clear_errors();

// Find the table containing the data
$xpath = new DOMXPath($dom);
$table = $xpath->query("//div[@class='section touch-scroll-x']//table[@class='snow']");

if ($table->length == 0) {
    echo "Table not found.";
    exit();
}

// Extract weather data from rows
$data = [];
$rows = $table->item(0)->getElementsByTagName('tr');

// Skip the header row and iterate through table rows
foreach ($rows as $index => $row) {
    if ($index == 0) continue; // Skip header row

    $cells = $row->getElementsByTagName('td');
    if ($cells->length >= 6) { // Ensure there are enough columns in the row
        $skigebiet = trim($cells->item(0)->nodeValue); // Name of the region
        $tal = trim($cells->item(1)->nodeValue);       // Snow depth in valley
        $berg = trim($cells->item(2)->nodeValue);      // Snow depth on mountain
        $neu = trim($cells->item(3)->nodeValue);       // New snow
        $lifte = trim($cells->item(4)->nodeValue);     // Lifts open
        $datum = trim($cells->item(5)->nodeValue);     // Date

        // Prepare data for insertion into the database
        $data[] = [
            'region'      => $skigebiet,
            'snow_valley' => $tal,
            'snow_mountain' => $berg,
            'new_snow'    => $neu,
            'lifts_open'  => (int)$lifte, // Cast to integer
            'report_date' => $datum
        ];
    }
}

// Insert data into the database
global $wpdb;
$table_name = $wpdb->prefix . 'weather_data';

// Delete old data before inserting new data
$wpdb->query("DELETE FROM $table_name");

// Insert the new data into the database
foreach ($data as $row) {
    $wpdb->insert($table_name, $row);
}

echo "Weather data successfully imported into the database!";
