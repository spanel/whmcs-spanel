<?php

# Riap::HTTP client to connect to server over Unix sockets.
#
# This software is copyright (c) 2014 by Steven Haryanto,
# <stevenharyanto@gmail.com>.
#
# This is free software; you can redistribute it and/or modify it under the
# Artistic License 2.0.
#
# See also `phi_access_http_client.inc.php`. We currently need a separate lib
# because curl doesn't seem to support Unix sockets yet.

# just for PHINCI_VERSION constant
require_once "phi_access_http_client.inc.php";

function phi_http_request_unix($action, $url, $extra=array(), $copts=array()) {
  global $PHINCI_VERSION;

  # check url
  if (!preg_match('!^https?:!', $url)) {
    return array(400, 'Please supply http URL');
  }
  if (preg_match('!^https:!', $url)) {
    return array(400, 'Only http URL is supported, not https');
  }
  if (preg_match('!^http://!', $url) ||
      !preg_match('!^http:(.+?)//([^?]*)!', $url, $m)) {
    return array(400, 'Invalid URL syntax, please use: '.
                 'http:/path/to/unix/socket//uri/path');
  }
  $sock_path = $m[1];
  $uri_path = "/$m[2]";

  # copts
  $retries     = isset($copts['retries'])     ? $copts['retries']     : 2;
  $retry_delay = isset($copts['retry_delay']) ? $copts['retry_delay'] : 3;

  while (1) {

    $tries = 0;
    $daemon_started = 0;
    while ($tries <= $retries) {

      $fp = @fsockopen("unix://$sock_path", -1, $errno, $errstr);
      if (!$fp) {
        sleep($retry_delay); $tries++; continue;
      }

      fputs($fp, "POST $uri_path HTTP/1.0\n");
      #echo "DEBUG: Sending request line: POST $uri_path HTTP/1.0\n";

      # form riap request
      $rreq = array('action' => $action, 'ua' => "Phinci/$PHINCI_VERSION");
      foreach($extra as $k => $v) { $rreq[$k] = $v; }

      # put all riap request keys, except some like args, to http headers
      $headers = array();
      foreach ($rreq as $k => $v) {
        if (preg_match('/\A(args|fmt|loglevel|marklog|_.*)\z/', $k)) continue;
        $hk = "x-riap-$k";
        $hv = $rreq[$k];
        if (!isset($hv) || is_array($hv) || preg_match('/\n/', $hv)) {
          $hk = "$hk-j-";
          $hv = json_encode($hv);
        }
        $headers[] = "$hk: $hv";
      }
      #$http_req->header('x-riap-marklog'  => $ua->{__mark_log});
      #$http_req->header('x-riap-loglevel' => $self->{log_level});
      $headers[] = 'x-riap-fmt: json';

      $args = isset($rreq['args']) ? $rreq['args'] : array();
      $args_s = json_encode($args);
      $headers[] = "Content-Type: application/json";
      $headers[] = "Content-Length: ".strlen($args_s);

      foreach($headers as $h) {
        #echo "DEBUG: Sending http header: $h\n";
        fputs($fp, "$h\n");
      }
      fputs($fp, "\n");

      # send args in request body
      fputs($fp, $args_s);
      #echo "DEBUG: Sending request body: <$args_s>\n";
      fputs($fp, "\n"); # XXX why do we need this?
      #echo "DEBUG: Done sending request (content-length: ".strlen($args_s).")\n";

      # parse response
      $status_line = fgets($fp);
      #echo "DEBUG: got status line: $status_line";
      preg_match('!^HTTP/\d\.\d (\d+) (.+)$!', $status_line, $m);
      $status_code = $m[1]; $status_msg = $m[2];
      $headers = array();
      while (1) {
        $header = fgets($fp);
        if (!preg_match('/\S/', $header)) break;
        #echo "DEBUG: got header: $header";
        preg_match('/^(\S+): (.+)$/', $header, $m); $headers[$m[1]] = $m[2];
      }
      #echo "DEBUG: headers: "; print_r($headers);
      if ($headers['Content-Length'] > 0) {
        $rest = $headers['Content-Length'];
        $bodies = array();
        while (1) {
          $body = fread($fp, min(8192, $rest));
          $rest -= strlen($body);
          #echo "DEBUG: got body (".strlen($body)." bytes, rest=$rest)\n";
          #echo "DEBUG: memory_get_usage() = ".memory_get_usage()."\n";
          $bodies[] = $body;
          if ($rest <= 0) break;
        }
        $res = json_decode(join("", $bodies), true);
        #echo "DEBUG: unserialized body: "; print_r($res);
      } else {
        # no content-length
        $bodies = array();
        while (1) {
          $body = fread($fp, 8192);
          if (!isset($body) || !strlen($body)) break;
          #echo "DEBUG: got body (".strlen($body)." bytes)\n";
          #echo "DEBUG: memory_get_usage() = ".memory_get_usage()."\n";
          $bodies[] = $body;
        }
        $res = json_decode(join("", $bodies), true);
      }

      # we get empty result from server, maybe spaneld is restarting itself, we
      # should try again
      #if ($status_code < 200) { sleep($retry_delay); $tries++; continue; }

      break;

    } # tries

    if (!$fp) { $res = array(504, "Can't connect to Unix socket $sock_path: ".
                             "$errstr ($errno)"); break; }
    break;

  } # while(1)

  # BEGIN COPY PASTE FROM phi_access_http_client.inc.php
  $ver = 1.1;
  if (isset($res[3]) && $res[3]['riap.v']) $ver = $res[3]['riap.v'];
  if ($ver >= 1.2) {
    # strip riap.* keys from result metadata
    foreach ($res[3] as $k => $val) {
      if (!preg_match('/\Ariap\./', $k)) continue;
      if ($k == 'riap.v') {
      } elseif ($k == 'riap.result_encoding') {
        if ($val != 'base64') return array(501, "Unknown result_encoding '$val', only 'base64' is supported");
        $res[2] = base64_decode($res[2]);
      } else {
        return array(501, "Unknown Riap attribute in result metadata '$k'");
      }
      unset($res[3][$k]);
    }
  }
  # END COPY PASTE FROM phi_access_http_client.inc.php

  return $res;
}
