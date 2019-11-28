<?php
    // use Psr\Http\Message\ResponseInterface as Response;
    // use Psr\Http\Message\ServerRequestInterface as Request;
    use Endroid\QrCode\ErrorCorrectionLevel;
    
    header('Access-Control-Allow-Origin: *');
    $app->get('/', function ($request, $response, $args) {
        $response->getBody()->write("API's for SGC");
        return $response;
    });
    $app->get('/test', function ($req, $res, $args) {
        $res->getBody()->write("[ {lat:-7.5642550,lng:110.7613420}, {lat:-7.5643330,lng:110.7613420}, {lat:-7.5643330,lng:110.7613740}, {lat:-7.5642550,lng:110.7613740} ]");
        return $res->withHeader('Content-Type', 'application/json');
    });
    $app->post('/register', function($req, $res, $args) {
    //$app->map(['OPTIONS', 'POST'], '/register', function($req, $res, $args) {
        //$data = json_decode(file_get_contents("php://input"), true);
        //$data = json_decode($req->getParsedBody(), true);
        global $db;
        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            /*->withStatus(200)*/;
            //->withHeader('Access-Control-Allow-Method', '');
        if ($data === null || !is_numeric($data["nrp"])) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Data isn't in correct format"]));
            return $res;
        }
        $db['query'] = $db['conn'] -> prepare("SELECT level, is_used FROM users WHERE nrp = :nrp");
        if (!$db['query']->execute([
            ':nrp' => $data["nrp"]
        ])) {
            $res->getBody()->write(json_encode([
                "status" => "error", 
                "desc" => "Error occured while querying on database",
                "error" => $db['conn'] -> errorInfo(),
                "code" => $db['conn'] -> errorCode()
            ]));
            return $res;
        }
        if ($db['query'] -> rowCount() < 1) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Invalid ID!"]));
            return $res;
        }
        $db['res'] = $db['query']->fetch(PDO::FETCH_ASSOC);
        if ($db['res']['is_used']) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "You can't register using this NRP, because it have been used by other user."]));
            return $res;
        }
        $token = getToken(255);
        $db['query'] = $db['conn'] -> prepare("UPDATE users SET is_used = 1, registration_date = :now, token = :token");
        if (!$db['query'] -> execute([
            ':now' => strtotime("now"),
            ':token' => $token
        ]) ) {
            $res->getBody()->write(json_encode([
                "status" => "error", 
                "desc" => "Error occured while querying on database",
                "error" => $db['conn'] -> errorInfo(),
                "code" => $db['conn'] -> errorCode()
            ]));
            return $res;
        }
        $res->getBody()->write(json_encode(["status" => "success", "token" => $token, "level" => $db['res']['level']]));
        return $res;
    });

    $app->post('/check_profile', function($req, $res, $args) {
        global $db;
        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            /*->withStatus(200)*/;
            //->withHeader('Access-Control-Allow-Method', '');
        if ($data === null || !isset($data["nrp"], $data["token"])) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Data isn't in correct format"]));
            return $res;
        }
        $db['query'] = $db['conn'] -> prepare("SELECT is_used, token FROM users WHERE nrp = :nrp");
        if (!$db['query']->execute([
            ':nrp' => $data["nrp"]
        ])) {
            $res->getBody()->write(json_encode([
                "status" => "error", 
                "desc" => "Error occured while querying on database",
                "error" => $db['conn'] -> errorInfo(),
                "code" => $db['conn'] -> errorCode()
            ]));
            return $res;
        }
        if ($db['query'] -> rowCount() < 1) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Invalid ID!"]));
            return $res;
        }
        $db['res'] = $db['query']->fetch(PDO::FETCH_ASSOC);
        if (!$db['res']['is_used']) {
            $res->getBody()->write(json_encode(["status" => "not_used"]));
            return $res;
        }

        if ($db['res']['is_used'] && $db['res']['token'] == $data["token"]) {
            $res->getBody()->write(json_encode(["status" => "success"]));
        } else {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Invalid token!"]));
        }
        return $res;
    });


    $app->post('/checkpoint', function($req, $res, $args) {
        global $db;

        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        $db['query'] = $db['conn'] -> prepare("SELECT id, name, latitude, longitude FROM checkpoint WHERE active = 1");
        if (!$db['query']->execute()) {
            $res->getBody()->write(json_encode([
                "status" => "error", 
                "desc" => "Error occured while fetching on database",
                "error" => $db['conn'] -> errorInfo(),
                "code" => $db['conn'] -> errorCode()
            ]));
            return $res;
        }
        $db['res'] = $db['query']->fetchAll(PDO::FETCH_ASSOC);
        $res->getBody()->write(json_encode([
            "status" => "success", "checkpoints" => $db['res'],
            "amount" => $db['query'] -> rowCount()
        ]));
        return $res;
    });

    $app->get('/checkpoint/qr', function($req, $res, $args) {
        global $db; global $qr;

        $query = $req->getQueryParams();

        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        if (!isset($query['id']))
            return $res->getBody()->write(json_encode(["status" => "error", "desc" => "Data isn't in correct format"]));
            
        $db['query'] = $db['conn'] -> prepare(
            "SELECT 
                name, latitude, longitude
            FROM checkpoint 
            WHERE id = :id AND active = 1"
        );
        if (!$db['query']->execute([
            ':id' => $query['id']
        ])) {
            $res->getBody()->write(json_encode([
                "status" => "error", 
                "desc" => "An Error occured while fetching data from the database",
                "error" => $db['conn'] -> errorInfo(),
                "code" => $db['conn'] -> errorCode()
            ]));
            return $res;
        }
        if ($db['query'] -> rowCount() < 1) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "ID not found!"]));
            return $res;
        }
        $db['res'] = $db['query'] -> fetch(PDO::FETCH_ASSOC);
        
        $qr->setText(hash('sha512',$db['res']['latitude'].','.$db['res']['longitude']));
        $qr->setSize(320);
        $qr->setMargin(16);
        $qr->setEncoding('UTF-8');
        $qr->setLogoSize(96,96);
        $qr->setLogoPath(__DIR__.'/img/icon.jpg');
        $qr->setLabel($db['res']['name'], 12);
        $qr->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH());

        $res->getBody()->write($qr -> writeString());
        return $res -> withHeader('Content-Type', $qr -> getContentType());
    });

    $app->post('/checkpoint/validate', function($req, $res, $args) {
        global $db;

        $data = $req -> getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');

        if (!isset($data['text'], $data['account']['nrp'], $data['account']['token'])) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Data isn't in correct format"]));
            return $res;
        }
        if (!check_profile($data['account']['nrp'], $data['account']['token']))  {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Not authorized"]));
            return $res;
        }
            
        $cur_cp = current_checkpoint($data['account']['nrp'], true);
        if (hash("sha512", $cur_cp['latitude'].",".$cur_cp['longitude']) != $data['text']) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Location isn't valid."]));
            return $res;
        }
        $res->getBody()->write(json_encode([
            "status" => "success", "name" => $cur_cp['name'], "region" => $cur_cp['region']
        ]));
        return $res;
    });

    $app->post('/regions', function($req, $res, $args) {
        global $db;

        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        $db['query'] = $db['conn'] -> prepare("SELECT id, name, region, color, latitude, longitude, description, mark_icon FROM checkpoint WHERE active = 1");
        if (!$db['query']->execute()) {
            $res->getBody()->write(json_encode([
                "status" => "error", 
                "desc" => "Error occured while fetching on database",
                "error" => $db['conn'] -> errorInfo(),
                "code" => $db['conn'] -> errorCode()
            ]));
            return $res;
        }
        $db['res'] = $db['query']->fetchAll(PDO::FETCH_ASSOC);

        $res->getBody()->write(json_encode([
            "status" => "success", "regions" => $db['res']
        ]));
        return $res;
    });
    
    $app->post('/use_method', function($req, $res, $args) {
        global $db;

        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        $method = get_method();
        $res->getBody()->write(json_encode([
            "status" => "success", "method" => $method
        ]));
        return $res;
    });

    $app->post('/checkin', function($req, $res, $args) {
        global $db;
        
        $method = get_method();
        $method = $method == "COMMON" ? 1 : 0;

        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');

        
        if (!isset($data['image'], $data['coor'], $data['account']['nrp'], $data['account']['token'])) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Data isn't in correct format"]));
            return $res;
        }
        if (!check_profile($data['account']['nrp'], $data['account']['token'])) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Not authorized"]));
            return $res;
        }
        $cur_cp = current_checkpoint($data['account']['nrp'], true);
        if ($cur_cp === 0) {
            $res->getBody()->write(json_encode(["status" => "success", "desc" => "You have finished the route! only 1 lap/day are allowed"]));
            return $res;
        }

        $db['query'] = $db['conn'] -> prepare(
            "INSERT INTO history VALUES(null, :cp_id, :user_nrp, :remark, :dt, :lat, :lng, :method)"
        );
        if (!$db['query']->execute([
            ':cp_id' => $cur_cp['id'],
            ':user_nrp' => $data['account']['nrp'],
            ':remark' => isset($data['remark']) ? $data['remark'] : "",
            ':dt' => strtotime('now'),
            ':lat' => $data['coor']['lat'],
            ':lng' => $data['coor']['lng'],
            ':method' => $method
        ])) {
            $res->getBody()->write(json_encode([
                "status" => "error", 
                "desc" => "An Error occured while inserting data to the database",
                "error" => $db['conn'] -> errorInfo(),
                "code" => $db['conn'] -> errorCode()
            ]));
            return $res;
        }
        $lastId = $db['conn'] -> lastInsertId();

        if ($method == 0) {
            if (picSave('img/history/'.$lastId.'.jpeg', $data['image']) === false) 
                return $res->getBody()->write(json_encode(["status" => "error", "desc" => "Can't save the captured image"]));
        }

        $cur_cp = current_checkpoint($data['account']['nrp'], true);
        if ($cur_cp === 0)
            $res->getBody()->write(json_encode(["status" => "success", "desc" => "You have finished the route! Good work for today!"]));
        else {
            $res->getBody()->write(json_encode([
                "status" => "success", "desc" => "The next checkpoint is <b>".$cur_cp['name']."</b>"
            ]));
        }
        return $res;
    });


    $app->post('/history/dt', function($req, $res, $args) {
        global $db;

        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');

        if (!isset(
            $data['account']['nrp'], $data['account']['token'],
            $data['draw'], $data['order'][0], $data['search']['value']
        )) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Data isn't in correct format"]));
            return $res;
        }
        if (!check_profile($data['account']['nrp'], $data['account']['token'], 1)) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Not authorized"]));
            return $res;
        }
        $draw = $data['draw'];
        $order_by = $data['order'][0]['column'];
        $order_as = $data['order'][0]['dir'];
        $search = $data['find'];
        $minDate = isset($data['date']) ? $data['date'] : strtotime('today');
        $maxDate = $minDate + 86400;

        switch($order_by) {
            case 1: $order_by = 'checkpoint_name'; break;
            case 2: $order_by = 'dt'; break;
            case 0: 
            default: $order_by = 'user_name'; break;
        }
        switch ($order_as) {
			case 'desc': $order_as = 'desc'; break;
			case 'asc':
			default: $order_as = 'asc'; break;
		}
        $db['query'] = $db['conn'] -> prepare(
            "SELECT COUNT(*) AS total FROM history WHERE dt >= :minDate AND dt < :maxDate"
        );
        if (!$db['query']->execute([
            ':minDate' => $minDate,
            ':maxDate' => $maxDate
        ])) {
            $res->getBody()->write(json_encode([
                "status" => "error", "desc" => "Error occured while fetching on database",
                "error" => $db['conn'] -> errorInfo(), "code" => $db['conn'] -> errorCode()
            ]));
            return $res;
        }
        $total_unfiltered = $db['query'] -> fetch(PDO::FETCH_ASSOC)['total'];
        $db['query'] = $db['conn'] -> prepare(
            "SELECT 
                history.id, checkpoint.name AS checkpoint_name, users.name AS user_name,
                history.dt, history.cp_id AS checkpoint_id, history.user_nrp AS user_nrp,
                checkpoint.region AS region, history.latitude, history.longitude, method
            FROM history 
            INNER JOIN checkpoint ON history.cp_id = checkpoint.id 
            INNER JOIN users ON history.user_nrp = users.nrp 
            WHERE 
                dt >= :minDate AND dt < :maxDate AND
                (checkpoint.name LIKE :search OR users.name LIKE :search)
            ORDER BY ".$order_by." ".$order_as
        );
        if (!$db['query']->execute([
            ':minDate' => $minDate,
            ':maxDate' => $maxDate,
            ':search' => '%'.$search.'%'
        ])) {
            $res->getBody()->write(json_encode([
                "status" => "error", 
                "desc" => "Error occured while fetching on database",
                "error" => $db['conn'] -> errorInfo(),
                "code" => $db['conn'] -> errorCode()
            ]));
            return $res;
        }
        $db['res'] = $db['query']->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach($db['res'] as $index => $val) {
            array_push($out, [
                'DT_RowData' => [
                    'history_id' => $val['id'],
                    'checkpoint_id' => $val['checkpoint_id'],
                    'user_nrp' => $val['user_nrp'],
                    'region' => $val['region'],
                    'coor' => [
                        'lat' => $val['latitude'],
                        'lng' => $val['longitude']
                    ]
                ],
                'user_name' => $val['user_name'],
                'checkpoint_name' => $val['checkpoint_name'],
                'time_taken' => date('H:i', $val['dt'])
            ]);
        }

        $res->getBody()->write(json_encode([
            "status" => "success", "draw" => intval($draw),
            "recordsTotal" => $total_unfiltered - $db['query'] -> rowCount(),
            "recordsFiltered" => $db['query'] -> rowCount(),
            "data" => $out, "test" => $db['res'], 
            "order_by" => $order_by, "order_as" => $order_as
        ]));
        return $res;
    });

    $app->post('/history/today_checkin', function($req, $res, $args) {
        global $db;

        $minDt = strtotime('today');
        $maxDt = $minDt + 86400;

        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');

        if (!isset($data['account']['nrp'], $data['account']['token'])) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Data isn't in correct format"]));
            return $res;
        }
        if (!check_profile($data['account']['nrp'], $data['account']['token'])) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Not authorized"]));
            return $res;
        }
        $cur_cp = current_checkpoint($data['account']['nrp'], false);
        $db['query'] = $db['conn'] -> prepare(
            "SELECT 
                cp_id
            FROM history 
            INNER JOIN checkpoint ON cp_id = checkpoint.id
            WHERE ".($cur_cp === 0 ? "" : "cp_id < :current_cp AND ")."
                checkpoint.active = 1 AND
                dt >= :min_dt AND dt < :max_dt AND user_nrp = :nrp
            "
        );
        if (!$db['query']->execute([
            ':current_cp' => $cur_cp,
            ':min_dt' => $minDt,
            ':max_dt' => $maxDt,
            ':nrp' => $data['account']['nrp']
        ])) {
            $res->getBody()->write(json_encode([
                "status" => "error", 
                "desc" => "An Error occured while fetching data from the database",
                "error" => $db['conn'] -> errorInfo(),
                "code" => $db['conn'] -> errorCode()
            ]));
            return $res;
        }
        $db['res'] = $db['query'] -> fetchAll(PDO::FETCH_ASSOC);
        $res->getBody()->write(json_encode([
            "status" => "success",
            "passed_cp" => $db['res']
        ]));
        return $res;
    });
    $app->post('/history/restart', function($req, $res, $args) {
        global $db;

        $minDt = strtotime('today');
        $maxDt = $minDt + 86400;

        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');

        if (!isset($data['time'], $data['account']['nrp'], $data['account']['token'])) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Data isn't in correct format"]));
            return $res;
        }
        if (!check_profile($data['account']['nrp'], $data['account']['token'])) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Not authorized"]));
            return $res;
        }
        
        $query = strtolower($data['time']) == "last" ? 
            "DELETE
            FROM history
            WHERE dt >= :min_dt AND dt < :max_dt AND user_nrp = :nrp
            ORDER BY id DESC LIMIT 1" : 
            "DELETE
            FROM history 
            WHERE dt >= :min_dt AND dt < :max_dt AND user_nrp = :nrp";
        $db['query'] = $db['conn'] -> prepare($query);
        if (!$db['query']->execute([
            ':min_dt' => $minDt,
            ':max_dt' => $maxDt,
            ':nrp' => $data['account']['nrp']
        ])) {
            $res->getBody()->write(json_encode([
                "status" => "error", 
                "desc" => "An Error occured while fetching data from the database",
                "error" => $db['conn'] -> errorInfo(),
                "code" => $db['conn'] -> errorCode()
            ]));
            return $res;
        }
        $res->getBody()->write(json_encode([
            "status" => "success"
        ]));
        return $res;
    });

    $app->post('/history/detail', function($req, $res, $args) {
        global $db;
        
        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');

        if (!isset($data['id'], $data['account']['nrp'], $data['account']['token'])) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Data isn't in correct format"]));
            return $res;
        }
        if (!check_profile($data['account']['nrp'], $data['account']['token'],1)) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Not authorized"]));
            return $res;
        }
        $db['query'] = $db['conn'] -> prepare(
            "SELECT 
                checkpoint.name AS checkpoint_name, users.name AS user_name,
                remark, dt, history.latitude, history.longitude, user_nrp
            FROM history 
            INNER JOIN checkpoint ON cp_id = checkpoint.id
            INNER JOIN users ON user_nrp = users.nrp
            WHERE history.id = :history_id"
        );
        if (!$db['query']->execute([
            ':history_id' => $data['id']
        ])) {
            $res->getBody()->write(json_encode([
                "status" => "error", 
                "desc" => "An Error occured while inserting data to the database",
                "error" => $db['conn'] -> errorInfo(),
                "code" => $db['conn'] -> errorCode()
            ]));
            return $res;
        }
        $db['res'] = $db['query'] -> fetch(PDO::FETCH_ASSOC);
        $res->getBody()->write(json_encode([
            "status" => "success", "remark" => htmlspecialchars($db['res']['remark']),
            "on" => '<b>'.$db['res']['checkpoint_name'].'</b>, &nbsp;'.date("d M Y - H:i:s", $db['res']['dt']), 
            "by" => '<b>'.$db['res']['user_name'].'</b> ('.substr_replace($db['res']['user_nrp'], '-', 6, 0).')',
            "lat" => $db['res']['latitude'],
            "lng" => $db['res']['longitude']
        ]));
        return $res;
    });
?>