<?php
    class AppException {
        public $conn = null;
        public $res = null;
        function __construct($response) {
            $res = $response;
        }
        function Err_SQL() {
            $res -> getBody() -> write(json_encode([]));
        }
    }
?>