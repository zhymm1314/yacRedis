<?php
/**
 * Created by PhpStorm.
 * User: 'xHai'
 * Date: 2019/6/12 0012
 * Time: 10:29
 * 逻辑处理
 */

namespace wavecache;


class WaveCache
{
    /**
     * Notes:数据库值也没有取到值的状态值
     */
    public $dbEmpty= -1;

    /**
     * Notes:wave缓存真实存储时间倍数
     */
    public $saveTimes = 1;

    /**
     * @var
     */
    public $localCache;

    /**
     * @var
     */
    public $redisCache;

    /**
     * @var string
     */
    public $lockPrefix = 'redis:lock:';

    /**
     * @redis文件夹名
     */
    public $redisPrefix = 'yacRedis:';

    /**
     * @redis文件夹名
     */
    public $redisSelect;

    /**数据是否过期
     */
    private function _waveDataIsExpire($data,$time)
    {
        if(!isset($data['logicExpireAt'])){
            return true;
        }
        //过滤永久保存
        elseif ($data['timeout'] == 0){
            return false;
        }
        return $data['logicExpireAt'] <= $time;
    }
    
    /**数据格式转化
    */
    protected function _waveReturnData($data)
    {
       
        $data['data'] = ($data['data'] == $this->dbEmpty) ? [] : $data['data'];
        return $data['data'];
    }

    /**二级缓存获取数据
    */
    public function waveGet($key)
    {
        //优先使用yac缓存
        $yacResult = $this->localCache->get($key);

        //当前时间
        $time   = time();

        //本地读取到
        if(!empty($yacResult)) {
            $isExpire = $this->_waveDataIsExpire($yacResult,$time);

            //数据未过期
            if(!$isExpire) {
                return $this->_waveReturnData($yacResult);
            }
        }

        //yac没有读取到或者已经过期，都需要从redis读取
        $this->redisCache->select($this->redisSelect);
        $redisResult = $this->redisCache->get($this->redisPrefix.$key);

        //redis没有读取到返回 false
        if(empty($redisResult)) {
            return false;
        }

        //redis读取到
        $redisIsExpire = $this->_waveDataIsExpire($redisResult,$time);
        //p($redisIsExpire);

        //数据未过期
        if(!$redisIsExpire) {
            //其他端更新了redis缓存（yac中无，而redis中有数据）

            //过期时间
            $timeout = $redisResult['logicExpireAt']-$time;

            //存在redis 的已经过期了
            if($timeout<0){
                return false;
            }

            //将未过期的存在redis的值存入，并更改其 timeout （还有多久过期）
            $redisResult['timeout'] = $timeout;
            $this->localCache->set($key,$redisResult,$timeout);

            return $this->_waveReturnData($redisResult);
        }

        //redis数据已经过期
        return false;
    }

    /**刷新二级缓存
     */
    public function waveSet($key,$vuale,$timeout)
    {

        //格式化数据
        $data = [
            'data'           => $vuale,
            'logicExpireAt'  => time()+$timeout,
            'timeout'        => $timeout
        ];

        //更新redis缓存
        $this->redisCache->select($this->redisSelect);
        $now = $this->redisCache->set($this->redisPrefix.$key,$data,$this->saveTimes * $timeout);

        if($now){
            //更新yac缓存
            $this->localCache->set($key,$data,$timeout);
            return true;
        }
        return false;
    }
}