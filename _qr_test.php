<?php
require __DIR__.'/lib/qr.php';
$data='S-'.bin2hex(random_bytes(16));
$qr=qrBuild($data);
echo "data=$data len=".strlen($data)."\n";
echo "moduleCount=".$qr->getModuleCount()."\n";
$svg=qrSvg($data,6,4);
echo "svgLen=".strlen($svg)." startsSvg=".(strncmp($svg,'<svg',4)===0?'Y':'N')." hasRect=".(strpos($svg,'<rect')!==false?'Y':'N')."\n";
// Dunkelmodul-Anzahl
$cnt=$qr->getModuleCount(); $dark=0;
for($r=0;$r<$cnt;$r++)for($c=0;$c<$cnt;$c++)if($qr->isDark($r,$c))$dark++;
echo "darkModules=$dark of ".($cnt*$cnt)."\n";
