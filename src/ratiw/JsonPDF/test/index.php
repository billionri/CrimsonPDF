<?php
// use these require statements if you point your http server directly to this folder.
require "../Fpdf.php";
require "../JsonPDF.php";

// use this require statement instead if you install JsonPDF via composer 
// and comment out the above require statements.
//require 'vendor/autoload.php';

    $pdf = new ratiw\JsonPDF\JsonPDF('P', 'mm', 'A4');
    $pdf->make($_POST['UserRequest']);
    $pdf->render();

?>
