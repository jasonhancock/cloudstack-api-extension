<?php

/*
 * Copyright (C) 2011 Jason Hancock http://geek.jasonhancock.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/.
 */

/*
 * CloudStackClient.php can be found as part of this project:
 * https://github.com/jasonhancock/cloudstack-php-client
 */
require_once('CloudStack/CloudStackClient.php');

// Adjust these paths as appropriate for your environment
$api = new ExtendedCloudAPI('/etc/cloud/management/db.properties', 'bundles.php');
$api->apicall($_GET);

class ExtendedCloudAPI {
    protected $api_key;
    protected $api_secret;
    protected $api_endpoint;
    protected $db_file;
    protected $dbh;
    protected $mime_type = 'text/javascript';

    public function __construct($db_file='/etc/cloud/management/db.properties', $bundle_file='bundles.php', $api_endpoint='http://localhost:8080/client/api') {
        $this->db_file = $db_file;
        $this->bundle_file = $bundle_file;
        $this->dbh = false;
        $this->api_endpoint=$api_endpoint;
        $this->headers();
    }

    protected function headers() {
        header("Content-Type: {$this->mime_type}");
    }

    /**
     * Pass method arguments by name. Adapted from original found at:
     * http://us3.php.net/manual/en/reflectionmethod.invokeargs.php#100041
     *
     * @param array $args
     * @return mixed
     */
    public function apicall(array $args = array()) {
        try {
            if(!isset($args['command']))
                throw new Exception('Command not set');
            $method = $args['command'];
            $this->checkSignature($args);
            if(!method_exists($this, $method))
                throw new Exception("The given command:$method does not exist");

            $reflection = new ReflectionMethod($this, $method);

            $pass = array();
            foreach($reflection->getParameters() as $param) {
                /* @var $param ReflectionParameter */
                if(isset($args[$param->getName()]))
                    $pass[] = $args[$param->getName()];
                else
                    $pass[] = $param->getDefaultValue();
            }

            $result = $reflection->invokeArgs($this, $pass);

            echo json_encode(array(
                    strtolower($method) . 'response' => $result
            ));
        } catch (Exception $e) {
            $method = isset($args['command']) ? $args['command'] : 'error';
            echo json_encode(array(
                strtolower($method) . 'response' => array(
                    'errorcode' => 500,
                    'errortext' => $e->getMessage()
                )
            ));
        }
    }

    public function checkSignature($args=array()) {
        if(!isset($args['apikey']))
            throw new Exception('apikey not set');
        $this->api_key = $args['apikey'];
        $this->getAPISecret();
        if(!$this->api_secret)
            throw new Exception('Unable to find api secret for api key');

        if(isset($args['signature'])) {
            $sig =  $args['signature'];
            unset($args['signature']);
        } else
            throw new Exception('No signature found');

        $computed_sig = $this->getSignature($args);
    
        return $computed_sig == $sig;
    }

    public function getSignature($args) {
        ksort($args);
        $query = http_build_query($args);
        $query = strtolower(str_replace('+', '%20', $query));
        return urlencode(base64_encode(@hash_hmac('SHA1', $query, $this->api_secret, true)));
    }

    public function getUserData($id) {
        $this->connectDB();

        if(!is_numeric($id))
            throw new Exception('Invalid ID');

        $q = sprintf('SELECT user_data from user_vm WHERE id=%d', $id);
        $result = mysql_query($q, $this->dbh) or throw new Exception(mysql_error($this->dbh));

        $data = ($row = mysql_fetch_row($result)) ? $row[0] : '';

        return array('userdata' => $data);
    }

    public function deployBundle($bundle) {
        $bundles = require_once($this->bundle_file);

        for($i=0; $i<count($bundles) && !isset($idx); $i++) {
            if($bundles[$i]['name'] == $bundle)
                $idx = $i;
        }

        if(!isset($idx))
            throw new Exception("Couldn't find bundle $bundle");

        $cloudstack = new CloudStackClient($this->api_endpoint, $this->api_key, $this->api_secret);

        $vm = $cloudstack->deployVirtualMachine(array(
            'serviceofferingid' => $bundles[$idx]['offeringid'],
            'templateid'        => $bundles[$idx]['templateid'],
            'zoneid'            => $bundles[$idx]['zoneid'],
            'diskofferingid'    => $bundles[$idx]['diskoffering'],
            'userdata'          => $bundles[$idx]['userdata']
        ));

        return $vm;
    }

    public function listBundles() {
        $bundles = require_once($this->bundle_file);
        return array('count' => count($bundles), 'bundle' => $bundles);
    }
    
    protected function getAPISecret() {
        $this->connectDB();

        $q = sprintf('SELECT secret_key from user WHERE api_key=\'%s\'', mysql_real_escape_string($this->api_key, $this->dbh));
        $result = mysql_query($q, $this->dbh) or throw new Exception(mysql_error($this->dbh));

        if($row = mysql_fetch_row($result)) {
            $this->api_secret = $row[0];
        } else
            $this->api_secret = false;
    }

    protected function connectDB() {
        if($this->dbh)
            return;

        $configs = array();
        $fh = fopen($this->db_file, 'r');

        while(!feof($fh)) {
            $line=rtrim(fgets($fh));
    
            if(preg_match('/^#/', $line))
                continue;

            if(preg_match('/(.+?)=(.+)/', $line, $matches))
                $configs[$matches[1]] = $matches[2];
        }

        fclose($fh);
        
        $this->dbh = mysql_connect(
            $configs['db.cloud.host'],
            $configs['db.cloud.username'],
            $configs['db.cloud.password'])
            or die(mysql_error($this->dbh));
        mysql_select_db($configs['db.cloud.name']) or throw new Exception(mysql_error($this->dbh));
    }
}
