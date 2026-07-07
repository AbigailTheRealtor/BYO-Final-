<?php
$items = json_decode(file_get_contents('scratchpad/items.json'), true);

// ---- F1 reconciliation: phase→commit ledger (authoritative over stale inline ⬜) ----
$phaseCommit = [
  8=>'85182b325', 9=>'21c1191a8 (+3)', 10=>'a45b9ffe3',
  11=>'4a12d503d', 12=>'284577b02', 13=>'bd0b1ba9d / f874cac85',
];
// Phase 13 items that are genuine verification/audit obligations (not a discrete commit)
$verifyItems = ['C1','C4','C5','C6','C7','C8','C12','C13','C14'];

foreach ($items as &$it) {
    $p = $it['phase']['num']; $id = $it['id']; $g = $it['g'];
    // defaults from glyph
    if ($g==='done')      { $vs='code';  $vnote='Implemented & code-verified.'; }
    elseif ($g==='partial'){ $vs='partial'; $vnote='Code done; a runtime/visual/multi-surface dimension remains — browser check.'; }
    else                  { $vs='verify'; $vnote='Verify in browser.'; }

    // reconcile stale ⬜ for committed phases
    if ($g==='todo' && isset($phaseCommit[$p]) && !in_array($id,$verifyItems)) {
        $vs='code'; $vnote='Committed in Phase '.$p.' ('.$phaseCommit[$p].') per F1 ledger; inline ⬜ was stale.';
    }
    // Phase 13 audit obligations
    if (in_array($id,$verifyItems)) { $vs='verify'; $vnote='Regression/audit obligation — this IS a QA step (part of the C13 matrix).'; }
    // special cases
    if ($id==='B5.4') { $vs='held'; $vnote='KNOWN HOLD: bed/bath “Other” select vanishes; browser-only + global has-icon JS. Expect Fail on observation.'; }
    if ($id==='F.1')  { $vs='code'; $vnote='Completed this session — see PHASE-14-F1-standards-reconciliation.md.'; }
    if ($id==='B1.2' || $id==='B1.4') { $vs='partial'; }
    $it['vs']=$vs; $it['vnote']=$vnote;
}
unset($it);

// counts
$c=['code'=>0,'partial'=>0,'verify'=>0,'held'=>0];
foreach($items as $it){$c[$it['vs']]++;}

// phase titles
$ptitles=[];
foreach($items as $it){$ptitles[$it['phase']['num']]=$it['phase']['title'];}

$json = json_encode($items, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$pt   = json_encode($ptitles, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$counts = json_encode($c);

$tpl = file_get_contents('scratchpad/full_tpl.html');
$out = str_replace(['/*ITEMS*/','/*PTITLES*/','/*COUNTS*/'], [$json,$pt,$counts], $tpl);
file_put_contents('public/phase-1-14-full-checklist.html', $out);
echo "written ".strlen($out)." bytes\n";
echo "code=$c[code] partial=$c[partial] verify=$c[verify] held=$c[held] (total ".array_sum($c).")\n";
