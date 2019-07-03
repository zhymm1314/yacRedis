# yacRedis
介绍：基于yac和redis的简单的二级缓存
## composer下载方式
```
composer require yac-redis/yac-redis dev-master
```
## 准备工作
```
下载安装redis、yac 以及扩展

```
## 如何使用
```
setYacRedis($key,$select) 存值
getYacRedis($key,$value,$timeouts,$selects) 取值
$key 键名
$value 值
$selects 选择的redis的库 默认0
$timeouts 过期时间 默认无穷
```
