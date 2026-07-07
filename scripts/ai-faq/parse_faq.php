<?php
$roles = ['seller'=>'ai_faq_seller','buyer'=>'ai_faq_buyer','landlord'=>'ai_faq_landlord','tenant'=>'tenant_ai_faq'];
$grand=0;
$byRoleType=[];
$sourceTotals=[];
$dupWithinRole=[];
foreach($roles as $role=>$file){
  $cfg = require "config/$file.php";
  $gating = $cfg['gating'];
  $groups = $cfg['groups'];
  echo "\n########## ".strtoupper($role)."  (property types: ".implode(', ',array_keys($gating)).") ##########\n";
  // map each group name to which property types render it
  foreach($gating as $ptype=>$grpList){
    $count=0; $rows=[];
    foreach($grpList as $g){
      if(!isset($groups[$g])) continue;
      foreach($groups[$g] as $cat=>$qs){
        foreach($qs as $key=>$e){
          $count++;
          $rows[]=[$g,$cat,$key,$e['category_type'],$e['source']];
          $sourceTotals[$e['source']]=($sourceTotals[$e['source']]??0)+1;
        }
      }
    }
    $byRoleType["$role / $ptype"]=$count;
    echo "\n--- $ptype : $count questions (groups: ".implode('+',$grpList).") ---\n";
    foreach($rows as $r){ printf("  [%-11s|%-8s] %-42s %-9s src=%s\n",$r[0],$r[3],$r[2],$r[1],$r[4]); }
  }
  // count unique keys defined in this role's groups (dedup across property groups -universal counted once)
  $allkeys=[];
  foreach($groups as $g=>$cats){foreach($cats as $cat=>$qs){foreach($qs as $key=>$e){$allkeys[]=$key;}}}
  $uniq=array_unique($allkeys);
  $dups=array_diff_assoc($allkeys, $uniq);
  echo "\n  >> distinct question KEYS defined across all groups for $role: ".count($uniq)." (total entries incl. shared: ".count($allkeys).")\n";
  if($dups){ echo "  >> keys appearing in multiple groups: ".implode(', ',array_unique($dups))."\n"; }
}
echo "\n================ PER ROLE/TYPE RENDERED QUESTION COUNTS ================\n";
foreach($byRoleType as $k=>$v){ printf("  %-24s %d\n",$k,$v); }
echo "\n================ SOURCE CODE TOTALS (across rendered, universal double-counts per type) ================\n";
ksort($sourceTotals);
foreach($sourceTotals as $k=>$v){ printf("  %-16s %d\n",$k,$v); }
