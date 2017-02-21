# redis_client

Based on Yampee Redis Client "Copyright (c) 2013 Titouan Galopin"
https://github.com/yampee/Redis

Simple Redis Client for PHP 
- PHP 5.2+
- Single-file solution
- Auto-connect functionality
- No list or hash functions

Usage:
<pre>
require_once 'redis_client.php';
$redis = new Redis_Client('localhost', 6379);
</pre>
...start using redis...
