<?php
    error_reporting(E_ALL);

    use Slim\Factory\AppFactory;
    use Slim\Middleware\OutputBufferingMiddleware; 

    use Psr\Http\Message\ResponseInterface as Response;
    use Psr\Http\Message\ServerRequestInterface as Request;

    use Endroid\QrCode\LabelAlignment;
    use Endroid\QrCode\QrCode;
    
    require __DIR__ . '/vendor/autoload.php';
    require __DIR__ . '/config.php';
    require __DIR__ . '/api_lib.php';

    $app = AppFactory::create();
    $qr = new QrCode('');
    //$app->setBasePath('/sgc.api');

    require __DIR__ . '/router.php';
    
    $app->run();
?>