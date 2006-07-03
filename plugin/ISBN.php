<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a ISBN macro plugin for the MoniWiki
//
// $Id$

function macro_ISBN($formatter="",$value="") {
  global $DBInfo;

  $ISBN_MAP="IsbnMap";
  $DEFAULT=<<<EOS
Amazon http://www.amazon.com/exec/obidos/ISBN= http://images.amazon.com/images/P/\$ISBN.01.MZZZZZZZ.gif
Aladdin http://www.aladdin.co.kr/shop/wproduct.aspx?ISBN= http://image.aladdin.co.kr/cover/cover/\$ISBN_1.gif @/cover/([^\s_/]+_1\..{3,4})\s@\\\$ISBN_1\.gif
Gang http://kangcom.com/common/qsearch/search.asp?s_flag=T&s_text= http://kangcom.com/l_pic/\$ISBN.jpg @bookinfo\.asp\?sku=(\d+)"@\n
EOS;

  $DEFAULT_ISBN="Amazon";
  $re_isbn="/([0-9\-]+[xX]?)(?:,\s*)?(([A-Z][A-Za-z]*)?(?:,)?(.*))?/x";

  if ($value!='') {
     $test=preg_match($re_isbn,$value,$match);
     if ($test === false)
        return "<p><strong class=\"error\">Invalid ISBN \"%value\"</strong></p>";
  }

  $list= $DEFAULT;
  $map= new WikiPage($ISBN_MAP);
  if ($map->exists()) $list.=$map->get_raw_body();

  $lists=explode("\n",$list);
  $ISBN_list=array();
  foreach ($lists as $line) {
     if (!$line or !preg_match("/^[A-Z]/",$line[0])) continue;
     $dum=explode(" ",rtrim($line));
     $re='';
     $sz=sizeof($dum);
     if (!preg_match('/^(http|ftp)/',$dum[1])) continue;
     if ($sz == 2) {
        $dum[]=$ISBN_list[$DEFAULT_ISBN][1];
     } else if ($sz!=3) {
        if ($sz == 4) {
          if (($p=strpos(substr($dum[3],1),$dum[3][0]))!==false) {
             $retest=substr($dum[3],0,$p+2);
          } else {
             $retest=$dum[3];
          }
          if (preg_match($retest,'')!==false) $re=$dum[3];
        }
        else continue;
     }

     $ISBN_list[$dum[0]]=array($dum[1],$dum[2],$re);
  }

  if ($value=='') {
    $out="<ul>";
    foreach ($ISBN_list as $interwiki=>$v) {
      $href=$ISBN_list[$interwiki][0];
      if (strpos($href,'$ISBN') === false)
        $url=$href.'0738206679';
      else {
        $url=str_replace('$ISBN','0738206679',$href);
      }
      $icon=$DBInfo->imgs_dir_interwiki.strtolower($interwiki).'-16.png';
      $sx=16;$sy=16;
      if ($DBInfo->intericon[$interwiki]) {
        $icon=$DBInfo->intericon[$interwiki][2];
        $sx=$DBInfo->intericon[$interwiki][0];
        $sy=$DBInfo->intericon[$interwiki][1];
      }
      $out.="<li><img src='$icon' width='$sx' height='$sy' ".
        "align='middle' alt='$interwiki:' /><a href='$url'>$interwiki</a>: ".
        "<tt class='link'>$href</tt></li>";
    }
    $out.="</ul>\n";
    return $out;
  }

  $isbn2=$match[1];
  $isbn=str_replace('-','',$isbn2);

  #print_r($match);
  if ($match[3]) {
    if (strtolower($match[2][0])=='k') $lang='Aladdin';
    else $lang=$match[3];
  } else $lang=$DEFAULT_ISBN;

  $attr='';
  $ext='';
  if ($match[2]) {
    $args=explode(',',$match[2]);
    foreach ($args as $arg) {
      $arg=trim($arg);
      if ($arg == 'noimg') $noimg=1;
      else if (strtolower($arg)=='k') $lang='Aladdin';
      else {
        $name=strtok($arg,'=');
        $val=strtok(' ');
        $attr.=$name.'="'.$val.'" ';
        if ($name == 'align') $attr.='class="img'.ucfirst($val).'" ';
        if ($name == 'img') $ext=$val;
      }
    }
  }

  if ($ISBN_list[$lang]) {
     $booklink=$ISBN_list[$lang][0];
     $imglink=$ISBN_list[$lang][1];
     $imgre=$ISBN_list[$lang][2];
  } else {
     $booklink=$ISBN_list[$DEFAULT_ISBN][0];
     $imglink=$ISBN_list[$DEFAULT_ISBN][1];
  }

  if (strpos($booklink,'$ISBN') === false)
     $booklink.=$isbn;
  else {
     if (strpos($booklink,'$ISBN2') === false)
        $booklink=str_replace('$ISBN',$isbn,$booklink);
     else
        $booklink=str_replace('$ISBN2',$isbn2,$booklink);
  }

  if ($imgre and get_cfg_var('allow_url_fopen')) {
     if (($p=strpos(substr($imgre,1),$imgre[0]))!==false) {
        $imgrepl=substr($imgre,$p+2);
        $imgre=substr($imgre,0,$p+2);
        if ($imgrepl=='@') $imgrepl='';
        $imgre=str_replace('$ISBN',$isbn,$imgre);
     }
     $md5sum=md5($booklink);
     // check cache
     $bcache=new Cache_text('isbn');
     if ($bcache->exists($md5sum)) {
        $imgname=trim($bcache->fetch($md5sum));

        if ($imgrepl)
           $imglink=preg_replace('@'.$imgrepl.'@',$imgname, $imglink);
        else
           $imglink=str_replace('$ISBN', $imgname, $imglink);
        $fetch_ok=1;
     } else {
        // fetch the bookinfo page and grep the imagname of the book.
        $fd=fopen($booklink,'r');
        if (is_resource($fd)) {
           while(!feof($fd)) {
              $line=fgets($fd,1024);
              preg_match($imgre,$line,$match);
              if ($match[1]) {
                 $bcache->update($md5sum,$match[1]);
                 if ($imgrepl)
                    $imglink=preg_replace('@'.$imgrepl.'@',$match[1], $imglink);
                 else
                    $imglink=str_replace('$ISBN', $match[1], $imglink);
                 $fetch_ok=1;
                 break;
              }
           }
           fclose($fd);
        }
     }
  }

  if (!$fetch_ok) {
     if (strpos($imglink, '$ISBN') === false)
        $imglink.=$isbn;
     else {
        if (strpos($imglink, '$ISBN2') === false)
           $imglink=str_replace('$ISBN', $isbn, $imglink);
        else
           $imglink=str_replace('$ISBN2', $isbn2, $imglink);
        if ($ext)
           $imglink=preg_replace('/\.(gif|jpeg|jpg|png|bmp)$/i', $ext, $imglink);
     }
  }

  if ($noimg) {
    $icon=$DBInfo->imgs_dir_interwiki.strtolower($lang).'-16.png';
    $sx=16;$sy=16;
    if ($DBInfo->intericon[$lang]) {
      $icon=$DBInfo->intericon[$lang][2];
      $sx=$DBInfo->intericon[$lang][0];
      $sy=$DBInfo->intericon[$lang][1];
    }
    return "<img src='$icon' alt='$lang:' align='middle' width='$sx' height='$sy' title='$lang' />"."[<a href='$booklink'>ISBN-$isbn2</a>]";
  } else
     return "<a href='$booklink'><img src='$imglink' border='1' title='$lang".
       ":ISBN-$isbn' alt='[ISBN-$isbn2]' class='isbn' $attr /></a>";
}

?>
