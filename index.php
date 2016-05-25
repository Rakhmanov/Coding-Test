<?php
/**
 * Coding Sample as part of interview.
 * Init the app
 * @url index.php?location_id=1&currency_code=CAD&amount=5245
 * Denis Shatilov <shatilov18@gmail.com>
 * 5/24/2016
 */

try {
    echo "<pre>";
    /**
     * Instantiating Processing Class
     */
    $app = new ProcessRequest($_REQUEST);
    $app->calculate();

} catch (Exception $e){
    /**
     * Display Exceptions
     */
    echo $e->getMessage();
}

/**
 * Class ProcessRequest
 */
class ProcessRequest{

    public $userLocationId;
    public $userCurrencyCode;
    public $userAmount;

    /**
     * ProcessRequest constructor.
     * @var $raw_input
     * @throws Exception
     */
    function __construct($raw_input)
    {
        /**
         * Get necessary parameters from request and sanitize them;
         */
        if (isset($raw_input['location_id']) && isset($raw_input['currency_code']) && isset($raw_input['amount'])){

            $this->userLocationId = Sys_Request::sanitizeInt($raw_input['location_id']);
            $this->userCurrencyCode = Sys_Request::sanitizeStr($raw_input['currency_code']);
            $this->userAmount = Sys_Request::sanitizeInt($raw_input['amount']);

        } else{
            throw new Exception('Please provide all required parameters');
        }

    }
    public function calculate (){
        $obj = new CXI_Inventory();

        return $obj->calculateDenom(
            $this->userCurrencyCode,
            $this->userLocationId,
            $this->userAmount
        );
    }
}


/**
 * Class Sys_Request
 * Class for sanitisation
 */
class Sys_Request{

    /**
     * @param int $integer
     * @return int || null
     * @throws Exception
     */
    public static function sanitizeInt($integer){
        $criteria = "/^[0-9]+/";
        $filtered = null;

        /* We want it to be a string just to make sure we don't return array */
        if (is_string($integer)){
            if(preg_match($criteria, $integer, $filtered)){
                return $filtered[0];
            }
        } else{
            throw new Exception('Not a String');
        }
        return false;
    }

    /**
     * @param $string
     * @return string || null
     * @throws Exception
     */
    public static function sanitizeStr($string){
        $criteria = "/^[a-zA-Z]+/";
        $filtered = null;

        if (is_string($string)){
            if(preg_match($criteria, $string, $filtered)){
                return $filtered[0];
            }
        }else {
            throw new Exception('Not a String');
        }
        return false;
    }
}

/**
 * Class CXI_Inventory
 */
class CXI_Inventory{

    /**
     * PSR 0 Method name Convention Capability
     * @param $currency_code
     * @param $location_id
     * @deprecated use getCurrencyDenoms
     * @return array denom_value => amount
     */
    public static function get_denoms_for($currency_code, $location_id){
        return self::getCurrencyDenoms($currency_code, $location_id);
    }

    /**
     * @param $currency_code
     * @param $location_id
     * @return array
     */
    private static function getCurrencyDenoms($currencyCode, $locationId){
        $db = new CXI_DB();

        $query = $db->prepare("SELECT denom_value, amount FROM `denom_inventory` WHERE currency_code = ?
          AND location_id = ? ORDER BY denom_value ASC");
        $query->execute([$currencyCode, $locationId]);
        $result = $query->fetchAll();

        if (!$result){
            throw new Exception('We cant get data from db');
        }
        /**
         * Take the results and form required format
         */

        foreach ($result as $value){
            $formatedResult[$value['denom_value']] = $value['amount'];
        }

        return $formatedResult;
    }

    /**
     * Sum of all available denominators
     * @param $currency
     * @return int|string
     */
    private function getTotalDenom($currency){
        $amount = 0;
        foreach ($currency as $key => $value){
            $amount += $key * $value;
        }
        return $amount;
    }

    /**
     * Calculating Denominators required for demanded amount
     * @param $currencyCode
     * @param $locationId
     * @param $userAmount
     * @return int|string
     * @throws Exception
     */
    public function calculateDenom($currencyCode, $locationId, $userAmount){
        $currencyInStock = $this->getCurrencyDenoms($currencyCode, $locationId);
        $totalDenomination = $this->getTotalDenom($currencyInStock);

        if ($userAmount > $totalDenomination){
            throw new Exception('We dont have enough money');
        }

        /* Check if we can divide the amount by our lowest denominator to produce whole number
           We know its ordered by the mysql query */
        if(is_int($userAmount / key($currencyInStock))) {
            /* Switching order to highest to lowest */
            $currencyInStock = array_reverse($currencyInStock, TRUE);

            $cashHanded = [];
            /* Go through denominators starting from highest and pay out*/
                    foreach ($currencyInStock as $key => $value){

                        if($userAmount/$key >= 1){

                            while ($userAmount > 0 && $userAmount/$key >= 1 && $value > 0){
                                $userAmount = $userAmount - $key;
                                @$cashHanded[$key]++;

                                $value--;
                            }
                            /* Break if there no more money that we owe */
                            if($userAmount == 0){
                                break;
                            }

                        }else{
                            /* Going to the next denominator*/
                            continue;
                        }
                    }

            echo "Cash Handed" .PHP_EOL;
            print_r($cashHanded);

        }else{
            throw new Exception('Amount is not dividable');
        }

        return $totalDenomination;
    }

}

/**
 * Class DB
 */
class CXI_DB extends PDO{
    private $config = [
        'driver' => 'mysql',
        'host'   => 'localhost',
        'dbname' => 'test',
        'user' => 'user',
        'pass' => 'password'
    ];

    function __construct() {
        $dbh =
            $this->config['driver'] . ':host=' .
            $this->config['host'] . ';dbname=' .
            $this->config['dbname'];

        parent::__construct($dbh, $this->config['user'], $this->config['pass']);

    }
}