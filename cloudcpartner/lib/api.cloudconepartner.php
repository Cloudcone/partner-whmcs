<?php
class CloudConePartnerAPI
{
    private $base_url = 'https://api.cloudcone.com/api/v2';
    private $api_key;
    private $api_hash;

    public function __construct($partner_id, $api_key, $api_hash)
    {
        $this->partner_id = $partner_id;
        $this->api_key = $api_key;
        $this->api_hash = $api_hash;
    }

    private function sendRequest($path, $type, $params = array(), $errors = true)
    {
        $ch = curl_init($this->base_url . $path);
        if ($type === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } else if ($type === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else if ($type === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded',
            'App-Secret: ' . $this->api_key,
            'Hash: ' . $this->api_hash
        ));

        $return = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($return['status']) && $return['status'] > 0) {
            return json_encode($return);
        } else {
            if ($errors) {
                throw new Exception($return['message']);
            } else {
                return false;
            }
        }
    }

    public function getBrands()
    {
        return $this->sendRequest(
            "/partner/$this->partner_id/brands",
            'GET'
        );
    }

    public function computeCreate($cpu, $ram, $disk, $ip_count, $os_id, $hostname, $features = [])
    {
        $req_params = array_merge(array(
            'cpu' => $cpu,
            'ram' => $ram,
            'disk' => $disk,
            'ip_count' => $ip_count,
            'os_id' => $os_id,
            'hostname' => $hostname,
        ), $features);
        
        return $this->sendRequest(
            "/partner/$this->partner_id/compute",
            'POST',
            $req_params,
        );
    }

    public function computeResize($instanceid, $cpu, $ram, $disk)
    {
        return $this->sendRequest(
            "/partner/compute/$instanceid/resize",
            'POST',
            array(
                'cpu' => $cpu,
                'ram' => $ram,
                'disk' => $disk
            )
        );
    }

    public function computeSuspend($instanceid)
    {
        return $this->sendRequest(
            "/partner/compute/$instanceid/suspend",
            'POST'
        );
    }

    public function computeUnsuspend($instanceid)
    {
        return $this->sendRequest(
            "/partner/compute/$instanceid/unsuspend",
            'POST'
        );
    }

    public function computeDestroy($instanceid)
    {
        return $this->sendRequest(
            "/partner/compute/$instanceid/destroy",
            'DELETE'
        );
    }

    public function createAccessToken($instanceid)
    {
        return $this->sendRequest(
            "/partner/compute/$instanceid/token",
            'GET'
        );
    }
}
