<?php
    function getToken($length){
        $token = "";
        $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
        $codeAlphabet.= "0123456789";
        $max = strlen($codeAlphabet); // edited

        for ($i=0; $i < $length; $i++) {
            $token .= $codeAlphabet[random_int(0, $max-1)];
        }

        return $token;
    }

    function get_method() {
        global $db;
        $temp['query'] = $db['conn'] -> prepare("SELECT value FROM settings WHERE name = 'CHECKIN_METHOD'");
        if (!$temp['query']->execute()) {
            $res->getBody()->write(json_encode([
                "status" => "error", 
                "desc" => "Error occured while fetching on database",
                "error" => $db['conn'] -> errorInfo(),
                "code" => $db['conn'] -> errorCode()
            ]));
            return $res;
        }
        $temp['res'] = $temp['query']->fetch(PDO::FETCH_ASSOC);
        return $temp['res']['value'];
    }

    //to do further: more dynamic function statement
    function check_profile($nrp, $token, $level = null) {
        global $db;
        $temp['query'] = $db['conn'] -> prepare("SELECT is_used, token, level FROM users WHERE nrp = :nrp");
        if (!$temp['query']->execute([
            ':nrp' => $nrp
        ])) return false;
        
        if ($temp['query'] -> rowCount() < 1) return false;
        
        $temp['res'] = $temp['query']->fetch(PDO::FETCH_ASSOC);
        if (!$temp['res']['is_used']) return false;
        if (is_int($level) && $temp['res']['level'] < $level ) return false;

        if ($temp['res']['is_used'] && $temp['res']['token'] == $token) return true;
        else return false;
    }

//return id of checkpoints from table; return 0 if user has finished the route
    function current_checkpoint($nrp, $route, $returnAll = false) {
        global $db;
        $temp['query'] = $db['conn'] -> prepare(
            "SELECT 
                history.id, history.cp_id, history.dt
            FROM history 
            INNER JOIN checkpoint ON history.cp_id = checkpoint.id
            WHERE history.user_nrp = :nrp AND history.is_finished = 0 
                AND checkpoint.route = :route AND checkpoint.active = 1
            ORDER BY checkpoint.sequence"
        );
        if (!$temp['query']->execute([
            ':nrp' => $nrp,
            ':route' => $route
        ])) return false;
        
        $passed = $temp['query'] -> rowCount();
        $temp['query'] = $db['conn'] -> prepare(
            "SELECT 
                COUNT(*) AS amount 
            FROM checkpoint 
            WHERE active = 1 AND route = :route"
        );
        if (!$temp['query']->execute([
            ':route' => $route
        ])) return false;
        $is_finished = ($temp['query'] -> fetch(PDO::FETCH_ASSOC)['amount'] < $passed + 1);
        if ($is_finished) return 0;
        if (!$returnAll) {
            if ($passed < 1) return ordered_checkpoint($route)['id'];
            return ordered_checkpoint($route, $passed + 1)['id'];
        } else {
            if ($passed < 1) return ordered_checkpoint($route);
            return ordered_checkpoint($route, $passed + 1);
        }
    }

    //experimental method
    function getSeq_checkpoint($nrp, $route) {
        global $db;
        $cur_cp = current_checkpoint($nrp);
        $temp['query'] = $db['conn'] -> prepare(
            "SELECT @curRow := @curRow + 1 AS seq 
                FROM checkpoint, (SELECT @curRow := 0) cRow 
                WHERE checkpoint.active = 1 AND checkpoint.id = :cur_cp
                    AND checkpoint.route = :route
                ORDER BY checkpoint.sequence 
                LIMIT 1"
            );
        if (!$temp['query']->execute([
            ':cur_cp' => $cur_cp,
            ':route' => $route
        ])) return false;
        $temp['res'] = $temp['query']->fetch(PDO::FETCH_ASSOC)['seq'];
        return $temp['res'];
    }

    function ordered_checkpoint($route, $seq = 1) {
        global $db;
        $temp['query'] = $db['conn'] -> prepare(
            "SELECT * FROM (
                SELECT checkpoint.*, @curRow := @curRow + 1 AS seq 
                FROM checkpoint, (SELECT @curRow := 0) cRow 
                WHERE checkpoint.active = 1 AND checkpoint.route = :route
                ORDER BY checkpoint.sequence
            ) a WHERE seq = :seq");
        if (!$temp['query']->execute([
            ':seq' => $seq,
            ':route' => $route
        ])) return null;        
        $temp['res'] = $temp['query']->fetch(PDO::FETCH_ASSOC);
        return $temp['res'];
    }

    function picSave($filename, $b64) {
        return file_put_contents(__DIR__.'/'.$filename, base64_decode($b64), FILE_APPEND);
    }


    function errQuery($res, $db/*, $state*/) {
        //global $db;
        $res->getBody()->write(json_encode([
            "status" => "error", 
            "desc" => "An Error occured while processing data",
            "error" => $db -> errorInfo(),
            "code" => $db -> errorCode()
        ]));
        return $res;
    }
    function errUnauthorized($res) {
        $res->getBody()->write(json_encode(["status" => "error", "desc" => "Not authorized"]));
        return $res;
    }
    function errReqData($res) {
        $res->getBody()->write(json_encode(["status" => "error", "desc" => "Data isn't in correct format"]));
        return $res;
    }
?>