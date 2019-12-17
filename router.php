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
        if ($data === null || !is_numeric($data["nrp"])) return errReqData($res);
        $db['query'] = $db['conn'] -> prepare("SELECT level, is_used, name FROM users WHERE nrp = :nrp");
        if (!$db['query']->execute([
            ':nrp' => $data["nrp"]
        ])) return errQuery($res, $db['query']);
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
        $db['query'] = $db['conn'] -> prepare("UPDATE users SET is_used = 1, registration_date = :now, token = :token WHERE nrp = :nrp");
        if (!$db['query'] -> execute([
            ':now' => strtotime("now"),
            ':token' => $token,
            ':nrp' => $data['nrp']
        ]) ) return errQuery($res, $db['query']);
        $res->getBody()->write(json_encode(["status" => "success", "token" => $token, "level" => $db['res']['level'], "name" => $db['res']['name']]));
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
        if ($data === null || !isset($data["nrp"], $data["token"])) return errReqData($res);
        $db['query'] = $db['conn'] -> prepare("SELECT is_used, token FROM users WHERE nrp = :nrp");
        if (!$db['query']->execute([
            ':nrp' => $data["nrp"]
        ])) return errQuery($res, $db['query']);
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
        $db['query'] = $db['conn'] -> prepare(
            "SELECT 
                route, id, name, active
            FROM checkpoint
            /*WHERE active = 1*/
            ORDER BY route ASC, sequence ASC");
        if (!$db['query']->execute()) return errQuery($res, $db['query']);
        $db['res'] = $db['query']->fetchAll(PDO::FETCH_GROUP);
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
                name
            FROM checkpoint 
            WHERE id = :id AND active = 1"
        );
        if (!$db['query']->execute([
            ':id' => $query['id']
        ])) return errQuery($res, $db['query']);
        if ($db['query'] -> rowCount() < 1) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "ID not found!"]));
            return $res;
        }
        $db['res'] = $db['query'] -> fetch(PDO::FETCH_ASSOC);
        
        $qr->setText(hash('sha512',"[".$db['res']['name']."]"/*.','.$db['res']['longitude']*/));
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

        if (!isset($data['text'], $data['route'], $data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token']))  return errUnauthorized($res);
            
        $cur_cp = current_checkpoint($data['account']['nrp'], $data['route'], true);
        if (hash("sha512", "[".$cur_cp['name']."]") != $data['text']) {
            $res->getBody()->write(json_encode(["status" => "error", "desc" => "Location isn't valid."]));
            return $res;
        }
        $res->getBody()->write(json_encode([
            "status" => "success", "name" => $cur_cp['name']//, "region" => $cur_cp['region']
        ]));
        return $res;
    });
    $app->post('/checkpoint/reorder', function($req, $res, $args) {
        global $db;

        $data = $req -> getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');

        if (!isset($data['checkpoints'], $data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'], 2))  return errUnauthorized($res);
        if (!is_array($data['checkpoints'])) return errReqData($res);
        $db['query'] = $db['conn'] -> prepare(
            'UPDATE checkpoint SET sequence = :seq WHERE id = :id'
        );
        foreach ($data['checkpoints'] as $index => $val) {
            if (!$db['query']->execute([
                ':id' => $val['id'],
                ':seq' => $val['seq']
            ])) return errQuery($res, $db['query']);
        }
        $res->getBody()->write(json_encode([
            "status" => "success"
        ]));
        return $res;
    });

    $app->post('/checkpoint/new', function($req, $res, $args) {
        global $db;
        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        if (!isset($data['input']['name'], $data['input']['route'], $data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'], 2)) return errUnauthorized($res);
        $db['query'] = $db['conn'] -> prepare(
            "INSERT INTO checkpoint 
            VALUES(null, :route, :name, :state, (
                SELECT COALESCE(MAX(sequence), 0) + 1 FROM checkpoint AS cp WHERE route = :route
            ))"
        );
        if (!$db['query']->execute([
            ':route' => $data['input']['route'],
            ':name' => $data['input']['name'],
            ':state' => isset($data['input']['state'][0]) ? 1 : 0
        ])) return errQuery($res, $db['query']);
        $res->getBody()->write(json_encode([
            "status" => "success"
        ]));
        return $res;
    });

    $app->post('/checkpoint/edit', function($req, $res, $args) {
        global $db;
        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        if (!isset($data['input']['route'], $data['input']['name'], $data['previous_data'], $data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'], 2)) return errUnauthorized($res);
        $db['query'] = $db['conn'] -> prepare(
            "SELECT route, sequence FROM checkpoint WHERE id = :prev_id"
        );
        if (!$db['query']->execute([
            ':prev_id' => $data['previous_data']
        ])) return errQuery($res, $db['query']);
        $db['res'] = $db['query'] -> fetch(PDO::FETCH_ASSOC);
        if ($data['input']['route'] == $db['res']['route']) {
            $db['query'] = $db['conn'] -> prepare(
                "UPDATE checkpoint SET route = :route, name = :name, active = :state WHERE id = :prev_id"
            );
        } else {
            $db['query'] = $db['conn'] -> prepare(
                "UPDATE checkpoint 
                SET route = :route, name = :name, 
                    active = :state, 
                    sequence = (
                        SELECT COALESCE(MAX(cp.sequence), 0) + 1 
                        FROM (SELECT sequence, route FROM checkpoint AS ref_cp) AS cp 
                        WHERE cp.route = :route
                    )
                WHERE id = :prev_id"
            );
        }
        if (!$db['query']->execute([
            ':route' => $data['input']['route'],
            ':name' => $data['input']['name'],
            ':state' => isset($data['input']['state'][0]) ? 1 : 0,
            ':prev_id' => $data['previous_data']
        ])) return errQuery($res, $db['query']);
        
        if ($data['input']['route'] != $db['res']['route']) {
            $db['query'] = $db['conn'] -> prepare(
                "UPDATE checkpoint SET sequence = (sequence - 1) WHERE route = :route AND sequence > :seq"
            );
            if (!$db['query']->execute([
                ':route' => $db['res']['route'],
                ':seq' => $db['res']['sequence']
            ])) return errQuery($res, $db['query']);
        }
        
        $res->getBody()->write(json_encode([
            "status" => "success"
        ]));
        return $res;
    });

    $app->post('/checkpoint/remove', function($req, $res, $args) {
        global $db;
        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        if (!isset($data['id'], $data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'], 2)) return errUnauthorized($res);
        $db['query'] = $db['conn'] -> prepare(
            "SELECT route, sequence FROM checkpoint WHERE id = :prev_id"
        );
        if (!$db['query']->execute([
            ':prev_id' => $data['id']
        ])) return errQuery($res, $db['query']);
        $db['res'] = $db['query'] -> fetch(PDO::FETCH_ASSOC);
        $db['query'] = $db['conn'] -> prepare(
            "DELETE FROM checkpoint WHERE id = :id"
        );
        if (!$db['query']->execute([
            ':id' => $data['id']
        ])) return errQuery($res, $db['query']);
        $db['query'] = $db['conn'] -> prepare(
            "DELETE FROM history WHERE cp_id = :id"
        );
        if (!$db['query']->execute([
            ':id' => $data['id']
        ])) return errQuery($res, $db['query']);
        $db['query'] = $db['conn'] -> prepare(
            "UPDATE checkpoint SET sequence = (sequence - 1) WHERE route = :route AND sequence > :seq"
        );
        if (!$db['query']->execute([
            ':route' => $db['res']['route'],
            ':seq' => $db['res']['sequence']
        ])) return errQuery($res, $db['query']);
        $res->getBody()->write(json_encode([
            "status" => "success"
        ]));
        return $res;
    });

    $app->post('/regions', function($req, $res, $args) {
        global $db;

        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        $db['query'] = $db['conn'] -> prepare("SELECT id, name, region, color, latitude, longitude, description, mark_icon FROM checkpoint WHERE active = 1");
        if (!$db['query']->execute()) return errQuery($res, $db['query']);
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
        $method = ($method == "COMMON" ? 1 : 0);

        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');

        
        if (!isset($data['image'], $data['coor'], $data['route'], $data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'])) return errUnauthorized($res);
        $cur_cp = current_checkpoint($data['account']['nrp'], $data['route'], true);
        if ($cur_cp === 0) {
            $res->getBody()->write(json_encode(["status" => "success", "desc" => "You have finished the route! only 1 lap/day are allowed"]));
            return $res;
        }

        $db['query'] = $db['conn'] -> prepare(
            "INSERT INTO history 
            VALUES(null, :cp_id, :user_nrp, :remark, :dt, :lat, :lng, :method, 0)"
        );
        if (!$db['query']->execute([
            ':cp_id' => $cur_cp['id'],
            ':user_nrp' => $data['account']['nrp'],
            ':remark' => isset($data['remark']) ? $data['remark'] : "",
            ':dt' => strtotime('now'),
            ':lat' => $data['coor']['lat'],
            ':lng' => $data['coor']['lng'],
            ':method' => $method
        ])) return errQuery($res, $db['query']);
        $lastId = $db['conn'] -> lastInsertId();

        if ($method == 0) {
            if (picSave('img/history/'.$lastId.'.jpeg', $data['image']) === false) 
                return $res->getBody()->write(json_encode(["status" => "error", "desc" => "Can't save the captured image"]));
        }

        $cur_cp = current_checkpoint($data['account']['nrp'], $data['route'], true);
        if ($cur_cp === 0) {
            $db['query'] = $db['conn'] -> prepare(
                "UPDATE history 
                INNER JOIN checkpoint ON history.cp_id = checkpoint.id
                SET is_finished = 1 
                WHERE user_nrp = :nrp AND route = :route AND is_finished = 0"
            );
            if (!$db['query']->execute([
                ':nrp' => $data['account']['nrp'],
                ':route' => $data['route']
            ])) return errQuery($res, $db['query']);
            $res->getBody()->write(json_encode(["status" => "success", "desc" => "Congrats! You have finished the route!"]));
        }
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
        )) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'], 1)) return errUnauthorized($res);
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
        ])) return errQuery($res, $db['query']);
        $total_unfiltered = $db['query'] -> fetch(PDO::FETCH_ASSOC)['total'];
        $db['query'] = $db['conn'] -> prepare(
            "SELECT 
                history.id, checkpoint.name AS checkpoint_name, users.name AS user_name,
                history.dt, history.cp_id AS checkpoint_id, history.user_nrp AS user_nrp,
                checkpoint.route,
                /*checkpoint.region AS region,*/ history.latitude, history.longitude, method
            FROM history 
            INNER JOIN checkpoint ON history.cp_id = checkpoint.id 
            INNER JOIN users ON history.user_nrp = users.nrp 
            WHERE 
                dt >= :minDate AND dt < :maxDate AND
                (checkpoint.name LIKE :search OR users.name LIKE :search)
            ORDER BY route, ".$order_by." ".$order_as
        );
        if (!$db['query']->execute([
            ':minDate' => $minDate,
            ':maxDate' => $maxDate,
            ':search' => '%'.$search.'%'
        ])) return errQuery($res, $db['query']);
        $db['res'] = $db['query']->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach($db['res'] as $index => $val) {
            array_push($out, [
                'DT_RowData' => [
                    'history_id' => $val['id'],
                    'checkpoint_id' => $val['checkpoint_id'],
                    'user_nrp' => $val['user_nrp'],
                    //'region' => $val['region'],
                    'coor' => [
                        'lat' => $val['latitude'],
                        'lng' => $val['longitude']
                    ],
                    'checkpoint_name' => $val['checkpoint_name']
                ],
                'user_name' => $val['user_name'],
                'checkpoint_name' => $val['checkpoint_name'],
                'time_taken' => date('H:i', $val['dt']),
                'route' => $val['route']
            ]);
        }

        $res->getBody()->write(json_encode([
            "status" => "success", "draw" => intval($draw),
            "recordsTotal" => $total_unfiltered - $db['query'] -> rowCount(),
            "recordsFiltered" => $db['query'] -> rowCount(),
            "data" => $out
        ]));
        return $res;
    });

    $app->post('/history/user', function($req, $res, $args) {
        global $db;

        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');

        if (!isset($data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'])) return errUnauthorized($res);
        $db['query'] = $db['conn'] -> prepare(
            "SELECT 
                cp_id
            FROM history 
            INNER JOIN checkpoint ON cp_id = checkpoint.id
            WHERE checkpoint.active = 1 AND user_nrp = :nrp AND
                is_finished = 0"
        );
        if (!$db['query']->execute([
            ':nrp' => $data['account']['nrp']
        ])) return errQuery($res, $db['query']);
        $db['res'] = $db['query'] -> fetchAll(PDO::FETCH_ASSOC);
        $res->getBody()->write(json_encode([
            "status" => "success",
            "passed_cp" => $db['res']
        ]));
        return $res;
    });
    $app->post('/history/user/current', function($req, $res, $args) {
        global $db;

        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');

        if (!isset($data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'])) return errUnauthorized($res);
        $db['query'] = $db['conn'] -> prepare(
            "SELECT 
                route
            FROM history 
            INNER JOIN checkpoint ON cp_id = checkpoint.id
            WHERE checkpoint.active = 1 AND user_nrp = :nrp AND
                is_finished = 0 ORDER BY history.id DESC LIMIT 1"
        );
        if (!$db['query']->execute([
            ':nrp' => $data['account']['nrp']
        ])) return errQuery($res, $db['query']);
        $db['res'] = $db['query'] -> fetch(PDO::FETCH_ASSOC);
        $cur_cp = current_checkpoint($data['account']['nrp'], $db['res']['route'], true);
        $res->getBody()->write(json_encode([
            "status" => "success",
            "current_cp" => $cur_cp
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

        if (!isset($data['time'], $data['route'], $data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'])) return errUnauthorized($res);
        
        $query = strtolower($data['time']) == "last" ? 
            "DELETE history
            FROM history, (
                SELECT history.id
                FROM history 
                INNER JOIN checkpoint ON history.cp_id = checkpoint.id
                WHERE checkpoint.active = 1 AND history.user_nrp = :nrp AND checkpoint.route = :route AND history.is_finished = 0
                ORDER BY checkpoint.sequence DESC LIMIT 1
            ) AS refCP
            WHERE history.id = refCP.id" : 
            "DELETE history
            FROM history 
            INNER JOIN checkpoint ON history.cp_id = checkpoint.id
            WHERE is_finished = 0 AND user_nrp = :nrp AND checkpoint.route = :route";
        $db['query'] = $db['conn'] -> prepare($query);
        if (!$db['query']->execute([
            ':nrp' => $data['account']['nrp'],
            ':route' => $data['route']
        ])) return errQuery($res, $db['query']);
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

        if (!isset($data['id'], $data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'],1)) return errUnauthorized($res);
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
        ])) return errQuery($res, $db['query']);
        $db['res'] = $db['query'] -> fetch(PDO::FETCH_ASSOC);
        $res->getBody()->write(json_encode([
            "status" => "success", "remark" => htmlspecialchars($db['res']['remark']),
            "on" => '<b>'.$db['res']['checkpoint_name'].'</b>, &nbsp;'.date("d M Y - H:i:s", $db['res']['dt']), 
            "by" => '<b>'.$db['res']['user_name'].'</b> ('.substr_replace($db['res']['user_nrp'], '-', 6, 0).')',
            "lat" => $db['res']['latitude'],
            "lng" => $db['res']['longitude'],
            "cp_name" => $db['res']['checkpoint_name']
        ]));
        return $res;
    });

    $app->post('/settings/switch_mode', function($req, $res, $args) {
        global $db;

        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');

        if (!isset($data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'], 2)) return errUnauthorized($res);
        
        $method = get_method();
        $db['query'] = $db['conn'] -> prepare(
            "INSERT INTO settings VALUES(\"Q_CHECKIN_METHOD\", :val)"
        );
        if (!$db['query']->execute([
            ':val' => $method == "CLASSIC" ? "COMMON" : "CLASSIC"
        ])) return errQuery($res, $db['query']);
        $res->getBody()->write(json_encode([
            "status" => "success"
        ]));
        return $res;
    });


    $app->post('/users/dt', function($req, $res, $args) {
        global $db;

        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');

        if (!isset(
            $data['account']['nrp'], $data['account']['token'],
            $data['draw'], $data['order'][0], $data['search']['value']
        )) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'], 2)) return errUnauthorized($res);
        $draw = $data['draw'];
        $order_by = $data['order'][0]['column'];
        $order_as = $data['order'][0]['dir'];
        $search = $data['find'];

        switch($order_by) {
            case 1: $order_by = 'name'; break;
            case 2: $order_by = 'registration_date'; break;
            case 0: 
            default: $order_by = 'nrp'; break;
        }
        switch ($order_as) {
			case 'desc': $order_as = 'desc'; break;
			case 'asc':
			default: $order_as = 'asc'; break;
		}
        $db['query'] = $db['conn'] -> prepare(
            "SELECT COUNT(*) AS total FROM users"
        );
        if (!$db['query']->execute()) return errQuery($res, $db['query']);
        $total_unfiltered = $db['query'] -> fetch(PDO::FETCH_ASSOC)['total'];
        $db['query'] = $db['conn'] -> prepare(
            "SELECT 
                nrp, name, level, is_used, registration_date
            FROM users
            WHERE 
                nrp LIKE :search OR name LIKE :search
            ORDER BY ".$order_by." ".$order_as
        );
        if (!$db['query']->execute([
            ':search' => '%'.$search.'%'
        ])) return errQuery($res, $db['query']);
        $db['res'] = $db['query']->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach($db['res'] as $index => $val) {
            array_push($out, [
                'DT_RowData' => [
                    'is_used' => $val['is_used'],
                    'registration_date' => $val['registration_date'] * 1000,
                    'name' => $val['name'],
                    'nrp' => $val['nrp'],
                    'level' => $val['level']
                ],
                'name' => $val['name'],
                'nrp' => $val['nrp'],
                'level' => $val['level']
            ]);
        }

        $res->getBody()->write(json_encode([
            "status" => "success", "draw" => intval($draw),
            "recordsTotal" => $total_unfiltered - $db['query'] -> rowCount(),
            "recordsFiltered" => $db['query'] -> rowCount(),
            "data" => $out
        ]));
        return $res;
    });

    $app->post('/users/new', function($req, $res, $args) {
        global $db;
        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        if (!isset($data['input']['nrp'], $data['input']['name'], $data['input']['level'], $data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'], 2)) return errUnauthorized($res);
        $db['query'] = $db['conn'] -> prepare(
            "INSERT INTO users VALUES(:nrp, :name, :level, 0, unix_timestamp(), \"\")"
        );
        if (!$db['query']->execute([
            ':nrp' => str_replace('-','', $data['input']['nrp']),
            ':name' => $data['input']['name'],
            ':level' => $data['input']['level']
        ])) return errQuery($res, $db['query']);
        $res->getBody()->write(json_encode([
            "status" => "success"
        ]));
        return $res;
    });

    $app->post('/users/edit', function($req, $res, $args) {
        global $db;
        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        if (!isset($data['input']['nrp'], $data['input']['name'], $data['input']['level'], $data['previous_data'], $data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'], 2)) return errUnauthorized($res);
        $db['query'] = $db['conn'] -> prepare(
            "UPDATE users SET nrp = :nrp, name = :name, level = :level WHERE nrp = :prev_nrp"
        );
        if (!$db['query']->execute([
            ':nrp' => str_replace('-','', $data['input']['nrp']),
            ':name' => $data['input']['name'],
            ':level' => $data['input']['level'],
            ':prev_nrp' => $data['previous_data']
        ])) return errQuery($res, $db['query']);
        if (str_replace('-','', $data['input']['nrp']) != $data['previous_data']) {
            $db['query'] = $db['conn'] -> prepare(
                "UPDATE history SET user_nrp = :nrp WHERE user_nrp = :prev_nrp"
            );
            if (!$db['query']->execute([
                ':nrp' => str_replace('-','', $data['input']['nrp']),
                ':prev_nrp' => $data['previous_data']
            ])) return errQuery($res, $db['query']);
        }
        $res->getBody()->write(json_encode([
            "status" => "success"
        ]));
        return $res;
    });

    $app->post('/users/remove', function($req, $res, $args) {
        global $db;
        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        if (!isset($data['nrp'], $data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'], 2)) return errUnauthorized($res);
        $db['query'] = $db['conn'] -> prepare(
            "DELETE FROM users WHERE nrp = :nrp"
        );
        if (!$db['query']->execute([
            ':nrp' => $data['nrp']
        ])) return errQuery($res, $db['query']);
        $db['query'] = $db['conn'] -> prepare(
            "DELETE FROM history WHERE user_nrp = :nrp"
        );
        if (!$db['query']->execute([
            ':nrp' => $data['nrp']
        ])) return errQuery($res, $db['query']);
        $res->getBody()->write(json_encode([
            "status" => "success"
        ]));
        return $res;
    });

    $app->post('/users/unreg', function($req, $res, $args) {
        global $db;
        $data = $req->getParsedBody();
        $res = $res->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        if (!isset($data['nrp'], $data['account']['nrp'], $data['account']['token'])) return errReqData($res);
        if (!check_profile($data['account']['nrp'], $data['account']['token'], 2)) return errUnauthorized($res);
        $db['query'] = $db['conn'] -> prepare(
            "UPDATE users SET is_used = 0 WHERE nrp = :nrp"
        );
        if (!$db['query']->execute([
            ':nrp' => $data['nrp']
        ])) return errQuery($res, $db['query']);
        $res->getBody()->write(json_encode([
            "status" => "success"
        ]));
        return $res;
    });
?>