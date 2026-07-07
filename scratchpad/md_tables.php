<?php
$roles = ['seller'=>'ai_faq_seller','buyer'=>'ai_faq_buyer','landlord'=>'ai_faq_landlord','tenant'=>'tenant_ai_faq'];
$catLabel=['common'=>'Common','insight'=>'Insight'];
foreach($roles as $role=>$file){
  $cfg = require "config/$file.php";
  echo "\n\n###### $role ######\n";
  foreach($cfg['groups'] as $gname=>$cats){
    echo "\n@@GROUP:$gname\n";
    echo "| # | Question Key | Question Text | Category | Source(s) |\n|---|---|---|---|---|\n";
    $i=1;
    foreach($cats as $cat=>$qs){
      foreach($qs as $key=>$e){
        $lbl=str_replace('|','\\|',$e['label']);
        echo "| $i | `$key` | ".$lbl." | ".$catLabel[$e['category_type']]." | `".$e['source']."` |\n";
        $i++;
      }
    }
  }
}
