<?php

namespace Luracast\Restler {
    class RestException extends \Exception
    {
        public function __construct($status = 500, $message = '', $code = 0, \Throwable $previous = null)
        {
            parent::__construct((string) $message, (int) $status, $previous);
        }
    }
}

namespace {
    class DolibarrApi
    {
        /** @var mixed */
        protected $db;

        public function __construct($db = null)
        {
            $this->db = $db;
        }

        protected function _cleanObjectDatas($object)
        {
            return $object;
        }
    }

    class DolibarrApiAccess
    {
        /** @var User|null */
        public static $user;
    }

    if (!function_exists('forgeSQLFromUniversalSearchCriteria')) {
        function forgeSQLFromUniversalSearchCriteria($sqlfilters, &$errorMessage = '', $noand = 1)
        {
            $errorMessage = '';

            return '';
        }
    }
}
