<?php
    require __DIR__ . '/vendor/autoload.php';
    /*require __DIR__ . '/config.php';
    require __DIR__ . '/api_lib.php';*/

    use Endroid\QrCode\ErrorCorrectionLevel;
    use Endroid\QrCode\LabelAlignment;
    use Endroid\QrCode\QrCode;
    use Endroid\QrCode\Response\QrCodeResponse;

    error_reporting(E_ALL);

    $qr = new QrCode('Test 1234');
    $qr->setSize(320);
    $qr->setMargin(16);
    $qr->setEncoding('UTF-8');
    $qr->setLogoSize(92,92);
    $qr->setLogoPath(__DIR__.'/img/icon.jpg');
    $qr->setLabel('Home Yard', 12);
    $qr->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH());
    // header('Content-Type: '.$qr->getContentType());
    // echo $qr->writeString();
    echo hash("sha512", "-7.5649755,110.7634254");


    /*echo strtotime("now")."<br>";
    echo date("d m y - i H",1574306945)."<br>";
    echo strtotime("+1 day")."<br>";
    var_dump(getSeq_checkpoint('7916151'))."<br>";*/
    //var_dump(json_decode("{lat:-7.5642550,lng:110.7613420}, 	{lat:-7.5643330,lng:110.7613420}, 	{lat:-7.5643330,lng:110.7613740}, 	{lat:-7.5642550,lng:110.7613740}", true));

?>