<?php
/**
 * Created by PhpStorm.
 * User: 'xHai'
 * Date: 2019/6/12 0012
 * Time: 18:26
 */

namespace app\common\yacredis;

use app\common\service\ExternalApi;


class YacRedis
{
    /**
     * @param string $key   读的键
     * @return array|mixed
     */
    public function getYacRedis($key='',$selects=0)
    {
        //将空值转化为0 避免报错
        $select = ($selects==='')?0:$selects;


        //加载yac操作类
        $waveCache = new WaveCache();
        $localCache = new Cache();
        $localCache->prefix = 'wave:yac:';
        $waveCache->localCache = $localCache;

        //加载redis操作类
        $redis = ExternalApi::redisApi();
        $waveCache->redisCache = $redis;
        $waveCache->redisSelect = $select;
        $waveCache->redisPrefix = 'yacRedis:';

        //取值
        $menu = $waveCache->waveGet($key);
        return $menu;
    }

    /**
     * @param string $key   存的键
     * @param string $value 存的值
     * @param int $timeout  过期时间
     * @return array|mixed
     */
    public function setYacRedis($key='',$value='',$timeouts=0,$selects =0)
    {
        //将空值转化为0 避免报错
        $timeout = ($timeouts==='')?0:$timeouts;
        $select = ($selects==='')?0:$selects;

        //加载yac操作类
        $waveCache = new WaveCache();
        $localCache = new Cache();
        $localCache->prefix = 'wave:yac:';
        $waveCache->localCache = $localCache;

        //加载redis操作类
        $redis = ExternalApi::redisApi();
        $waveCache->redisCache = $redis;
        $waveCache->redisSelect = $select;
        $waveCache->redisPrefix = 'yacRedis:';

        //存值
        $menu = $waveCache->waveSet($key,$value,$timeout);
        return $menu;
    }
}