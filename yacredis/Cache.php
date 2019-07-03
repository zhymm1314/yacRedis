<?php
/**
 * Created by PhpStorm.
 * User: 'xHai'
 * Date: 2019/6/12 0012
 * Time: 10:22
 * yac 缓存需要安装相应的扩展
 */
    
namespace cache;


class Cache
{
    /**
     * @var string
     */
    public $prefix = '_yac:';

    private  $_cache;

    public function getCache(){

        if(!($this->_cache instanceof \Yac)){
            $this->_cache = new \Yac($this->prefix);
        }
        return $this->_cache;
    }

    /**
     * 针对较长的key做has运算
     * @param $key
     * @return string
     */
    protected  function _formatKey($key){

        if(strlen($this->prefix.$key) > 48){
            return md5($key);
        }
        return $key;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $timeOut
     * @return mixed
     */
    public function set($key,$value,$timeOut=0){

        $key = $this->_formatKey($key);
        return $this->getCache()->set($key,$value,$timeOut);
    }

    /**
     * @param array $kvs
     * @param int $timeOut
     * @return mixed
     */
    public function mSet(array  $kvs,$timeOut=0){

        foreach ($kvs as $index => $kv) {
            $hashkeys[$this->_formatKey($index)] = $kv;
        }
        return $this->getCache()->set($hashkeys,$timeOut);
    }

    /**
     * @return mixed
     * @param $key
     */
    public function get($key){
        $key = $this->_formatKey($key);
        return $this->getCache()->get($key);
    }

    /**
     * @return mixed
     * @param array $keys
     */
    public function mget(array $keys){

        $hashkeys = [];
        foreach($keys as $values){

            $hashkeys[$this->_formatKey($values)] = $values;
        }
        unset($keys);
        $keyValues = $this->getCache()->get(array_keys($hashkeys));
        $data = [];
        foreach ($keyValues as $haskey => $value) {
            $data[$hashkeys[$haskey]] = $value;
        }
        return $data;
    }
    /**
     * @param $keys
     * @param int $delay
     * @return mixed
     */
    public function delete($keys,$delay=0){

        if(is_array($keys)){
            $hashkeys = array_map(function ($value){
                return $this->_formatKey($value);
            },$keys);
        }else{
            $hashkeys = $this->_formatKey($keys);
        }
        return $this->getCache()->delete($hashkeys,$delay);
    }

    /**
     * 所有缓存都失效
     * @return mixed
     */
    public function flush(){
        return $this->getCache()->flush();
    }

    /**
     * @return mixed
     */
    public function info(){
        return $this->getCache()->info();
    }
}