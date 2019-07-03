<?php
namespace redis;

class Redis
{
    protected $options = [
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'select'     => 0,
        'timeout'    => 0,
        'expire'     => 0,
        'persistent' => false,
        'prefix'     => '',
    ];

    /**
     * 架构函数 改造后的
     * @param array $options 缓存参数
     * @access public
     */
    public function __construct($options=array()) {
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }

        //加载redis配置
        $redisConfig = \Yaconf::get("redis");

        if(!empty($redisConfig)){
            $this->options = array_merge($this->options, $redisConfig);
        }

        // 自定义 接收参数
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }


        //p($this->options);
        $this->handler  = new \Redis;
        if($_SERVER['SERVER_ADDR'] == '127.0.0.1'){  // 本地
            $this->handler->connect('127.0.0.1','6379');
        }else{  //线上
            $func = $this->options['persistent'] ? 'pconnect' : 'connect';
            $this->handler->$func($this->options['host'], $this->options['port'], $this->options['timeout']);

            if ('' != $this->options['password']) {
                $this->handler->auth($this->options['password']);
            }

            if (0 != $this->options['select']) {
                $this->handler->select($this->options['select']);
            }
        }
    }

    /**
     * 判断缓存
     * @access public
     * @param string $name 缓存变量名
     * @return bool
     */
    public function has($name)
    {
        return $this->handler->get($this->getCacheKey($name)) ? true : false;
    }

    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get($name, $default = false)
    {
        $value = $this->handler->get($this->getCacheKey($name));
        if (is_null($value) || false === $value) {
            return $default;
        }

        try {
            $result = 0 === strpos($value, 'think_serialize:') ? unserialize(substr($value, 16)) : $value;
        } catch (\Exception $e) {
            $result = $default;
        }

        return $result;
    }

    /**
     * 写入缓存
     * @access public
     * @param string            $name 缓存变量名
     * @param mixed             $value  存储数据
     * @param integer|\DateTime $expire  有效时间（秒）
     * @return boolean
     */
    public function set($name, $value, $expire = null)
    {
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        if ($expire instanceof \DateTime) {
            $expire = $expire->getTimestamp() - time();
        }
        if ($this->tag && !$this->has($name)) {
            $first = true;
        }
        $key   = $this->getCacheKey($name);
        $value = is_scalar($value) ? $value : 'think_serialize:' . serialize($value);
        if ($expire) {
            $result = $this->handler->setex($key, $expire, $value);
        } else {
            $result = $this->handler->set($key, $value);
        }
        isset($first) && $this->setTagItem($key);
        return $result;
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function inc($name, $step = 1)
    {
        $key = $this->getCacheKey($name);

        return $this->handler->incrby($key, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function dec($name, $step = 1)
    {
        $key = $this->getCacheKey($name);

        return $this->handler->decrby($key, $step);
    }

    /**
     * 删除缓存
     * @access public
     * @param string $name 缓存变量名
     * @return boolean
     */
    public function rm($name)
    {
        return $this->handler->delete($this->getCacheKey($name));
    }

    /**
     * 清除缓存
     * @access public
     * @param string $tag 标签名
     * @return boolean
     */
    public function clear($tag = null)
    {
        if ($tag) {
            // 指定标签清除
            $keys = $this->getTagItem($tag);
            foreach ($keys as $key) {
                $this->handler->delete($key);
            }
            $this->rm('tag_' . md5($tag));
            return true;
        }
        return $this->handler->flushDB();
    }

    /**
     * 选择库
     * @param  [type] $id [description]
     * @return [type]     [description]
     */
    public function select($id)
    {
      $this->handler->select($id);
    }

 /**
     * 获取hash表key下所有值
     * @access public
     * @return boolean
     */
    public function hGetAll($c_name) {
        // $name   =   $this->options['prefix'].$name;
        // return $value.$name.$key;
        return $this->handler->hGetAll($c_name);
    }

     /**
     * 给哈希表中某个 key 设置值.如果值已经存在, 返回 false 
     * @access public
     * @return boolean
     */
    public function hSet($name, $key, $value) {
        if(is_null($expire)) {
            $expire  =  $this->options['expire'];
        }
        $name   =   $this->options['prefix'].$name;
        //对数组/对象数据进行缓存处理，保证数据完整性
        $value  =  (is_object($value) || is_array($value)) ? json_encode($value) : $value;

        $result = $this->handler->hSet($name, $key, $value);

        if($result && $this->options['length']>0) {
            // 记录缓存队列
            $this->queue($name);
        }
        return $result;
    }  

    /**
     * 删除哈希表中某个 key
     */
    public function hdel($name, $key)
    {
      return $this->handler->hdel($name,$key);
    }

    /**
     * 插入队列
     * @return boolean
     */
    public function rpush($name , $v){
        return $this->handler->rpush($name,json_encode($v,JSON_UNESCAPED_UNICODE));
    }
    /**
     * 出对列
     * @return value
     */
    public function lpop($name){
        $rs = $this->handler->lpop($name);
        return json_decode($rs); 
    }
    /**
     * 读取对列长度
     * @return value
     */
    public function Llen($name){
        $rs = $this->handler->Llen($name);
        return $rs; 
    }  

    /**
     * 为哈希表key中的域field的值加上增量increment。增量也可以为负数，相当于对给定域进行减法操作。
     * @access public
     * @return boolean
     */
    public function hincrby($name,$key,$value) {
        // $name   =   $this->options['prefix'].$name;
        // return $value.$name.$key;
        return $this->handler->hincrby($name,$key,$value);
    }

    /**
     * 验证指定的键是否存在
     * @access public
     * @return boolean
     */
    public function exists($name) {
        $name   =   $this->options['prefix'].$name;
        return $this->handler->exists($name);
    }

    /**
     * hash 插入一个数组
     * @zwr
     * @DateTime 2017-06-19T20:07:32+0800
     * @return   bool
     */
    public function hMset($name,$arr)
    {
        $rs = $this->handler->hMset($name,$arr);
        return $rs;
    }


   /**
    * 功能:     key值失效
    * @Author   Mrzhp
    * @email    18702529695@163.com
    * @DateTime 2018-06-28
    * @param    [type]              $key    [description]
    * @param    [type]              $expire [description]
    * @return   [type]                      [description]
    */
    public function expire($key,$expire)
    {
        $rs = $this->handler->expire($key,$expire);
        return $rs;
    }  

    public function setTimeout($name,$expire){
        $name   =   $this->options['prefix'].$name;
        return $this->handler->setTimeout( $name , $expire );
    } 
    public function Randomkey()
    {
        return $this->handler->randomkey();
    }    

    


}
