<?php

error_reporting(E_ALL | E_STRICT);

function getConnection() {
        $dbhost = "10.52.21.3";
        $dbuser = "apenweb";
        $dbpass = "allen123";
        $dbname = "av";
        $dbcharset = 'utf8';
        $dbh = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=$dbcharset", $dbuser, $dbpass);        
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $dbh;
}

$app = new Phalcon\Mvc\Micro();

$app->get('/', function () {
        echo 'index.html';
});

$app->get('/api/play/{avkey:[0-9a-z-]+}/?{isb}?', function ($avkey, $isb) {
      $moresql = sprintf(' AND status = 1 AND createtime <=\'%s\' ', date('Y-m-d'));
      $avK = str_replace('-','',$avkey);
      if($avK == $avkey){
         $wheresql = ' avkey = :avkey ';
      }else{
         $wheresql = ' (avkey = :avkey or avkey = :avk) ';
      }
      $sql = sprintf("SELECT * FROM `video` WHERE %s %s LIMIT 1",$wheresql,$moresql);
      try {
              $db = getConnection();
              $stmt = $db->prepare($sql);
              $stmt->bindParam("avkey", $avkey);
              if($avK != $avkey){
                $stmt->bindParam("avk", $avk);
              }
              $stmt->execute();
              //$wine = $stmt->fetchObject();
              $info = $stmt->fetch(PDO::FETCH_ASSOC);
              $ext = ".flv";
              if($isdownload)
                $ext = ".avi";
              if($info['ismp4']==1)
                $ext = ".mp4";
              if($isb&&$row['bkey'])
                $info['videourl'] = getVideoUrl($info['bkey'].$ext,$info['serverid']);
              else
                $info['videourl'] = getVideoUrl($info['avkey'].$ext,$info['serverid']);

              $info['picurl'] = getPicUrl($info['avkey'],$info['serverid'],'b');
              //$wines = $stmt->fetchAll(PDO::FETCH_OBJ);
              $db = null;
              echo '{"info": ' . json_encode($info) . '}';
      } catch(PDOException $e) {
              echo '{"error":{"text":'. $e->getMessage() .'}}'; 
      }
});

$app->get('/api/index/{cid:[0-9]+}/{order:[0-9a-z]+}/{nowpage:[0-9]+}', function($cid, $order, $nowpage) {
        $perpage = 10;
        $p = $p > 1 ? intval($p - 1) * $perpage : 0;
        $orderkey = in_array('new', 'hot', 'scores') ? $order : 'new';
        $orderArr = array('new' => ' `video`.`createtime` ', 'hot' => ' `video`.`viewcount` ', 'scores' => ' `video`.`scores` ');
        $orderbysql = ' ORDER BY  '.$orderArr[$orderkey].' DESC ';
        $wheresql = ' WHERE `video`.`createtime` <= \''.date("Y-m-d"). '\' AND status =1 ';
        if($cid){
          if($cid == 11)
            $wheresql .= ' AND video.ismp4 = 1 ';
          else
            $wheresql .= sprintf(' AND video.cid = %d ', $cid);
        }
        $sql = sprintf("SELECT  `avkey` ,  `cid` ,  `title` ,  `viewcount` ,  `scores` ,  `collectcount` ,  `createtime` ,  `serverid` ,  `lastview` ,`username` , `isessence` ,`ismasked`
        FROM  `video` %s %s LIMIT %d , %d",$wheresql,$orderbysql,$p,$perpage);
        
        try {
                $db = getConnection();
                $stmt = $db->query($sql);  
                $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);  
                foreach($lists as &$val){
                  $val['picurl'] = getPicUrl($val['avkey'],$val['serverid'],'b');
                }
                $db = null;
                echo json_encode($lists); 
        } catch(PDOException $e) {
                echo '{"error":{"text":'. $e->getMessage() .'}}'; 
        }
});

$app->post('/api/wines', function () use ($app) {        
        $wine = json_decode($app->request->getRawBody());
        $sql = "INSERT INTO wine (name, grapes, country, region, year, description) VALUES (:name, :grapes, :country, :region, :year, :description)";
        try {
                $db = getConnection();
                $stmt = $db->prepare($sql);  
                $stmt->bindParam("name", $wine->name);
                $stmt->bindParam("grapes", $wine->grapes);
                $stmt->bindParam("country", $wine->country);
                $stmt->bindParam("region", $wine->region);
                $stmt->bindParam("year", $wine->year);
                $stmt->bindParam("description", $wine->description);
                $stmt->execute();
                $wine->id = $db->lastInsertId();
                $db = null;
                echo json_encode($wine); 
        } catch(PDOException $e) {
                error_log($e->getMessage(), 3, '/var/tmp/php.log');
                echo '{"error":{"text":'. $e->getMessage() .'}}'; 
        }
});


$app->handle();

function getChannelList(){
    $sql = 'SELECT  `cid` ,  `name` ,  `videocount` FROM  `channel` WHERE `state` = 1 ';
    $db = getConnection();
    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getVideoUrl($videoname,$serverid,$isvip=null){
    $f ="/".$videoname;
    $vip = $p = '';
    if($serverid === '136-170-2' || $serverid==='136-170')
      $p = ':8888';

    $secret = "iloveallen";
    if($isvip){
      $vip = "vip";
      $secret = "goldallen";
    }
    $http   = sprintf("http://1.fs%s.%sav.ckcdn.com%s",$serverid,$vip,$p);
    $uri_prefix = "/dl/";
    $t      = time();
    $t_hex  = sprintf("%08x", $t);
    $m      = md5($secret.$f.$t_hex);
    $videopath = sprintf('%s%s%s/%s%s',$http,$uri_prefix, $m, $t_hex, $f);
    return $videopath;
}

function getPicUrl($avkey = null,$serverid = 1 ,$pictype = 's'){
      $p = '';
      if($serverid === '136-170' || $serverid === '136-170-2')
        $p = ':8888';

      $imgPatt      = "http://%s.fs%s.av.ckcdn.com%s/%s-%s.jpg";
      $e = isset($avkey[4])?strtolower($avkey[4]):9;
      switch($e){
        default:
          $pre = 0;
        break;
        case '0':
        case '1':
        case '2':
        case '7':
          $pre = 0;
        break;
        case '3':
        case '4':
        case 'a':
        case 'b':
        case 'c':
        case 'd':
        case 'e':
        case 'f':
          $pre = 1;
        break;
        case '5':
        case '6':
        case 'g':
        case 'h':
        case 'i':
        case 'j':
        case 'k':
        case 'l':
        case 'm':
          $pre = 2;
        break;
        case '8':
        case '9':
        case 'n':
        case 'o':
        case 'p':
        case 'q':
        case 'r':
          $pre = 3;
        break;
        case 's':
        case 't':
        case 'u':
        case 'v':
        case 'w':
        case 'x':
        case 'y':
        case 'z':
          $pre = 4;
        break;
      }

      return sprintf($imgPatt,$pre,$serverid,$p,$avkey,$pictype);
  }
