<?php

final class TeamWorkPm_Rest
{
    const FORMAT = 'json';

    private static
        $_instance;

    private
        $_key,
        $_company;

    private function  __construct($company, $key)
    {
        if (is_null($company) || is_null($key)) {
            throw new TeamWorkPm_Exception('set your company and api key');
        } else {
            $this->_key     = $key;
            $this->_company = $company;
        }
    }

    public function getInstance($company, $key)
    {
        if (null === self::$_instance) {
            self::$_instance = new self($company, $key);
        }

        return self::$_instance;
    }

    protected function _execute($method, $action, $request = null)
    {
        $url = "http://". $this->_company .".teamworkpm.net/". $action . '.' . self::FORMAT;
        $headers = array( "Authorization: BASIC ". base64_encode($this->_key .":xxx" ));
        switch ($method) {
            case '_get':
                if (null !== $request) {
                    if (is_array($request)) {
                        $request = $this->_getParametersAsString($request);
                    }
                    $url .= '?' . $request;
                }
                break;
            case '_put':
            case '_post':
                $headers = array_merge($headers, array(
                    'Content-Type: application/' . self::FORMAT
                ));
                break;
        }
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);

        return $this->$method($ch, $request);
    }
    /*
    public function get($action, $params = null)
    {
        return $this->_execute('GET', $action, $params );
    }

    public function post($action, $request)
    {
        return $this->_execute('POST', $action, $request);
    }

    public function put($action, $request)
    {
        return $this->_execute('PUT', $action, $request);
    }*/
    // public function get($action, $request = null);
    // public function post($action, $request
    // public function put($action, $request))

    /**
     * Ejecuta uno de los metodos descrito arriba
     * @param string $method
     * @param array $arguments
     * @return mixed
     */

    public function  __call($name, $arguments)
    {
        $name   = '_' . strtolower($name);
        if (method_exists($this, $name)) {
            list($action, $request) = $arguments;
            return $this->_execute($name, $action, $request);
        }
        return null;
    }

    private function _get($ch, $request = null)
    {
        return $this->_response($ch, true);
    }

    private function _put($ch, $request)
    {
        $length = strlen($request);
        if ($length) {
            $f = fopen('php://temp', 'rw');
            fwrite($f, $request);
            rewind($f);
            curl_setopt($ch, CURLOPT_INFILE, $f);
            curl_setopt($ch, CURLOPT_INFILESIZE, $length);
        }
        curl_setopt($ch, CURLOPT_PUT, true);
        $response = $this->_response($ch);
        if (isset ($f)) {
            fclose($f);
        }
        return $response;
    }

    private function _post($ch, $request)
    {
        curl_setopt( $ch, CURLOPT_POST, true );
        if ($request) {
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $request);
        }
        return $this->_response($ch);
    }

    private function _delete($ch, $request = null)
    {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        return $this->_response($ch);
    }

    private function _response($ch, $get = false)
    {
        $data = curl_exec ( $ch );
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_error($ch);
        curl_close($ch);
        if (self::FORMAT == 'xml') {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($data);
            if ($xml instanceof SimpleXMLElement) {
                if ($get) {
                    return $xml;
                } elseif ($xml[0] == 'OK' || $xml[0] == 'Created') {
                    return true;
                }
            }
        } elseif (self::FORMAT == 'json') {
            $response = json_decode($data, true);
            if ($status == 200 || $status == 201 || $response['status'] == 'OK' || $response['status'] == 'Created') {
                if ($get) {
                    unset ($response['status']);
                    return $response;
                }
                return true;
            }
        }

        return false;
    }

    private function _getParametersAsString(array $parameters)
    {
        $queryParameters = array();
        foreach ($parameters as $key => $value) {
            $queryParameters[] = $key . '=' . $this->_urlencode($value);
        }
        return implode('&', $queryParameters);
    }

    private function _urlencode($value)
    {
        return urlencode($value);
    }
}