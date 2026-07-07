<?php
$roles = ['seller'=>'ai_faq_seller','buyer'=>'ai_faq_buyer','landlord'=>'ai_faq_landlord','tenant'=>'tenant_ai_faq'];
$ptypeGroup = ['residential'=>'Residential','income'=>'Income','commercial'=>'Commercial','business'=>'Business','land'=>'Vacant Land'];
foreach($roles as $role=>$file){
  $cfg = require "config/$file.php";
  echo "\n=====ROLE:$role=====\n";
  foreach($cfg['groups'] as $gname=>$cats){
    foreach($cats as $cat=>$qs){
      foreach($qs as $key=>$e){
        $lbl=str_replace('|','/',$e['label']);
        echo implode('|',[$gname,$cat,$key,$e['category_type'],$e['source'],$lbl])."\n";
      }
    }
  }
}
