<?php

namespace Common\Lib;

/**
 * 加密
 * @package Common\Lib
 */
class Encrypt
{
    private $pub_key;
    private $priv_key;

    public function generate(){
        $config = array(
            "private_key_bits" => 1024
        );
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privKey);
        $pub_key_arr = openssl_pkey_get_details($res);
        $pub_key = $pub_key_arr['key'];

        $certification_path = './'.C("UPLOADPATH").'certification/';
        if(!is_dir($certification_path)){
            mkdir($certification_path,0777,TRUE);
        }
        file_put_contents($certification_path.'/priv_key.pem', $privKey);
        file_put_contents($certification_path.'/pub_key.pem', $pub_key);
    }

    public function publicEncrypt($data)
    {
        $crypto = '';
        foreach (str_split($data, 117) as $chunk) {
            openssl_public_encrypt($chunk, $encryptData, $this->pub_key);
            $crypto .= $encryptData;
        }
        return base64_encode($crypto);
    }

    public function privateDecrypt($data)
    {
        $crypto = '';
        $data = base64_decode($data);
        foreach (str_split($data, 128) as $chunk) {
            openssl_private_decrypt($chunk, $decryptData, $this->priv_key);
            $crypto .= $decryptData;
        }
        return $crypto;
    }

    public function set_public_key($key){
        $this->pub_key = $key;
    }

    public function set_private_key($key){
        $this->priv_key = $key;
    }

}