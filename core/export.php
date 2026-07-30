<?php
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit();
}

function exportToPDF($html, $filename) {
    // Requires dompdf library
    // require_once 'vendor/autoload.php';
    // $dompdf = new Dompdf\Dompdf();
    // $dompdf->loadHtml($html);
    // $dompdf->setPaper('A4', 'portrait');
    // $dompdf->render();
    // $dompdf->stream($filename);
}
?>