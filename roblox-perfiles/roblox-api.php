<?php
// Consulta pública limitada a las API oficiales de Roblox. PHP 8+ y cURL.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
function fail($code,$message){http_response_code($code);echo json_encode(['error'=>$message]);exit;}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail(405,'Método no permitido.');
if (!is_string($_GET['username'] ?? null)) fail(400,'Escribe un nombre de usuario.');
$name = ltrim(trim($_GET['username']),'@');
if (!preg_match('/^[A-Za-z0-9_]{3,20}$/D',$name)) fail(400,'Escribe un nombre de usuario válido, no el nombre visible.');
function api($url,$body=null){
  $c=curl_init($url);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json'],CURLOPT_USERAGENT=>'PublicProfileLookup/1.0']);
  if($body!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,json_encode($body));}
  $raw=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
  if($status===429) throw new RuntimeException('Roblox limita las consultas. Inténtalo más tarde.',429);
  if($raw===false || $status!==200) throw new RuntimeException('Roblox no está disponible temporalmente.',502);
  $data=json_decode($raw,true);if(!is_array($data)) throw new RuntimeException('Respuesta no válida de Roblox.',502);return $data;
}
try{
 if(!function_exists('curl_init')) fail(503,'Activa la extensión cURL de PHP.');
 $users=api('https://users.roblox.com/v1/usernames/users',['usernames'=>[$name],'excludeBannedUsers'=>true]);
 $u=$users['data'][0]??null;if(!$u) fail(404,'No se encontró ese usuario. Comprueba el @ exacto.');
 $id=(int)$u['id'];$images=[];
 foreach(['headshot'=>'avatar-headshot','avatar'=>'avatar'] as $key=>$type){
   try{$t=api('https://thumbnails.roblox.com/v1/users/'.$type.'?userIds='.$id.'&size=420x420&format=Png&isCircular=false');$item=$t['data'][0]??[];$url=$item['imageUrl']??null;$images[$key]=(($item['state']??'')==='Completed' && is_string($url) && preg_match('~^https://[^/]+\.rbxcdn\.com/~',$url))?$url:null;}catch(Throwable $e){$images[$key]=null;}
 }
 header('Cache-Control: public, max-age=60');echo json_encode(['id'=>$id,'name'=>$u['name'],'displayName'=>$u['displayName']??$u['name']]+$images,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){fail(in_array($e->getCode(),[429,502])?$e->getCode():502,$e instanceof RuntimeException?$e->getMessage():'No se pudo consultar Roblox.');}
